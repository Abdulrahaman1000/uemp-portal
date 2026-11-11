<?php

namespace App\Http\Controllers\Api\Lecturer;

use App\Enums\ResultStatus;
use App\Http\Requests\Lecturer\SaveScoresRequest;
use App\Http\Resources\SubmissionResource;
use App\Models\Course;
use App\Models\Result;
use App\Models\ResultSubmission;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use App\Support\LikeSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Lecturer result entry. Rules: only assigned courses; scores can be edited only while the
 * submission is draft, rejected or reopened; maximums, total, grade and grade point come from
 * config/grading.php (GradingService); every save and submit is written to the audit log.
 */
class LecturerResultController extends LecturerController
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    /** PUT /api/lecturer/courses/{course}/results  body: { scores: [{studentId, caScore, examScore}] } */
    public function save(SaveScoresRequest $request, Course $course): array
    {
        $lecturer = $this->ownCourse($request, $course);
        $semester = $this->writableSemester($course);
        $scores = $request->validated('scores');

        // Every student must be registered for this course in this semester.
        $classIds = $this->classList($course, $semester)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $errors = [];
        foreach ($scores as $index => $row) {
            if (! in_array((int) $row['studentId'], $classIds, true)) {
                $errors["scores.{$index}.studentId"] = ['This student is not registered for the course.'];
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $user = $request->user();

        DB::transaction(function () use ($course, $semester, $lecturer, $scores, $user) {
            $submission = ResultSubmission::query()
                ->where('course_id', $course->id)
                ->where('semester_id', $semester->id)
                ->lockForUpdate()
                ->first();

            if (! $submission) {
                $submission = ResultSubmission::create([
                    'course_id' => $course->id,
                    'semester_id' => $semester->id,
                    'lecturer_id' => $lecturer->id,
                    'status' => ResultStatus::Draft,
                ]);
            }

            $this->assertEditable($submission);

            $saved = 0;
            foreach ($scores as $row) {
                $ca = $this->number($row['caScore'] ?? null);
                $exam = $this->number($row['examScore'] ?? null);
                $studentId = (int) $row['studentId'];

                $existing = Result::query()
                    ->where('result_submission_id', $submission->id)
                    ->where('student_id', $studentId)
                    ->first();
                if ($ca === null && $exam === null && ! $existing) {
                    continue;
                }

                $computed = $this->grading()->compute($ca, $exam);
                Result::updateOrCreate(
                    ['result_submission_id' => $submission->id, 'student_id' => $studentId],
                    [
                        'ca_score' => $ca,
                        'exam_score' => $exam,
                        'total_score' => $computed['total'],
                        'grade' => $computed['grade'],
                        'grade_point' => $computed['grade_point'],
                    ],
                );
                $saved++;
            }

            $this->audit->record(
                $user,
                'RESULT_DRAFT_SAVED',
                $course->code,
                "Saved scores for {$saved} student(s) in {$course->code}",
                $submission,
            );
        });

        return $this->payload($course);
    }

    /** POST /api/lecturer/courses/{course}/results/submit */
    public function submit(Request $request, Course $course): array
    {
        $this->ownCourse($request, $course);
        $semester = $this->writableSemester($course);
        $user = $request->user();

        DB::transaction(function () use ($course, $semester, $user) {
            $submission = ResultSubmission::query()
                ->where('course_id', $course->id)
                ->where('semester_id', $semester->id)
                ->lockForUpdate()
                ->first();

            if (! $submission) {
                abort(422, 'Enter and save the scores before submitting.');
            }

            $this->assertEditable($submission);

            $students = $this->classList($course, $semester);
            if ($students->isEmpty()) {
                abort(422, 'No students are registered for this course.');
            }

            $results = $submission->results()->get()->keyBy('student_id');
            $incomplete = $students->filter(function ($student) use ($results) {
                $result = $results->get($student->id);

                return ! $result || $result->ca_score === null || $result->exam_score === null;
            });
            if ($incomplete->isNotEmpty()) {
                $list = $incomplete->take(5)->pluck('matric_no')->implode(', ');
                $more = $incomplete->count() > 5 ? ' and more' : '';
                abort(422, "Both scores are required for every student. Missing for {$incomplete->count()} student(s): {$list}{$more}.");
            }

            $submission->update([
                'status' => ResultStatus::Submitted,
                'submitted_at' => now(),
                'feedback' => null,
            ]);

            $this->audit->record(
                $user,
                'RESULT_SUBMITTED',
                $course->code,
                "Submitted {$students->count()} result(s) for {$course->code} for review",
                $submission,
            );
        });

        return $this->payload($course);
    }

    /** GET /api/lecturer/submissions?status=&search=&page=&per_page= : this lecturer's own submissions with feedback. */
    public function submissions(Request $request): array
    {
        $lecturer = $this->lecturer($request);

        // select() must come BEFORE withCount(): a later select() would drop the count column.
        $query = ResultSubmission::query()
            ->select('result_submissions.*')
            ->with([
                'course.department',
                'lecturer.user',
                'semester.session',
                'auditLogs' => fn ($q) => $q->orderBy('created_at')->orderBy('id'),
            ])
            ->withCount('results as student_count')
            ->join('courses', 'courses.id', '=', 'result_submissions.course_id')
            ->where('result_submissions.lecturer_id', $lecturer->id)
            ->orderByDesc('result_submissions.updated_at')
            ->orderByDesc('result_submissions.id');

        $term = ApiResponse::search($request);
        if ($term !== null) {
            $query->where(fn (Builder $group) => LikeSearch::apply($group, $term, ['courses.code', 'courses.title']));
        }

        $status = $request->query('status');
        if (is_string($status) && ResultStatus::tryFrom($status)) {
            $query->where('result_submissions.status', $status);
        }

        return ApiResponse::paginated($query->paginate(ApiResponse::perPage($request)), SubmissionResource::class);
    }

    private function assertEditable(ResultSubmission $submission): void
    {
        if ($submission->status->isEditableByLecturer()) {
            return;
        }

        abort(422, $submission->status === ResultStatus::Approved
            ? 'Approved results are locked. Ask the department admin to reopen them.'
            : 'These results are submitted for review and cannot be changed.');
    }

    private function number(mixed $value): ?float
    {
        return $value === null || $value === '' ? null : (float) $value;
    }
}
