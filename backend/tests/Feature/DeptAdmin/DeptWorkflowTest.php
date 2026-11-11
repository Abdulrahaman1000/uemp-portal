<?php

namespace Tests\Feature\DeptAdmin;

use App\Enums\ResultStatus;
use App\Models\AuditLog;
use App\Models\Result;
use Tests\Concerns\CreatesDemoData;
use Tests\Concerns\CreatesWorkflowData;
use Tests\TestCase;

class DeptWorkflowTest extends TestCase
{
    use CreatesDemoData;
    use CreatesWorkflowData;

    public function test_only_department_admins_can_reach_dept_endpoints(): void
    {
        $csc = $this->makeDepartment();
        $paths = ['dashboard', 'students', 'lecturers', 'courses', 'registrations', 'submissions', 'reports/students'];

        foreach ($paths as $path) {
            $this->app['auth']->forgetGuards();
            $this->getJson("/api/dept/{$path}")->assertUnauthorized();
        }
        foreach ($paths as $path) {
            foreach ([$this->makeLecturer($csc), $this->makeStudent($csc), $this->makeSuperAdmin()] as $user) {
                $this->getJson("/api/dept/{$path}", $this->bearer($user))->assertForbidden();
            }
        }
    }

    public function test_submission_list_shows_only_the_own_department(): void
    {
        $csc = $this->makeDepartment('CSC', 'Computer Science');
        $mth = $this->makeDepartment('MTH', 'Mathematics');
        $semester = $this->currentSemester();
        $cscLecturer = $this->makeLecturer($csc);
        $mthLecturer = $this->makeLecturer($mth);
        $this->makeSubmission($this->makeCourse('CSC301', $csc), $semester, $cscLecturer, 'submitted');
        $this->makeSubmission($this->makeCourse('CSC302', $csc), $semester, $cscLecturer, 'draft');
        $this->makeSubmission($this->makeCourse('MTH301', $mth), $semester, $mthLecturer, 'submitted');
        $admin = $this->makeDeptAdmin($csc);

        $response = $this->getJson('/api/dept/submissions', $this->bearer($admin))
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonStructure(['data' => [['id', 'courseCode', 'courseTitle', 'lecturerName', 'studentCount', 'status', 'submittedAt', 'audit']]]);
        $this->assertSame(['CSC301', 'CSC302'], collect($response->json('data'))->pluck('courseCode')->sort()->values()->all());

        $this->getJson('/api/dept/submissions?status=submitted', $this->bearer($admin))
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.courseCode', 'CSC301');
        $this->getJson('/api/dept/submissions?search=mth', $this->bearer($admin))
            ->assertOk()->assertJsonPath('meta.total', 0);
    }

    public function test_show_returns_own_submission_and_blocks_another_department(): void
    {
        $csc = $this->makeDepartment('CSC', 'Computer Science');
        $mth = $this->makeDepartment('MTH', 'Mathematics');
        $semester = $this->currentSemester();
        $own = $this->makeSubmission($this->makeCourse('CSC301', $csc), $semester, $this->makeLecturer($csc));
        $other = $this->makeSubmission($this->makeCourse('MTH301', $mth), $semester, $this->makeLecturer($mth));
        $admin = $this->makeDeptAdmin($csc);

        $this->getJson("/api/dept/submissions/{$own->id}", $this->bearer($admin))
            ->assertOk()->assertJsonPath('courseCode', 'CSC301')->assertJsonPath('status', 'submitted');
        $this->getJson("/api/dept/submissions/{$other->id}", $this->bearer($admin))->assertForbidden();
        $this->getJson("/api/dept/submissions/{$other->id}/results", $this->bearer($admin))->assertForbidden();
    }

