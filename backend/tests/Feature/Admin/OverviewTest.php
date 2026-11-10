<?php

namespace Tests\Feature\Admin;

use App\Models\AcademicSession;
use App\Models\Course;
use App\Models\Department;
use App\Models\Program;
use App\Models\Registration;
use App\Models\ResultSubmission;
use App\Models\Semester;
use Tests\Concerns\CreatesDemoData;
use Tests\TestCase;

class OverviewTest extends TestCase
{
    use CreatesDemoData;

    private function makeSession(string $name, bool $current): AcademicSession
    {
        [$from] = explode('/', $name);

        return AcademicSession::create([
            'name' => $name,
            'start_date' => $from.'-09-14',
            'end_date' => ((int) $from + 1).'-07-30',
            'is_current' => $current,
        ]);
    }

    private function makeSemester(AcademicSession $session, string $name, bool $current): Semester
    {
        return Semester::create([
            'academic_session_id' => $session->id,
            'name' => $name,
            'start_date' => '2026-09-14',
            'end_date' => '2027-01-29',
            'is_current' => $current,
        ]);
    }

    private function makeSubmission(string $status, Semester $semester, string $code, Department $department): ResultSubmission
    {
        $program = Program::firstOrCreate(
            ['department_id' => $department->id, 'name' => $department->name],
            ['award' => 'B.Sc.', 'duration_years' => 4],
        );
        $course = Course::create([
            'code' => $code,
            'title' => 'Course '.$code,
            'units' => 3,
            'level' => 300,
            'semester' => 'First',
            'type' => 'core',
            'department_id' => $department->id,
            'program_id' => $program->id,
        ]);
        $lecturer = $this->makeLecturer($department)->lecturer;

        return ResultSubmission::create([
            'course_id' => $course->id,
            'semester_id' => $semester->id,
            'lecturer_id' => $lecturer->id,
            'status' => $status,
        ]);
    }

    public function test_dashboard_returns_university_wide_counts(): void
    {
        $department = $this->makeDepartment();
        $this->makeStudent($department);
        $this->makeStudent($department);
        $this->makeLecturer($department);
        $admin = $this->makeSuperAdmin();

        $this->getJson('/api/admin/dashboard', $this->bearer($admin))
            ->assertOk()
            ->assertExactJson([
                'totalStudents' => 2,
                'totalLecturers' => 1,
                'totalFaculties' => 1,
                'totalDepartments' => 1,
                'activePrograms' => 1,
                'pendingSubmissions' => 0,
                'currentSession' => 'None',
                'currentSemester' => 'First',
            ]);
    }

    public function test_dashboard_shows_the_current_session_and_semester(): void
    {
        $session = $this->makeSession('2026/2027', true);
        $this->makeSemester($session, 'First', false);
        $this->makeSemester($session, 'Second', true);
        $admin = $this->makeSuperAdmin();

        $this->getJson('/api/admin/dashboard', $this->bearer($admin))
            ->assertOk()
            ->assertJsonPath('currentSession', '2026/2027')
            ->assertJsonPath('currentSemester', 'Second');
    }

    public function test_dashboard_counts_only_submitted_results_as_pending(): void
    {
        $department = $this->makeDepartment();
        $semester = $this->makeSemester($this->makeSession('2026/2027', true), 'First', true);
        $this->makeSubmission('submitted', $semester, 'CSC301', $department);
        $this->makeSubmission('submitted', $semester, 'CSC302', $department);
        $this->makeSubmission('approved', $semester, 'CSC303', $department);
        $this->makeSubmission('draft', $semester, 'CSC304', $department);
        $admin = $this->makeSuperAdmin();

        $this->getJson('/api/admin/dashboard', $this->bearer($admin))
            ->assertOk()
            ->assertJsonPath('pendingSubmissions', 2);
    }

    public function test_grading_scale_comes_from_the_config_file(): void
    {
        $admin = $this->makeSuperAdmin();

        $response = $this->getJson('/api/admin/grading-scale', $this->bearer($admin))
            ->assertOk()
            ->assertJsonPath('caMax', 30)
            ->assertJsonPath('examMax', 70)
            ->assertJsonCount(6, 'bands')
            ->assertJsonPath('bands.0', ['min' => 70, 'max' => 100, 'grade' => 'A', 'point' => 5])
            ->assertJsonPath('bands.5', ['min' => 0, 'max' => 39, 'grade' => 'F', 'point' => 0]);

        $this->assertStringContainsString('sum(grade point x course units)', $response->json('gpaFormula'));
    }

