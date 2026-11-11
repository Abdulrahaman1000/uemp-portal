<?php

namespace App\Http\Controllers\Api\DeptAdmin;

use App\Enums\ResultStatus;
use App\Http\Resources\SubmissionResource;
use App\Models\ResultSubmission;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use App\Support\DepartmentScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Department admin: review the results that lecturers of this department submitted.
 * Rules: only submitted results can be approved or rejected; a rejection needs feedback;
 * only approved results can be reopened; every action is written to the audit log.
 */
class DepartmentSubmissionController extends DeptAdminController
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    /** GET /api/dept/submissions?status=&search=&page=&per_page= */
    public function index(Request $request): array
    {
        // select() must come BEFORE withCount(): a later select() would drop the count column.
        $query = ResultSubmission::query()
            ->select('result_submissions.*')
            ->with($this->relations())
            ->withCount('results as student_count')
            ->join('courses', 'courses.id', '=', 'result_submissions.course_id')
            ->orderByDesc('result_submissions.updated_at')
            ->orderByDesc('result_submissions.id');

        DepartmentScope::apply($query, $request->user(), 'courses.department_id');
        $this->search($query, $request, ['courses.code', 'courses.title']);

        $status = $request->query('status');
        if (is_string($status) && ResultStatus::tryFrom($status)) {
            $query->where('result_submissions.status', $status);
        }

        return ApiResponse::paginated($query->paginate(ApiResponse::perPage($request)), SubmissionResource::class);
    }

    /** GET /api/dept/submissions/{submission} */
    public function show(Request $request, ResultSubmission $submission): SubmissionResource
    {
        $this->authorizeSubmission($request, $submission);

        return new SubmissionResource($this->loaded($submission->getKey()));
    }

    /** GET /api/dept/submissions/{submission}/results: the scores the admin is reviewing. */
    public function results(Request $request, ResultSubmission $submission): array
    {
        $this->authorizeSubmission($request, $submission);

        $rows = $submission->results()->with('student.user')->get()
            ->sortBy(fn ($result) => $result->student?->matric_no ?? '')
            ->values()
            ->map(fn ($result) => [
                'id' => (string) $result->id,
                'studentId' => (string) $result->student_id,
                'matricNo' => $result->student?->matric_no ?? '',
                'studentName' => $result->student?->user?->name ?? '',
                'caScore' => $result->ca_score !== null ? (float) $result->ca_score : null,
                'examScore' => $result->exam_score !== null ? (float) $result->exam_score : null,
                'totalScore' => $result->total_score !== null ? (float) $result->total_score : null,
                'grade' => $result->grade,
                'gradePoint' => $result->grade_point !== null ? (float) $result->grade_point : null,
            ])
            ->all();

        return ['data' => $rows];
    }

    /** POST /api/dept/submissions/{submission}/approve */
    public function approve(Request $request, ResultSubmission $submission): SubmissionResource
    {
        return $this->review(
            $request, $submission,
            [ResultStatus::Submitted], ResultStatus::Approved,
            'RESULT_APPROVED', 'Approved results for %s',
            'Only submitted results can be approved.', null,
        );
    }

    /** POST /api/dept/submissions/{submission}/reject  body: { feedback } (required) */
    public function reject(Request $request, ResultSubmission $submission): SubmissionResource
    {
        $this->authorizeSubmission($request, $submission);
        $data = $request->validate(['feedback' => ['required', 'string', 'min:3', 'max:1000']]);

        return $this->review(
            $request, $submission,
            [ResultStatus::Submitted], ResultStatus::Rejected,
            'RESULT_REJECTED', 'Rejected results for %s and returned them to the lecturer',
            'Only submitted results can be rejected.', $data['feedback'],
        );
    }

    /** POST /api/dept/submissions/{submission}/reopen  body: { feedback } (optional reason) */
    public function reopen(Request $request, ResultSubmission $submission): SubmissionResource
    {
        $this->authorizeSubmission($request, $submission);
        $data = $request->validate(['feedback' => ['nullable', 'string', 'max:1000']]);

        return $this->review(
            $request, $submission,
            [ResultStatus::Approved], ResultStatus::Reopened,
            'RESULT_REOPENED', 'Reopened approved results for %s so the lecturer can edit them',
            'Only approved results can be reopened.', $data['feedback'] ?? null,
        );
    }

    /**
     * Move a submission to a new status inside one transaction. The row is locked and the
     * status is checked again, so two admins cannot approve and reject the same submission.
     *
     * @param  list<ResultStatus>  $allowedFrom
     */
    private function review(
        Request $request,
        ResultSubmission $submission,
        array $allowedFrom,
        ResultStatus $to,
        string $action,
        string $detailsTemplate,
        string $failMessage,
        ?string $note,
    ): SubmissionResource {
        $user = $request->user();
        $this->authorizeSubmission($request, $submission);
        $code = $submission->course->code;

        DB::transaction(function () use ($user, $submission, $allowedFrom, $to, $action, $detailsTemplate, $failMessage, $note, $code) {
            $locked = ResultSubmission::query()->whereKey($submission->getKey())->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, $allowedFrom, true)) {
                abort(422, $failMessage);
            }

            $locked->update([
                'status' => $to,
                'feedback' => $note,
                'reviewed_by' => $user->id,
                'reviewed_at' => now(),
            ]);

            $this->audit->record(
                $user,
                $action,
                $code,
                sprintf($detailsTemplate, $code),
                $locked,
                $note !== null ? ['note' => $note] : [],
            );
        });

        return new SubmissionResource($this->loaded($submission->getKey()));
    }

    /** 403 unless the submission's course belongs to the admin's own department. */
    private function authorizeSubmission(Request $request, ResultSubmission $submission): void
    {
        $submission->loadMissing('course');
        DepartmentScope::authorize($request->user(), $submission->course?->department_id);
    }

    private function loaded(int|string $id): ResultSubmission
    {
        return ResultSubmission::query()
            ->with($this->relations())
            ->withCount('results as student_count')
            ->findOrFail($id);
    }

    /** @return array<int|string, mixed> */
    private function relations(): array
    {
        return [
            'course.department',
            'lecturer.user',
            'semester.session',
            'auditLogs' => fn ($query) => $query->orderBy('created_at')->orderBy('id'),
        ];
    }
}
