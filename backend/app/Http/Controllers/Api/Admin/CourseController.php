<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CourseRequest;
use App\Http\Resources\CourseResource;
use App\Models\AcademicSession;
use App\Models\Course;
use App\Models\CourseAssignment;
use App\Models\Lecturer;
use App\Models\ResultSubmission;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use App\Support\LikeSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Super Admin: courses and lecturer allocation (T2.5c).
 * A course's lecturer is stored per academic session (course_assignments); saving a course
 * with `lecturerId` sets the assignment for the CURRENT session, `lecturerId: null` removes it.
 */
class CourseController extends Controller
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    /** GET /api/admin/courses?search=&departmentId=&level=&semester=&page=&per_page= */
    public function index(Request $request): array
    {
        $query = Course::query()
            ->with(['department', 'currentAssignment.lecturer.user'])
            ->orderBy('code');

        LikeSearch::apply($query, ApiResponse::search($request), ['code', 'title']);

        if (ctype_digit((string) $request->query('departmentId'))) {
            $query->where('department_id', (int) $request->query('departmentId'));
        }
        if (ctype_digit((string) $request->query('level'))) {
            $query->where('level', (int) $request->query('level'));
        }
        if (in_array($request->query('semester'), ['First', 'Second'], true)) {
            $query->where('semester', $request->query('semester'));
        }

        return ApiResponse::paginated($query->paginate(ApiResponse::perPage($request)), CourseResource::class);
    }

    /** POST /api/admin/courses */
    public function store(CourseRequest $request): JsonResponse
    {
        $actor = $request->user();

        $course = DB::transaction(function () use ($request, $actor) {
            $course = Course::create($request->courseAttributes());

            $this->audit->record($actor, 'RECORD_CREATED', $course->code, "Created course {$course->code}: {$course->title}", $course);

            if ($request->filled('lecturerId')) {
                $this->assignLecturer($course, (int) $request->input('lecturerId'), $actor);
            }

            return $course;
        });

        return (new CourseResource($this->fresh($course)))->response()->setStatusCode(201);
    }

    /** PUT /api/admin/courses/{course} */
    public function update(CourseRequest $request, Course $course): CourseResource
    {
        $actor = $request->user();
        $attributes = $request->courseAttributes();

        $this->guardAcademicFields($course, $attributes);

        DB::transaction(function () use ($request, $course, $attributes, $actor) {
            $course->fill($attributes);
            if ($course->isDirty()) {
                $changed = implode(', ', array_keys($course->getDirty()));
                $course->save();
                $this->audit->record($actor, 'RECORD_UPDATED', $course->code, "Updated course {$course->code} ({$changed})", $course);
            }

            if ($request->exists('lecturerId')) {
                $lecturerId = $request->filled('lecturerId') ? (int) $request->input('lecturerId') : null;
                $lecturerId === null
                    ? $this->unassignLecturer($course, $actor)
                    : $this->assignLecturer($course, $lecturerId, $actor);
            }
        });

        return new CourseResource($this->fresh($course));
    }

    /** DELETE /api/admin/courses/{course} */
    public function destroy(Request $request, Course $course): Response
    {
        if ($this->hasAcademicRecords($course)) {
            abort(422, "Cannot delete {$course->code}: students have registered for it or results exist.");
        }

        DB::transaction(function () use ($request, $course) {
            $course->assignments()->delete();
            $course->delete();
            $this->audit->record($request->user(), 'RECORD_DELETED', $course->code, "Deleted course {$course->code}: {$course->title}", $course);
        });

        return response()->noContent();
    }

    private function fresh(Course $course): Course
    {
        return $course->fresh(['department', 'currentAssignment.lecturer.user']);
    }

    /** Registrations and results depend on a course, so they must not be changed underneath them. */
    private function hasAcademicRecords(Course $course): bool
    {
        return DB::table('registration_items')->where('course_id', $course->id)->exists()
            || $course->resultSubmissions()->exists();
    }

    /** @param  array<string, mixed>  $attributes */
    private function guardAcademicFields(Course $course, array $attributes): void
    {
        if (! $this->hasAcademicRecords($course)) {
            return;
        }

        $messages = [];
        $fields = [
            'units' => ['units', 'units'],
            'level' => ['level', 'level'],
            'semester' => ['semester', 'semester'],
            'departmentId' => ['department_id', 'department'],
            'programId' => ['program_id', 'program'],
        ];
        foreach ($fields as $key => [$column, $label]) {
            $current = $course->{$column} instanceof \BackedEnum ? $course->{$column}->value : $course->{$column};
            if ((string) $current !== (string) $attributes[$column]) {
                $messages[$key] = "The {$label} cannot be changed because students have registered for this course or results exist.";
            }
        }

        if ($messages !== []) {
            throw ValidationException::withMessages($messages);
        }
    }

    private function assignLecturer(Course $course, int $lecturerId, User $actor): void
    {
        $session = AcademicSession::current()->first();
        if (! $session) {
            throw ValidationException::withMessages(['lecturerId' => 'Set a current academic session before assigning a lecturer.']);
        }

        $existing = CourseAssignment::where('course_id', $course->id)->where('academic_session_id', $session->id)->first();
        if ($existing && $existing->lecturer_id === $lecturerId) {
            return;
        }
        $this->guardAgainstStartedResults($course, $session);

        CourseAssignment::updateOrCreate(
            ['course_id' => $course->id, 'academic_session_id' => $session->id],
            ['lecturer_id' => $lecturerId],
        );

        $lecturer = Lecturer::with('user')->find($lecturerId);
        $this->audit->record(
            $actor,
            'COURSE_ASSIGNED',
            $course->code,
            "Assigned {$lecturer?->user?->name} to {$course->code} for {$session->name}",
            $course,
        );
    }

    private function unassignLecturer(Course $course, User $actor): void
    {
        $session = AcademicSession::current()->first();
        $existing = $session
            ? CourseAssignment::with('lecturer.user')->where('course_id', $course->id)->where('academic_session_id', $session->id)->first()
            : null;
        if (! $existing) {
            return;
        }
        $this->guardAgainstStartedResults($course, $session);

        $existing->delete();
        $this->audit->record(
            $actor,
            'COURSE_UNASSIGNED',
            $course->code,
            "Removed {$existing->lecturer?->user?->name} from {$course->code} for {$session->name}",
            $course,
        );
    }

    /** A lecturer who already started or submitted results for this session's semesters cannot be swapped out. */
    private function guardAgainstStartedResults(Course $course, AcademicSession $session): void
    {
        $started = ResultSubmission::where('course_id', $course->id)
            ->whereIn('semester_id', $session->semesters()->pluck('id'))
            ->exists();

        if ($started) {
            throw ValidationException::withMessages([
                'lecturerId' => 'The lecturer cannot be changed because results were already started for this course this session.',
            ]);
        }
    }
}
