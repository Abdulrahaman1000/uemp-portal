<?php

namespace Tests\Feature\Student;

use App\Models\AcademicSession;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\Registration;
use App\Models\Result;
use App\Models\Semester;
use App\Models\User;
use Tests\Concerns\CreatesDemoData;
use Tests\Concerns\CreatesWorkflowData;
use Tests\TestCase;

class StudentApiTest extends TestCase
{
    use CreatesDemoData;
    use CreatesWorkflowData;

    private function approve(Course $course, Semester $semester, User $lecturer, User $student, float $ca, float $exam, string $grade, float $point, string $status = 'approved'): void
    {
        $submission = $this->makeSubmission($course, $semester, $lecturer, $status);
        Result::create([
            'result_submission_id' => $submission->id,
            'student_id' => $student->student->id,
            'ca_score' => $ca, 'exam_score' => $exam, 'total_score' => $ca + $exam,
            'grade' => $grade, 'grade_point' => $point,
        ]);
    }

    public function test_only_students_can_reach_student_endpoints(): void
    {
        $csc = $this->makeDepartment();
        $paths = ['profile', 'available-courses', 'registrations', 'results', 'academic-history'];

        foreach ($paths as $path) {
            $this->app['auth']->forgetGuards();
            $this->getJson("/api/student/{$path}")->assertUnauthorized();
        }
        foreach ($paths as $path) {
            foreach ([$this->makeLecturer($csc), $this->makeDeptAdmin($csc), $this->makeSuperAdmin()] as $user) {
                $this->getJson("/api/student/{$path}", $this->bearer($user))->assertForbidden();
            }
        }
        $this->postJson('/api/student/registrations', ['courseIds' => [1]], $this->bearer($this->makeLecturer($csc)))->assertForbidden();
    }

    public function test_profile_shows_own_details_and_allows_only_a_limited_edit(): void
    {
        $csc = $this->makeDepartment();
        $user = $this->makeStudent($csc);
        $headers = $this->bearer($user);

        $this->getJson('/api/student/profile', $headers)
            ->assertOk()
            ->assertJsonPath('matricNo', $user->student->matric_no)
            ->assertJsonPath('departmentName', 'Computer Science')
            ->assertJsonPath('level', 300);

        $this->putJson('/api/student/profile', [
            'phone' => '+234 801 234 5678', 'address' => '12 Campus Road', 'dateOfBirth' => '2001-04-09',
            'matricNo' => 'HACK/1', 'level' => 400, 'departmentId' => 99, 'status' => 'graduated',
        ], $headers)
            ->assertOk()
            ->assertJsonPath('phone', '+234 801 234 5678')
            ->assertJsonPath('address', '12 Campus Road')
            ->assertJsonPath('dateOfBirth', '2001-04-09')
            ->assertJsonPath('level', 300);

        $student = $user->student->fresh();
        $this->assertNotSame('HACK/1', $student->matric_no);
        $this->assertSame(300, $student->level);
        $this->assertNull($student->graduated_at);
        $this->assertSame(1, AuditLog::where('action', 'PROFILE_UPDATED')->count());

        $this->putJson('/api/student/profile', ['phone' => 'abc'], $headers)->assertStatus(422)->assertJsonValidationErrors(['phone']);
        $this->putJson('/api/student/profile', ['dateOfBirth' => '2999-01-01'], $headers)->assertStatus(422)->assertJsonValidationErrors(['dateOfBirth']);
    }

    public function test_available_courses_match_department_program_level_and_semester_only(): void
    {
        $csc = $this->makeDepartment('CSC', 'Computer Science');
        $mth = $this->makeDepartment('MTH', 'Mathematics');
        $semester = $this->currentSemester();
        $student = $this->makeStudent($csc);

        $ok = $this->makeCourse('CSC301', $csc);
        $this->makeCourse('MTH301', $mth);                                    // other department
        $this->makeCourse('CSC201', $csc)->update(['level' => 200]);          // other level
        $this->makeCourse('CSC312', $csc)->update(['semester' => 'Second']);  // other semester

        $registration = Registration::create(['student_id' => $student->student->id, 'semester_id' => $semester->id]);
        $registration->courses()->attach($ok->id);

        $response = $this->getJson('/api/student/available-courses', $this->bearer($student))->assertOk()->assertJsonPath('meta.total', 1);
        $this->assertSame('CSC301', $response->json('data.0.code'));
        $this->assertTrue($response->json('data.0.registered'));
        $this->assertSame('2026/2027', $response->json('sessionName'));
    }