    public function test_results_endpoint_lists_the_scores_under_review(): void
    {
        $csc = $this->makeDepartment();
        $semester = $this->currentSemester();
        $submission = $this->makeSubmission($this->makeCourse('CSC301', $csc), $semester, $this->makeLecturer($csc));
        foreach ([$this->makeStudent($csc), $this->makeStudent($csc)] as $student) {
            Result::create(['result_submission_id' => $submission->id, 'student_id' => $student->student->id, 'ca_score' => 20, 'exam_score' => 50]);
        }
        $admin = $this->makeDeptAdmin($csc);

        $response = $this->getJson("/api/dept/submissions/{$submission->id}/results", $this->bearer($admin))
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'studentId', 'matricNo', 'studentName', 'caScore', 'examScore', 'totalScore', 'grade', 'gradePoint']]]);
        $this->assertCount(2, $response->json('data'));
        $this->assertEquals(20, $response->json('data.0.caScore'));
        $this->assertEquals(50, $response->json('data.0.examScore'));
    }

    public function test_approving_a_submitted_result_works_once_and_is_audited(): void
    {
        $csc = $this->makeDepartment();
        $submission = $this->makeSubmission($this->makeCourse('CSC301', $csc), $this->currentSemester(), $this->makeLecturer($csc));
        $admin = $this->makeDeptAdmin($csc);

        $this->postJson("/api/dept/submissions/{$submission->id}/approve", [], $this->bearer($admin))
            ->assertOk()->assertJsonPath('status', 'approved');

        $submission->refresh();
        $this->assertSame(ResultStatus::Approved, $submission->status);
        $this->assertSame($admin->id, (int) $submission->reviewed_by);
        $this->assertNotNull($submission->reviewed_at);
        $this->assertSame(1, AuditLog::where('action', 'RESULT_APPROVED')->where('auditable_id', $submission->id)->count());

        $this->postJson("/api/dept/submissions/{$submission->id}/approve", [], $this->bearer($admin))->assertStatus(422);
        $this->assertSame(1, AuditLog::where('action', 'RESULT_APPROVED')->count());
    }

    public function test_a_draft_cannot_be_approved_or_rejected(): void
    {
        $csc = $this->makeDepartment();
        $draft = $this->makeSubmission($this->makeCourse('CSC301', $csc), $this->currentSemester(), $this->makeLecturer($csc), 'draft');
        $admin = $this->makeDeptAdmin($csc);

        $this->postJson("/api/dept/submissions/{$draft->id}/approve", [], $this->bearer($admin))->assertStatus(422);
        $this->postJson("/api/dept/submissions/{$draft->id}/reject", ['feedback' => 'Not ready'], $this->bearer($admin))->assertStatus(422);
        $this->assertSame(ResultStatus::Draft, $draft->refresh()->status);
        $this->assertSame(0, AuditLog::whereIn('action', ['RESULT_APPROVED', 'RESULT_REJECTED'])->count());
    }

    public function test_another_department_cannot_approve_reject_or_reopen(): void
    {
        $csc = $this->makeDepartment('CSC', 'Computer Science');
        $mth = $this->makeDepartment('MTH', 'Mathematics');
        $semester = $this->currentSemester();
        $other = $this->makeSubmission($this->makeCourse('MTH301', $mth), $semester, $this->makeLecturer($mth));
        $admin = $this->makeDeptAdmin($csc);

        $this->postJson("/api/dept/submissions/{$other->id}/approve", [], $this->bearer($admin))->assertForbidden();
        $this->postJson("/api/dept/submissions/{$other->id}/reject", ['feedback' => 'Check scores'], $this->bearer($admin))->assertForbidden();
        $this->postJson("/api/dept/submissions/{$other->id}/reopen", [], $this->bearer($admin))->assertForbidden();
        $this->assertSame(ResultStatus::Submitted, $other->refresh()->status);
        $this->assertSame(0, AuditLog::where('action', 'like', 'RESULT_%')->count());
    }

    public function test_rejecting_needs_feedback_and_stores_it(): void
    {
        $csc = $this->makeDepartment();
        $submission = $this->makeSubmission($this->makeCourse('CSC301', $csc), $this->currentSemester(), $this->makeLecturer($csc));
        $admin = $this->makeDeptAdmin($csc);

        $this->postJson("/api/dept/submissions/{$submission->id}/reject", [], $this->bearer($admin))
            ->assertStatus(422)->assertJsonValidationErrors(['feedback']);
        $this->assertSame(ResultStatus::Submitted, $submission->refresh()->status);

        $response = $this->postJson("/api/dept/submissions/{$submission->id}/reject", ['feedback' => 'Exam scores look swapped.'], $this->bearer($admin))
            ->assertOk()->assertJsonPath('status', 'rejected')->assertJsonPath('feedback', 'Exam scores look swapped.');

        $this->assertSame('RESULT_REJECTED', collect($response->json('audit'))->last()['action']);
        $this->assertSame('Exam scores look swapped.', collect($response->json('audit'))->last()['note']);
        $this->assertSame(ResultStatus::Rejected, $submission->refresh()->status);
    }

    public function test_only_approved_results_can_be_reopened(): void
    {
        $csc = $this->makeDepartment();
        $semester = $this->currentSemester();
        $lecturer = $this->makeLecturer($csc);
        $submitted = $this->makeSubmission($this->makeCourse('CSC301', $csc), $semester, $lecturer, 'submitted');
        $approved = $this->makeSubmission($this->makeCourse('CSC302', $csc), $semester, $lecturer, 'approved');
        $admin = $this->makeDeptAdmin($csc);

        $this->postJson("/api/dept/submissions/{$submitted->id}/reopen", [], $this->bearer($admin))->assertStatus(422);
        $this->assertSame(ResultStatus::Submitted, $submitted->refresh()->status);

        $this->postJson("/api/dept/submissions/{$approved->id}/reopen", ['feedback' => 'Wrong grade for one student'], $this->bearer($admin))
            ->assertOk()->assertJsonPath('status', 'reopened');
        $this->assertSame(ResultStatus::Reopened, $approved->refresh()->status);
        $this->assertSame(1, AuditLog::where('action', 'RESULT_REOPENED')->where('auditable_id', $approved->id)->count());
    }

    public function test_a_dept_admin_without_a_department_sees_nothing_and_changes_nothing(): void
    {
        $csc = $this->makeDepartment();
        $submission = $this->makeSubmission($this->makeCourse('CSC301', $csc), $this->currentSemester(), $this->makeLecturer($csc));
        $admin = $this->makeDeptAdmin($csc, ['department_id' => null]);

        $this->getJson('/api/dept/submissions', $this->bearer($admin))->assertOk()->assertJsonPath('meta.total', 0);
        $this->postJson("/api/dept/submissions/{$submission->id}/approve", [], $this->bearer($admin))->assertForbidden();
        $this->assertSame(ResultStatus::Submitted, $submission->refresh()->status);
    }
}
