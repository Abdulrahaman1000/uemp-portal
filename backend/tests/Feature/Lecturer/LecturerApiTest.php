<?php

namespace Tests\Feature\Lecturer;

use App\Models\AuditLog;
use App\Models\Course;
use App\Models\CourseAssignment;
use App\Models\Registration;
use App\Models\Semester;
use App\Models\User;
use Tests\Concerns\CreatesDemoData;
use Tests\Concerns\CreatesWorkflowData;
use Tests\TestCase;

class LecturerApiTest extends TestCase
{
    use CreatesDemoData;
    use CreatesWorkflowData;

    /** @return array{0: User, 1: Course, 2: Semester, 3: User, 4: User} lecturer, course, semester, student A, student B */
    private function classroom(): array
    {
        $csc = $this->makeDepartment();
        $semester = $this->currentSemester();
        $lecturer = $this->makeLecturer($csc);
        $course = $this->makeCourse('CSC301', $csc);
        CourseAssignment::create(['course_id' => $course->id, 'lecturer_id' => $lecturer->lecturer->id, 'academic_session_id' => $semester->academic_session_id]);

        $a = $this->makeStudent($csc);
        $b = $this->makeStudent($csc);
        $this->register($a, $semester, $course);
        $this->register($b, $semester, $course);

        return [$lecturer, $course, $semester, $a, $b];
    }

    private function register(User $student, Semester $semester, Course $course): void
    {
        $registration = Registration::firstOrCreate(['student_id' => $student->student->id, 'semester_id' => $semester->id]);
        $registration->courses()->syncWithoutDetaching([$course->id]);
    }

    /** @return array<string, mixed> */
    private function scores(User $a, int|float|null $caA, int|float|null $examA, ?User $b = null, int|float|null $caB = null, int|float|null $examB = null): array
    {
        $rows = [['studentId' => (string) $a->student->id, 'caScore' => $caA, 'examScore' => $examA]];
        if ($b) {
            $rows[] = ['studentId' => (string) $b->student->id, 'caScore' => $caB, 'examScore' => $examB];
        }

        return ['scores' => $rows];
    }

    public function test_only_lecturers_can_reach_lecturer_endpoints(): void
    {
        [$lecturer, $course] = $this->classroom();
        $csc = $this->makeDepartment();
        $paths = ['dashboard', 'courses', 'submissions', "courses/{$course->id}/students", "courses/{$course->id}/summary"];

        foreach ($paths as $path) {
            $this->app['auth']->forgetGuards();
            $this->getJson("/api/lecturer/{$path}")->assertUnauthorized();
        }
        foreach ($paths as $path) {
            foreach ([$this->makeStudent($csc), $this->makeDeptAdmin($csc), $this->makeSuperAdmin()] as $user) {
                $this->getJson("/api/lecturer/{$path}", $this->bearer($user))->assertForbidden();
            }
        }
        $this->getJson('/api/lecturer/dashboard', $this->bearer($lecturer))->assertOk();
    }

    public function test_courses_list_has_only_courses_assigned_in_the_current_session(): void
    {
        [$lecturer, $course, $semester] = $this->classroom();
        $csc = $this->makeDepartment();

        // Assigned to someone else, assigned to nobody, and assigned to this lecturer in an OLD session.
        $other = $this->makeLecturer($csc);
        $theirs = $this->makeCourse('CSC302', $csc);
        CourseAssignment::create(['course_id' => $theirs->id, 'lecturer_id' => $other->lecturer->id, 'academic_session_id' => $semester->academic_session_id]);
        $this->makeCourse('CSC303', $csc);
        $oldSession = \App\Models\AcademicSession::create(['name' => '2025/2026', 'start_date' => '2025-09-14', 'end_date' => '2026-07-30', 'is_current' => false]);
        $old = $this->makeCourse('CSC201', $csc);
        CourseAssignment::create(['course_id' => $old->id, 'lecturer_id' => $lecturer->lecturer->id, 'academic_session_id' => $oldSession->id]);

        $response = $this->getJson('/api/lecturer/courses', $this->bearer($lecturer))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.code', 'CSC301')
            ->assertJsonPath('data.0.submissionStatus', 'draft')
            ->assertJsonStructure(['data' => [['id', 'code', 'title', 'units', 'lecturerName', 'studentCount', 'submissionStatus']]]);
        $this->assertEquals(2, $response->json('data.0.studentCount'));
    }