    public function test_registering_courses_creates_one_registration_and_is_audited(): void
    {
        $csc = $this->makeDepartment();
        $this->currentSemester();
        $student = $this->makeStudent($csc);
        $a = $this->makeCourse('CSC301', $csc);
        $b = $this->makeCourse('CSC305', $csc);

        $response = $this->postJson('/api/student/registrations', ['courseIds' => [(string) $a->id, (string) $b->id]], $this->bearer($student))
            ->assertCreated()
            ->assertJsonCount(2, 'courses');
        $this->assertSame(6, $response->json('totalUnits'));
        $this->assertSame('First', $response->json('semester'));
        $this->assertSame(1, Registration::count());
        $this->assertSame(1, AuditLog::where('action', 'COURSE_REGISTERED')->count());
        $this->assertSame($student->student->matric_no, $response->json('matricNo'));

        $this->getJson('/api/student/registrations', $this->bearer($student))
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.totalUnits', 6);
    }

    public function test_courses_outside_the_students_department_program_level_or_semester_are_refused(): void
    {
        $csc = $this->makeDepartment('CSC', 'Computer Science');
        $mth = $this->makeDepartment('MTH', 'Mathematics');
        $this->currentSemester();
        $student = $this->makeStudent($csc);
        $headers = $this->bearer($student);

        $otherDept = $this->makeCourse('MTH301', $mth);
        $otherLevel = $this->makeCourse('CSC201', $csc);
        $otherLevel->update(['level' => 200]);
        $otherSemester = $this->makeCourse('CSC312', $csc);
        $otherSemester->update(['semester' => 'Second']);
        $otherProgram = $this->makeCourse('CSC399', $csc);
        $otherProgram->update(['program_id' => \App\Models\Program::create(['department_id' => $csc->id, 'name' => 'Cyber Security', 'award' => 'B.Sc.', 'duration_years' => 4])->id]);

        foreach ([$otherDept, $otherLevel, $otherSemester, $otherProgram] as $course) {
            $this->postJson('/api/student/registrations', ['courseIds' => [$course->id]], $headers)
                ->assertStatus(422)->assertJsonValidationErrors(['courseIds.0']);
        }
        $this->postJson('/api/student/registrations', ['courseIds' => [99999]], $headers)->assertStatus(422)->assertJsonValidationErrors(['courseIds.0']);
        $this->postJson('/api/student/registrations', ['courseIds' => []], $headers)->assertStatus(422)->assertJsonValidationErrors(['courseIds']);

        $this->assertSame(0, Registration::count());
        $this->assertSame(0, AuditLog::where('action', 'COURSE_REGISTERED')->count());
    }

    public function test_a_course_cannot_be_registered_twice_but_more_courses_can_be_added_later(): void
    {
        $csc = $this->makeDepartment();
        $this->currentSemester();
        $student = $this->makeStudent($csc);
        $a = $this->makeCourse('CSC301', $csc);
        $b = $this->makeCourse('CSC305', $csc);
        $c = $this->makeCourse('CSC311', $csc);

        $this->postJson('/api/student/registrations', ['courseIds' => [$a->id, $b->id]], $this->bearer($student))->assertCreated();

        // Same course again, alone or together with a new one: refused, and nothing is added.
        $this->postJson('/api/student/registrations', ['courseIds' => [$a->id]], $this->bearer($student))
            ->assertStatus(422)->assertJsonValidationErrors(['courseIds.0']);
        $this->postJson('/api/student/registrations', ['courseIds' => [$c->id, $a->id]], $this->bearer($student))
            ->assertStatus(422)->assertJsonValidationErrors(['courseIds.1']);
        $this->assertDatabaseCount('registration_items', 2);

        // Adding a new course later goes into the same registration.
        $this->postJson('/api/student/registrations', ['courseIds' => [$c->id]], $this->bearer($student))
            ->assertCreated()->assertJsonCount(3, 'courses');
        $this->assertSame(1, Registration::count());
        $this->assertDatabaseCount('registration_items', 3);
    }

