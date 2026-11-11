<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Resources\CourseResource;
use App\Http\Resources\RegistrationResource;
use App\Models\Course;
use App\Models\Registration;
use App\Services\AuditLogger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Course registration. Rules (enforced here, never only in the UI):
 *  1. a student registers only courses of their own department, program, level and the CURRENT semester;
 *  2. a course cannot be registered twice in the same semester (also guarded by a unique index);
 *  3. every registration is written to the audit log.
 */
class StudentRegistrationController extends StudentController
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    /** GET /api/student/available-courses: eligible courses of the current semester, flagged when already registered. */
    public function available(Request $request): array
    {
        $student = $this->student($request);
        $semester = $this->currentSemester();

        if (! $semester) {
            return ['data' => [], 'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => 0], 'sessionName' => '', 'semester' => ''];
        }

        $registeredIds = $this->registeredCourseIds($student->id, $semester->id);
        $rows = $this->eligibleQuery($student, $semester->name->value)
            ->with('department', 'currentAssignment.lecturer.user')
            ->orderBy('code')
            ->get()
            ->map(fn (Course $course) => array_merge(
                (new CourseResource($course))->resolve($request),
                ['registered' => in_array($course->id, $registeredIds, true)],
            ))
            ->values()
            ->all();

        return [
            'data' => $rows,
            'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => count($rows)],
            'sessionName' => $semester->session?->name ?? '',
            'semester' => $semester->name->value,
        ];
    }

    /** POST /api/student/registrations  body: { courseIds: [..] } */
    public function store(Request $request): JsonResponse
    {
        $student = $this->student($request);
        $data = $request->validate([
            'courseIds' => ['required', 'array', 'min:1', 'max:30'],
            'courseIds.*' => ['required', 'integer', 'distinct'],
        ], [
            'courseIds.required' => 'Select at least one course.',
            'courseIds.min' => 'Select at least one course.',
        ]);

        if ($student->graduated_at) {
            abort(422, 'Graduated students cannot register for courses.');
        }
        $semester = $this->currentSemester();
        if (! $semester) {
            abort(422, 'Registration is closed: there is no current semester.');
        }

        // Rule 1: department, program, level and semester.
        $courses = Course::query()->whereIn('id', $data['courseIds'])->get()->keyBy('id');
        $errors = [];
        foreach ($data['courseIds'] as $index => $id) {
            $course = $courses->get((int) $id);
            $reason = match (true) {
                $course === null => 'This course does not exist.',
                (int) $course->department_id !== (int) $student->department_id => "{$course->code} is not offered by your department.",
                (int) $course->program_id !== (int) $student->program_id => "{$course->code} is not part of your program.",
                (int) $course->level !== (int) $student->level => "{$course->code} is not a {$student->level} level course.",
                $course->semester !== $semester->name => "{$course->code} is not offered in the {$semester->name->value} semester.",
                default => null,
            };
            if ($reason !== null) {
                $errors["courseIds.{$index}"] = [$reason];
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        try {
            $registration = DB::transaction(function () use ($student, $semester, $data, $courses, $request) {
                $registration = Registration::query()
                    ->where('student_id', $student->id)
                    ->where('semester_id', $semester->id)
                    ->lockForUpdate()
                    ->first() ?? Registration::create([
                        'student_id' => $student->id,
                        'semester_id' => $semester->id,
                        'registered_at' => now(),
                    ]);

                // Rule 2: no duplicate course in the same semester.
                $already = $registration->courses()->pluck('courses.id')->map(fn ($id) => (int) $id)->all();
                $errors = [];
                foreach ($data['courseIds'] as $index => $id) {
                    if (in_array((int) $id, $already, true)) {
                        $errors["courseIds.{$index}"] = ['You have already registered '.$courses->get((int) $id)->code.' this semester.'];
                    }
                }
                if ($errors !== []) {
                    throw ValidationException::withMessages($errors);
                }

                $registration->courses()->attach(array_map('intval', $data['courseIds']));

                $picked = $courses->whereIn('id', array_map('intval', $data['courseIds']));
                $this->audit->record(
                    $request->user(),
                    'COURSE_REGISTERED',
                    $picked->pluck('code')->implode(', '),
                    'Registered '.$picked->count().' course(s) ('.$picked->sum('units').' units)',
                    $registration,
                );

                return $registration;
            });
        } catch (UniqueConstraintViolationException) {
            abort(422, 'One of these courses is already registered.');
        }

        return (new RegistrationResource($this->loaded($registration->id)))->response()->setStatusCode(201);
    }

    /** GET /api/student/registrations: every registration of this student, newest semester first. */
    public function index(Request $request): array
    {
        $student = $this->student($request);

        $rows = Registration::query()
            ->select('registrations.*')
            ->join('semesters', 'semesters.id', '=', 'registrations.semester_id')
            ->where('registrations.student_id', $student->id)
            ->with('student.user', 'student.department', 'semester.session', 'courses')
            ->orderByDesc('semesters.start_date')
            ->get()
            ->map(fn (Registration $registration) => (new RegistrationResource($registration))->resolve($request))
            ->values()
            ->all();

        return ['data' => $rows, 'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => count($rows)]];
    }

    /** @return \Illuminate\Database\Eloquent\Builder<Course> */
    private function eligibleQuery($student, string $semesterName)
    {
        return Course::query()
            ->where('department_id', $student->department_id)
            ->where('program_id', $student->program_id)
            ->where('level', $student->level)
            ->where('semester', $semesterName);
    }

    /** @return list<int> */
    private function registeredCourseIds(int $studentId, int $semesterId): array
    {
        return DB::table('registration_items')
            ->join('registrations', 'registrations.id', '=', 'registration_items.registration_id')
            ->where('registrations.student_id', $studentId)
            ->where('registrations.semester_id', $semesterId)
            ->pluck('registration_items.course_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function loaded(int $id): Registration
    {
        return Registration::query()
            ->with('student.user', 'student.department', 'semester.session', 'courses')
            ->findOrFail($id);
    }
}