    public function test_dashboard_counts_the_lecturers_own_work(): void
    {
        [$lecturer, $course, $semester] = $this->classroom();
        $this->makeSubmission($course, $semester, $lecturer, 'rejected');

        $response = $this->getJson('/api/lecturer/dashboard', $this->bearer($lecturer))->assertOk();
        $this->assertSame(1, $response->json('assignedCourses'));
        $this->assertSame(2, $response->json('totalStudents'));
        $this->assertSame(0, $response->json('notStarted'));
        $this->assertSame(1, $response->json('submissionsByStatus.rejected'));
        $this->assertSame('2026/2027', $response->json('currentSession'));
        $this->assertSame('First', $response->json('currentSemester'));
    }

    public function test_class_list_shows_registered_students_without_scores_yet(): void
    {
        [$lecturer, $course, , $a] = $this->classroom();
        $csc = $this->makeDepartment();
        $this->makeStudent($csc); // not registered for the course

        $response = $this->getJson("/api/lecturer/courses/{$course->id}/students", $this->bearer($lecturer))
            ->assertOk()
            ->assertJsonPath('status', 'draft')
            ->assertJsonPath('canEdit', true)
            ->assertJsonPath('caMax', 30)
            ->assertJsonPath('examMax', 70)
            ->assertJsonCount(2, 'students');
        $this->assertNull($response->json('students.0.totalScore'));
        $this->assertSame((string) $a->student->id, $response->json('students.0.studentId'));
    }

    public function test_a_lecturer_cannot_touch_a_course_that_is_not_assigned_to_them(): void
    {
        [$lecturer, , $semester, $a] = $this->classroom();
        $csc = $this->makeDepartment();
        $other = $this->makeLecturer($csc);
        $foreign = $this->makeCourse('CSC302', $csc);
        CourseAssignment::create(['course_id' => $foreign->id, 'lecturer_id' => $other->lecturer->id, 'academic_session_id' => $semester->academic_session_id]);
        $this->register($a, $semester, $foreign);
        $unassigned = $this->makeCourse('CSC303', $csc);

        foreach ([$foreign, $unassigned] as $course) {
            $this->getJson("/api/lecturer/courses/{$course->id}/students", $this->bearer($lecturer))->assertForbidden();
            $this->getJson("/api/lecturer/courses/{$course->id}/summary", $this->bearer($lecturer))->assertForbidden();
            $this->putJson("/api/lecturer/courses/{$course->id}/results", $this->scores($a, 10, 20), $this->bearer($lecturer))->assertForbidden();
            $this->postJson("/api/lecturer/courses/{$course->id}/results/submit", [], $this->bearer($lecturer))->assertForbidden();
        }
        $this->assertSame(0, \App\Models\Result::count());
    }

    public function test_saving_a_draft_computes_total_grade_and_point_from_the_config(): void
    {
        [$lecturer, $course, , $a, $b] = $this->classroom();

        $response = $this->putJson("/api/lecturer/courses/{$course->id}/results", $this->scores($a, 24, 52, $b, 10, 25), $this->bearer($lecturer))
            ->assertOk()
            ->assertJsonPath('status', 'draft');
        $this->assertEquals(76, $response->json('students.0.totalScore'));
        $this->assertEquals(35, $response->json('students.1.totalScore'));
        $this->assertSame('A', $response->json('students.0.grade'));
        $this->assertSame('F', $response->json('students.1.grade'));
        $this->assertEquals(5, $response->json('students.0.gradePoint'));
        $this->assertEquals(0, $response->json('students.1.gradePoint'));

        $this->assertDatabaseHas('result_submissions', ['course_id' => $course->id, 'status' => 'draft']);
        $this->assertSame(1, AuditLog::where('action', 'RESULT_DRAFT_SAVED')->count());
    }

    public function test_a_partial_save_keeps_total_grade_and_point_empty(): void
    {
        [$lecturer, $course, , $a] = $this->classroom();

        $response = $this->putJson("/api/lecturer/courses/{$course->id}/results", $this->scores($a, 18, null), $this->bearer($lecturer))->assertOk();

        $this->assertEquals(18, $response->json('students.0.caScore'));
        $this->assertNull($response->json('students.0.examScore'));
        $this->assertNull($response->json('students.0.totalScore'));
        $this->assertNull($response->json('students.0.grade'));
        $this->assertNull($response->json('students.0.gradePoint'));
    }