    public function test_a_graduated_student_cannot_register(): void
    {
        $csc = $this->makeDepartment();
        $this->currentSemester();
        $student = $this->makeStudent($csc);
        $student->student->update(['graduated_at' => now()]);
        $course = $this->makeCourse('CSC301', $csc);

        $this->postJson('/api/student/registrations', ['courseIds' => [$course->id]], $this->bearer($student))->assertStatus(422);
        $this->assertSame(0, Registration::count());
    }

    public function test_students_see_only_approved_results_and_only_their_own(): void
    {
        $csc = $this->makeDepartment();
        $semester = $this->currentSemester();
        $lecturer = $this->makeLecturer($csc);
        $student = $this->makeStudent($csc);
        $other = $this->makeStudent($csc);

        $this->approve($this->makeCourse('CSC301', $csc), $semester, $lecturer, $student, 24, 52, 'A', 5);
        foreach (['draft' => 'CSC302', 'submitted' => 'CSC303', 'rejected' => 'CSC304', 'reopened' => 'CSC305'] as $status => $code) {
            $this->approve($this->makeCourse($code, $csc), $semester, $lecturer, $student, 20, 40, 'B', 4, $status);
        }
        $this->approve($this->makeCourse('CSC306', $csc), $semester, $lecturer, $other, 10, 10, 'F', 0);

        $response = $this->getJson('/api/student/results', $this->bearer($student))
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.courseCode', 'CSC301');
        $this->assertSame('A', $response->json('data.0.grade'));
        $this->assertEquals(76, $response->json('data.0.totalScore'));
        $this->assertEquals(5, $response->json('data.0.gradePoint'));

        // Once the department reopens an approved result, the student no longer sees it.
        \App\Models\ResultSubmission::query()->where('status', 'approved')->where('course_id', Course::where('code', 'CSC301')->value('id'))->update(['status' => 'reopened']);
        $this->getJson('/api/student/results', $this->bearer($student))->assertOk()->assertJsonPath('meta.total', 0);
    }

    public function test_academic_history_has_semester_gpa_and_a_unit_weighted_cgpa(): void
    {
        $csc = $this->makeDepartment();
        $current = $this->currentSemester();
        $oldSession = AcademicSession::create(['name' => '2025/2026', 'start_date' => '2025-09-14', 'end_date' => '2026-07-30', 'is_current' => false]);
        $old = Semester::create(['academic_session_id' => $oldSession->id, 'name' => 'Second', 'start_date' => '2026-02-01', 'end_date' => '2026-06-30', 'is_current' => false]);
        $lecturer = $this->makeLecturer($csc);
        $student = $this->makeStudent($csc);

        $five = $this->makeCourse('CSC301', $csc);  // 3 units, A (5)
        $two = $this->makeCourse('CSC302', $csc);
        $two->update(['units' => 2]);               // 2 units, D (2)
        $zero = $this->makeCourse('CSC206', $csc);  // 3 units, F (0), previous semester
        $this->approve($five, $current, $lecturer, $student, 24, 52, 'A', 5);
        $this->approve($two, $current, $lecturer, $student, 15, 30, 'D', 2);
        $this->approve($zero, $old, $lecturer, $student, 10, 20, 'F', 0);

        $response = $this->getJson('/api/student/academic-history', $this->bearer($student))->assertOk();

        // CGPA = (5x3 + 2x2 + 0x3) / 8 = 2.375 -> 2.38
        $this->assertEquals(2.38, $response->json('cgpa'));
        $this->assertSame(8, $response->json('totalUnits'));
        $this->assertSame(3, $response->json('totalCourses'));
        $this->assertCount(2, $response->json('semesters'));
        $this->assertSame('2025/2026', $response->json('semesters.0.sessionName'));   // oldest first
        $this->assertEquals(0, $response->json('semesters.0.gpa'));
        $this->assertEquals(3.8, $response->json('semesters.1.gpa'));                  // (15 + 4) / 5
        $this->assertSame($student->student->matric_no, $response->json('matricNo'));
    }

    public function test_history_is_empty_without_approved_results(): void
    {
        $csc = $this->makeDepartment();
        $student = $this->makeStudent($csc);

        $response = $this->getJson('/api/student/academic-history', $this->bearer($student))->assertOk();
        $this->assertNull($response->json('cgpa'));
        $this->assertSame([], $response->json('semesters'));
    }
}