    public function test_grading_scale_has_no_gaps_and_covers_zero_to_one_hundred(): void
    {
        $bands = config('grading.bands');

        $this->assertSame(100, $bands[0]['max']);
        $this->assertSame(0, end($bands)['min']);
        $this->assertSame(100, config('grading.ca_max') + config('grading.exam_max'));

        for ($i = 1; $i < count($bands); $i++) {
            $this->assertSame($bands[$i - 1]['min'] - 1, $bands[$i]['max'], 'There is a gap or overlap between two grade bands.');
        }
    }

    public function test_roles_lists_the_four_roles_with_user_counts(): void
    {
        $admin = $this->makeSuperAdmin();
        $this->makeStudent();
        $this->makeStudent();

        $response = $this->getJson('/api/admin/roles', $this->bearer($admin))
            ->assertOk()
            ->assertJsonCount(4)
            ->assertJsonPath('0.role', 'student')
            ->assertJsonPath('0.userCount', 2)
            ->assertJsonPath('1.role', 'lecturer')
            ->assertJsonPath('1.userCount', 0)
            ->assertJsonPath('2.role', 'dept_admin')
            ->assertJsonPath('2.label', 'Department Admin')
            ->assertJsonPath('3.role', 'super_admin')
            ->assertJsonPath('3.label', 'Super Admin')
            ->assertJsonPath('3.userCount', 1);

        $this->assertNotEmpty($response->json('0.permissions'));
        $this->assertNotSame('', $response->json('3.description'));
    }

    public function test_students_report_groups_students_by_department(): void
    {
        $csc = $this->makeDepartment('CSC', 'Computer Science');
        $eco = $this->makeDepartment('ECO', 'Economics');
        $this->makeStudent($csc);
        $this->makeStudent($csc);
        $this->makeStudent($eco);
        $admin = $this->makeSuperAdmin();

        $this->getJson('/api/admin/reports/students', $this->bearer($admin))
            ->assertOk()
            ->assertExactJson([
                ['label' => 'Computer Science', 'value' => 2],
                ['label' => 'Economics', 'value' => 1],
            ]);
    }

    public function test_registrations_report_counts_only_the_current_session(): void
    {
        $csc = $this->makeDepartment('CSC', 'Computer Science');
        $eco = $this->makeDepartment('ECO', 'Economics');
        $current = $this->makeSemester($this->makeSession('2026/2027', true), 'First', true);
        $old = $this->makeSemester($this->makeSession('2025/2026', false), 'First', false);

        $student = $this->makeStudent($csc)->student;
        $otherStudent = $this->makeStudent($csc)->student;
        Registration::create(['student_id' => $student->id, 'semester_id' => $current->id]);
        Registration::create(['student_id' => $otherStudent->id, 'semester_id' => $old->id]);
        $admin = $this->makeSuperAdmin();

        $this->getJson('/api/admin/reports/registrations', $this->bearer($admin))
            ->assertOk()
            ->assertExactJson([
                ['label' => 'Computer Science', 'value' => 1],
                ['label' => 'Economics', 'value' => 0],
            ]);
    }

    public function test_results_report_lists_every_status_even_with_zero(): void
    {
        $department = $this->makeDepartment();
        $semester = $this->makeSemester($this->makeSession('2026/2027', true), 'First', true);
        $this->makeSubmission('approved', $semester, 'CSC301', $department);
        $this->makeSubmission('approved', $semester, 'CSC302', $department);
        $this->makeSubmission('rejected', $semester, 'CSC303', $department);
        $admin = $this->makeSuperAdmin();

        $this->getJson('/api/admin/reports/results', $this->bearer($admin))
            ->assertOk()
            ->assertExactJson([
                ['label' => 'draft', 'value' => 0],
                ['label' => 'submitted', 'value' => 0],
                ['label' => 'approved', 'value' => 2],
                ['label' => 'rejected', 'value' => 1],
                ['label' => 'reopened', 'value' => 0],
            ]);
    }

    public function test_unknown_report_type_is_404(): void
    {
        $admin = $this->makeSuperAdmin();

        $this->getJson('/api/admin/reports/payroll', $this->bearer($admin))->assertStatus(404);
    }

    public function test_only_super_admin_can_use_the_overview_endpoints(): void
    {
        $urls = ['/api/admin/dashboard', '/api/admin/grading-scale', '/api/admin/roles', '/api/admin/reports/students'];

        foreach ($urls as $url) {
            foreach ([$this->makeDeptAdmin(), $this->makeLecturer(), $this->makeStudent()] as $user) {
                $this->getJson($url, $this->bearer($user))->assertStatus(403);
            }
        }
    }

    public function test_overview_endpoints_need_a_token(): void
    {
        foreach (['/api/admin/dashboard', '/api/admin/grading-scale', '/api/admin/roles', '/api/admin/reports/students'] as $url) {
            $this->getJson($url)->assertStatus(401);
        }
    }
}