    public function test_scores_are_checked_against_the_config(): void
    {
        [$lecturer, $course, , $a] = $this->classroom();
        $headers = $this->bearer($lecturer);

        $this->putJson("/api/lecturer/courses/{$course->id}/results", $this->scores($a, 31, 50), $headers)
            ->assertStatus(422)->assertJsonValidationErrors(['scores.0.caScore']);
        $this->putJson("/api/lecturer/courses/{$course->id}/results", $this->scores($a, 20, 71), $headers)
            ->assertStatus(422)->assertJsonValidationErrors(['scores.0.examScore']);
        $this->putJson("/api/lecturer/courses/{$course->id}/results", $this->scores($a, -1, 50), $headers)
            ->assertStatus(422)->assertJsonValidationErrors(['scores.0.caScore']);
        $this->putJson("/api/lecturer/courses/{$course->id}/results", $this->scores($a, 20.555, 50), $headers)
            ->assertStatus(422)->assertJsonValidationErrors(['scores.0.caScore']);
        $this->putJson("/api/lecturer/courses/{$course->id}/results", ['scores' => []], $headers)
            ->assertStatus(422)->assertJsonValidationErrors(['scores']);

        // The limits are read from the config, so changing it changes the rule.
        config(['grading.ca_max' => 40]);
        $this->putJson("/api/lecturer/courses/{$course->id}/results", $this->scores($a, 35, 50), $headers)->assertOk();
        // Only the one valid save was written to the audit log.
        $this->assertSame(1, AuditLog::where('action', 'RESULT_DRAFT_SAVED')->count());
    }

    public function test_a_student_who_is_not_in_the_class_is_refused(): void
    {
        [$lecturer, $course] = $this->classroom();
        $outsider = $this->makeStudent($this->makeDepartment());

        $this->putJson("/api/lecturer/courses/{$course->id}/results", $this->scores($outsider, 10, 20), $this->bearer($lecturer))
            ->assertStatus(422)->assertJsonValidationErrors(['scores.0.studentId']);
        $this->assertSame(0, \App\Models\Result::count());
    }

    public function test_scores_can_only_be_entered_in_the_current_semester(): void
    {
        [$lecturer, $course, , $a] = $this->classroom();
        $course->update(['semester' => 'Second']);

        $this->putJson("/api/lecturer/courses/{$course->id}/results", $this->scores($a, 10, 20), $this->bearer($lecturer))->assertStatus(422);
        $this->postJson("/api/lecturer/courses/{$course->id}/results/submit", [], $this->bearer($lecturer))->assertStatus(422);
    }

    public function test_submit_needs_both_scores_for_every_student(): void
    {
        [$lecturer, $course, , $a, $b] = $this->classroom();
        $headers = $this->bearer($lecturer);

        $this->postJson("/api/lecturer/courses/{$course->id}/results/submit", [], $headers)->assertStatus(422); // nothing saved yet

        $this->putJson("/api/lecturer/courses/{$course->id}/results", $this->scores($a, 20, 50, $b, 15, null), $headers)->assertOk();
        $this->postJson("/api/lecturer/courses/{$course->id}/results/submit", [], $headers)->assertStatus(422);
        $this->assertDatabaseHas('result_submissions', ['course_id' => $course->id, 'status' => 'draft']);
        $this->assertSame(0, AuditLog::where('action', 'RESULT_SUBMITTED')->count());
    }

    public function test_the_full_flow_save_submit_lock_approve(): void
    {
        [$lecturer, $course, , $a, $b] = $this->classroom();
        $csc = $this->makeDepartment();
        $admin = $this->makeDeptAdmin($csc);

        $this->putJson("/api/lecturer/courses/{$course->id}/results", $this->scores($a, 20, 50, $b, 15, 30), $this->bearer($lecturer))->assertOk();
        $this->postJson("/api/lecturer/courses/{$course->id}/results/submit", [], $this->bearer($lecturer))
            ->assertOk()->assertJsonPath('status', 'submitted')->assertJsonPath('canEdit', false);
        $this->assertSame(1, AuditLog::where('action', 'RESULT_SUBMITTED')->count());
        $submissionId = \App\Models\ResultSubmission::firstOrFail()->id;

        // Locked while submitted: no save, no second submit.
        $this->putJson("/api/lecturer/courses/{$course->id}/results", $this->scores($a, 25, 50), $this->bearer($lecturer))->assertStatus(422);
        $this->postJson("/api/lecturer/courses/{$course->id}/results/submit", [], $this->bearer($lecturer))->assertStatus(422);

        // The department admin approves, and the results stay locked.
        $this->postJson("/api/dept/submissions/{$submissionId}/approve", [], $this->bearer($admin))->assertOk();
        $this->putJson("/api/lecturer/courses/{$course->id}/results", $this->scores($a, 25, 50), $this->bearer($lecturer))->assertStatus(422);
        $this->getJson("/api/lecturer/courses/{$course->id}/students", $this->bearer($lecturer))
            ->assertOk()->assertJsonPath('status', 'approved')->assertJsonPath('canEdit', false);
    }

    public function test_rejected_results_can_be_corrected_and_resubmitted(): void
    {
        $this->assertCorrectableAndResubmittable('rejected');
    }

    public function test_reopened_results_can_be_corrected_and_resubmitted(): void
    {
        $this->assertCorrectableAndResubmittable('reopened');
    }

    private function assertCorrectableAndResubmittable(string $status): void
    {
        [$lecturer, $course, $semester, $a, $b] = $this->classroom();
        $submission = $this->makeSubmission($course, $semester, $lecturer, $status);
        $submission->update(['feedback' => 'Please check the exam scores.']);

        $this->getJson("/api/lecturer/courses/{$course->id}/students", $this->bearer($lecturer))
            ->assertOk()->assertJsonPath('status', $status)->assertJsonPath('canEdit', true)
            ->assertJsonPath('feedback', 'Please check the exam scores.');

        $this->putJson("/api/lecturer/courses/{$course->id}/results", $this->scores($a, 20, 50, $b, 15, 30), $this->bearer($lecturer))->assertOk();
        $this->postJson("/api/lecturer/courses/{$course->id}/results/submit", [], $this->bearer($lecturer))
            ->assertOk()->assertJsonPath('status', 'submitted')->assertJsonPath('feedback', null);
        $this->assertDatabaseHas('result_submissions', ['id' => $submission->id, 'status' => 'submitted']);
    }

    public function test_course_summary_has_statistics_and_the_grade_distribution(): void
    {
        [$lecturer, $course, , $a, $b] = $this->classroom();
        $this->putJson("/api/lecturer/courses/{$course->id}/results", $this->scores($a, 20, 50, $b, 10, 25), $this->bearer($lecturer))->assertOk();

        $response = $this->getJson("/api/lecturer/courses/{$course->id}/summary", $this->bearer($lecturer))->assertOk();
        $this->assertSame(2, $response->json('totalStudents'));
        $this->assertSame(2, $response->json('scoredStudents'));
        $this->assertEquals(52.5, $response->json('average'));
        $this->assertEquals(70, $response->json('highest'));
        $this->assertEquals(35, $response->json('lowest'));
        $this->assertEquals(50, $response->json('passRate'));
        $this->assertSame(1, $response->json('gradeDistribution.A'));
        $this->assertSame(1, $response->json('gradeDistribution.F'));
        $this->assertSame(0, $response->json('gradeDistribution.C'));
    }

    public function test_submissions_list_has_only_own_submissions_with_feedback(): void
    {
        [$lecturer, $course, $semester] = $this->classroom();
        $csc = $this->makeDepartment();
        $mine = $this->makeSubmission($course, $semester, $lecturer, 'rejected');
        $mine->update(['feedback' => 'Exam scores look swapped.']);

        $other = $this->makeLecturer($csc);
        $this->makeSubmission($this->makeCourse('CSC302', $csc), $semester, $other, 'submitted');

        $this->getJson('/api/lecturer/submissions', $this->bearer($lecturer))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.courseCode', 'CSC301')
            ->assertJsonPath('data.0.status', 'rejected')
            ->assertJsonPath('data.0.feedback', 'Exam scores look swapped.');
        $this->getJson('/api/lecturer/submissions?status=approved', $this->bearer($lecturer))
            ->assertOk()->assertJsonPath('meta.total', 0);
    }
}
