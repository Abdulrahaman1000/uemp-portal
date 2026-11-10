<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\Program;
use App\Models\User;
use Tests\Concerns\CreatesDemoData;
use Tests\TestCase;

/** T2.5b: faculties, departments and programs (super admin CRUD). */
class AcademicStructureTest extends TestCase
{
    use CreatesDemoData;

    private function admin(): array
    {
        return $this->bearer($this->makeSuperAdmin());
    }

    private function makeFaculty(string $name = 'Faculty of Arts', string $code = 'ART'): Faculty
    {
        return Faculty::create(['name' => $name, 'code' => $code, 'dean' => 'Prof. Dean']);
    }

    private function lastLog(string $action): ?AuditLog
    {
        return AuditLog::where('action', $action)->latest('id')->first();
    }

    // ---------- access control ----------

    public function test_only_super_admin_can_reach_these_endpoints(): void
    {
        $faculty = $this->makeFaculty();
        $users = [
            $this->makeDeptAdmin(),
            $this->makeLecturer(),
            $this->makeStudent(),
        ];
        $facultiesBefore = Faculty::count(); // the helpers above also create the default faculty

        foreach ($users as $user) {
            $headers = $this->bearer($user);
            foreach (['faculties', 'departments', 'programs'] as $resource) {
                $this->getJson("/api/admin/{$resource}", $headers)->assertForbidden();
                $this->postJson("/api/admin/{$resource}", [], $headers)->assertForbidden();
            }
            $this->putJson("/api/admin/faculties/{$faculty->id}", ['name' => 'Hacked', 'code' => 'HAK'], $headers)->assertForbidden();
            $this->deleteJson("/api/admin/faculties/{$faculty->id}", [], $headers)->assertForbidden();
        }

        $this->assertSame('Faculty of Arts', $faculty->fresh()->name);
        $this->assertSame($facultiesBefore, Faculty::count());
    }

    public function test_requests_without_a_token_are_401(): void
    {
        $this->getJson('/api/admin/faculties')->assertUnauthorized();
        $this->postJson('/api/admin/programs', [])->assertUnauthorized();
    }

    // ---------- faculties ----------

    public function test_faculty_list_has_the_frontend_shape_and_counts(): void
    {
        $department = $this->makeDepartment('CSC', 'Computer Science'); // creates the SCI faculty
        $this->makeFaculty();

        $response = $this->getJson('/api/admin/faculties', $this->admin())->assertOk();

        $response->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 1);

        $science = collect($response->json('data'))->firstWhere('code', 'SCI');
        $this->assertSame([
            'id' => (string) $department->faculty_id,
            'name' => 'Faculty of Science',
            'code' => 'SCI',
            'dean' => 'Prof. Harris',
            'departmentCount' => 1,
        ], $science);
        $this->assertSame(0, collect($response->json('data'))->firstWhere('code', 'ART')['departmentCount']);
    }

    public function test_faculty_search_accepts_search_and_q(): void
    {
        $this->makeFaculty('Faculty of Arts', 'ART');
        $this->makeFaculty('Faculty of Law', 'LAW');
        $headers = $this->admin();

        $this->getJson('/api/admin/faculties?search=law', $headers)->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.code', 'LAW');
        $this->getJson('/api/admin/faculties?q=ART', $headers)->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.code', 'ART');
        $this->getJson('/api/admin/faculties?search=100%25', $headers)->assertJsonPath('meta.total', 0); // % is literal, not a wildcard
    }

    public function test_super_admin_can_create_a_faculty_and_it_is_audited(): void
    {
        $admin = $this->makeSuperAdmin();

        $this->postJson('/api/admin/faculties', ['name' => '  Faculty of Law ', 'code' => 'law', 'dean' => 'Prof. Ade'], $this->bearer($admin))
            ->assertCreated()
            ->assertJsonPath('name', 'Faculty of Law')
            ->assertJsonPath('code', 'LAW')
            ->assertJsonPath('departmentCount', 0)
            ->assertJsonPath('id', (string) Faculty::where('code', 'LAW')->value('id'));

        $log = $this->lastLog('RECORD_CREATED');
        $this->assertNotNull($log);
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame('Faculty: Faculty of Law', $log->entity);
        $this->assertSame(Faculty::class, $log->auditable_type);
    }

    public function test_faculty_dean_is_optional_and_returned_as_empty_text(): void
    {
        $this->postJson('/api/admin/faculties', ['name' => 'Faculty of Law', 'code' => 'LAW', 'dean' => null], $this->admin())
            ->assertCreated()
            ->assertJsonPath('dean', '');
    }

    public function test_faculty_validation_errors_use_camel_case_keys(): void
    {
        $this->postJson('/api/admin/faculties', [], $this->admin())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'code']);

        $this->postJson('/api/admin/faculties', ['name' => 'X', 'code' => 'WAYTOOLONGCODE'], $this->admin())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);

        $this->assertSame(0, Faculty::count());
    }

    public function test_duplicate_faculty_name_or_code_is_rejected_ignoring_case(): void
    {
        $this->makeFaculty('Faculty of Arts', 'ART');
        $headers = $this->admin();

        $this->postJson('/api/admin/faculties', ['name' => 'faculty of arts', 'code' => 'NEW'], $headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name'])
            ->assertJsonPath('errors.name.0', 'A faculty with this name already exists.');

        $this->postJson('/api/admin/faculties', ['name' => 'Another', 'code' => 'art'], $headers)
            ->assertUnprocessable()
            ->assertJsonPath('errors.code.0', 'A faculty with this code already exists.');

        $this->assertSame(1, Faculty::count());
    }

    public function test_faculty_update_keeps_its_own_code_and_name_and_logs_the_change(): void
    {
        $faculty = $this->makeFaculty();

        $this->putJson("/api/admin/faculties/{$faculty->id}", ['name' => 'Faculty of Arts', 'code' => 'ART', 'dean' => 'Prof. New'], $this->admin())
            ->assertOk()
            ->assertJsonPath('dean', 'Prof. New');

        $log = $this->lastLog('RECORD_UPDATED');
        $this->assertNotNull($log);
        $this->assertSame(['dean' => ['from' => 'Prof. Dean', 'to' => 'Prof. New']], $log->metadata['changes']);
    }

    public function test_faculty_update_with_no_change_writes_no_audit_row(): void
    {
        $faculty = $this->makeFaculty();

        $this->putJson("/api/admin/faculties/{$faculty->id}", ['name' => 'Faculty of Arts', 'code' => 'ART', 'dean' => 'Prof. Dean'], $this->admin())->assertOk();

        $this->assertNull($this->lastLog('RECORD_UPDATED'));
    }

    public function test_faculty_delete_works_when_empty_and_is_audited(): void
    {
        $faculty = $this->makeFaculty();

        $this->deleteJson("/api/admin/faculties/{$faculty->id}", [], $this->admin())->assertNoContent();

        $this->assertNull(Faculty::find($faculty->id));
        $this->assertNotNull($this->lastLog('RECORD_DELETED'));
    }

    public function test_faculty_with_departments_cannot_be_deleted(): void
    {
        $department = $this->makeDepartment();

        $this->deleteJson("/api/admin/faculties/{$department->faculty_id}", [], $this->admin())
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Cannot delete "Faculty of Science": it still has 1 department.');

        $this->assertNotNull(Faculty::find($department->faculty_id));
        $this->assertNull($this->lastLog('RECORD_DELETED'));
    }

    public function test_unknown_faculty_is_404(): void
    {
        $this->getJson('/api/admin/faculties/9999', $this->admin())->assertNotFound();
        $this->deleteJson('/api/admin/faculties/9999', [], $this->admin())->assertNotFound();
    }

    // ---------- departments ----------

    public function test_department_list_has_the_frontend_shape(): void
    {
        $department = $this->makeDepartment('CSC', 'Computer Science');
        Program::create(['department_id' => $department->id, 'name' => 'Computer Science', 'award' => 'B.Sc.', 'duration_years' => 4]);

        $this->getJson('/api/admin/departments', $this->admin())
            ->assertOk()
            ->assertJsonPath('data.0', [
                'id' => (string) $department->id,
                'name' => 'Computer Science',
                'code' => 'CSC',
                'facultyId' => (string) $department->faculty_id,
                'facultyName' => 'Faculty of Science',
                'head' => 'Head CSC',
                'programCount' => 1,
            ]);
    }

    public function test_departments_can_be_filtered_by_faculty(): void
    {
        $this->makeDepartment('CSC', 'Computer Science');
        $arts = $this->makeFaculty();
        Department::create(['faculty_id' => $arts->id, 'name' => 'History', 'code' => 'HIS']);

        $this->getJson("/api/admin/departments?facultyId={$arts->id}", $this->admin())
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.code', 'HIS');
    }

    public function test_super_admin_can_create_update_and_delete_a_department(): void
    {
        $faculty = $this->makeFaculty();
        $headers = $this->admin();

        $id = $this->postJson('/api/admin/departments', [
            'facultyId' => (string) $faculty->id, // the frontend sends ids as strings
            'name' => 'History',
            'code' => 'his',
            'head' => 'Dr. Past',
        ], $headers)
            ->assertCreated()
            ->assertJsonPath('code', 'HIS')
            ->assertJsonPath('facultyName', 'Faculty of Arts')
            ->assertJsonPath('programCount', 0)
            ->json('id');

        $this->putJson("/api/admin/departments/{$id}", ['facultyId' => $faculty->id, 'name' => 'History', 'code' => 'HIS', 'head' => 'Dr. Future'], $headers)
            ->assertOk()
            ->assertJsonPath('head', 'Dr. Future');

        $this->deleteJson("/api/admin/departments/{$id}", [], $headers)->assertNoContent();

        foreach (['RECORD_CREATED', 'RECORD_UPDATED', 'RECORD_DELETED'] as $action) {
            $this->assertNotNull($this->lastLog($action), "{$action} missing from the audit log");
        }
    }

    public function test_department_validation_uses_camel_case_keys(): void
    {
        $headers = $this->admin();

        $this->postJson('/api/admin/departments', [], $headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['facultyId', 'name', 'code']);

        // A frontend mock id like "f-sci" is not a real faculty.
        $this->postJson('/api/admin/departments', ['facultyId' => 'f-sci', 'name' => 'X', 'code' => 'X'], $headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['facultyId']);

        $this->postJson('/api/admin/departments', ['facultyId' => 9999, 'name' => 'X', 'code' => 'X'], $headers)
            ->assertUnprocessable()
            ->assertJsonPath('errors.facultyId.0', 'The selected faculty does not exist.');
    }

    public function test_department_name_is_unique_only_inside_its_faculty(): void
    {
        $csc = $this->makeDepartment('CSC', 'Computer Science');
        $arts = $this->makeFaculty();
        $headers = $this->admin();

        $this->postJson('/api/admin/departments', ['facultyId' => $csc->faculty_id, 'name' => 'computer science', 'code' => 'CS2'], $headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);

        $this->postJson('/api/admin/departments', ['facultyId' => $arts->id, 'name' => 'Computer Science', 'code' => 'CS3'], $headers)
            ->assertCreated();
    }

    public function test_department_code_must_be_unique(): void
    {
        $this->makeDepartment('CSC', 'Computer Science');
        $faculty = $this->makeFaculty();

        $this->postJson('/api/admin/departments', ['facultyId' => $faculty->id, 'name' => 'Other', 'code' => 'csc'], $this->admin())
            ->assertUnprocessable()
            ->assertJsonPath('errors.code.0', 'A department with this code already exists.');
    }

    public function test_department_with_children_cannot_be_deleted(): void
    {
        $headers = $this->admin();

        // A department with a program (students create one automatically).
        $withStudent = $this->makeDepartment('CSC', 'Computer Science');
        $this->makeStudent($withStudent);
        $this->deleteJson("/api/admin/departments/{$withStudent->id}", [], $headers)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Cannot delete "Computer Science": it still has 1 program and 1 student.');

        // A department that only has a department admin.
        $withAdmin = $this->makeDepartment('ECO', 'Economics');
        $this->makeDeptAdmin($withAdmin);
        $this->deleteJson("/api/admin/departments/{$withAdmin->id}", [], $headers)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Cannot delete "Economics": it still has 1 department admin.');

        // A department with a lecturer.
        $withLecturer = $this->makeDepartment('MTH', 'Mathematics');
        $this->makeLecturer($withLecturer);
        $this->deleteJson("/api/admin/departments/{$withLecturer->id}", [], $headers)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Cannot delete "Mathematics": it still has 1 lecturer.');

        $this->assertSame(3, Department::count());
    }

    // ---------- programs ----------

    public function test_program_list_has_the_frontend_shape_and_student_count(): void
    {
        $department = $this->makeDepartment('CSC', 'Computer Science');
        $this->makeStudent($department);
        $this->makeStudent($department);
        $program = Program::first();

        $this->getJson('/api/admin/programs', $this->admin())
            ->assertOk()
            ->assertJsonPath('data.0', [
                'id' => (string) $program->id,
                'name' => 'Computer Science',
                'award' => 'B.Sc.',
                'departmentId' => (string) $department->id,
                'departmentName' => 'Computer Science',
                'durationYears' => 4,
                'studentCount' => 2,
            ]);
    }

    public function test_super_admin_can_create_update_and_delete_a_program(): void
    {
        $department = $this->makeDepartment();
        $headers = $this->admin();

        $id = $this->postJson('/api/admin/programs', [
            'departmentId' => (string) $department->id,
            'name' => 'Software Engineering',
            'award' => 'B.Sc.',
            'durationYears' => 5, // the frontend number input may also send "5"
        ], $headers)
            ->assertCreated()
            ->assertJsonPath('durationYears', 5)
            ->assertJsonPath('departmentName', 'Computer Science')
            ->assertJsonPath('studentCount', 0)
            ->json('id');

        $this->putJson("/api/admin/programs/{$id}", ['departmentId' => $department->id, 'name' => 'Software Engineering', 'award' => 'B.Eng.', 'durationYears' => '5'], $headers)
            ->assertOk()
            ->assertJsonPath('award', 'B.Eng.');

        $this->deleteJson("/api/admin/programs/{$id}", [], $headers)->assertNoContent();

        foreach (['RECORD_CREATED', 'RECORD_UPDATED', 'RECORD_DELETED'] as $action) {
            $this->assertNotNull($this->lastLog($action), "{$action} missing from the audit log");
        }
    }

    public function test_program_validation_uses_camel_case_keys(): void
    {
        $department = $this->makeDepartment();
        $headers = $this->admin();

        $this->postJson('/api/admin/programs', [], $headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['departmentId', 'name', 'award', 'durationYears']);

        foreach ([0, 8, 'abc', 2.5] as $bad) {
            $this->postJson('/api/admin/programs', ['departmentId' => $department->id, 'name' => 'P', 'award' => 'B.Sc.', 'durationYears' => $bad], $headers)
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['durationYears']);
        }
    }

    public function test_program_name_is_unique_inside_its_department(): void
    {
        $csc = $this->makeDepartment('CSC', 'Computer Science');
        $mth = $this->makeDepartment('MTH', 'Mathematics');
        Program::create(['department_id' => $csc->id, 'name' => 'Data Science', 'award' => 'B.Sc.', 'duration_years' => 4]);
        $headers = $this->admin();

        $this->postJson('/api/admin/programs', ['departmentId' => $csc->id, 'name' => 'data science', 'award' => 'B.Sc.', 'durationYears' => 4], $headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);

        $this->postJson('/api/admin/programs', ['departmentId' => $mth->id, 'name' => 'Data Science', 'award' => 'B.Sc.', 'durationYears' => 4], $headers)
            ->assertCreated();
    }

    public function test_program_with_students_cannot_move_department_or_be_deleted(): void
    {
        $csc = $this->makeDepartment('CSC', 'Computer Science');
        $mth = $this->makeDepartment('MTH', 'Mathematics');
        $this->makeStudent($csc);
        $program = Program::where('department_id', $csc->id)->first();
        $headers = $this->admin();

        $this->putJson("/api/admin/programs/{$program->id}", ['departmentId' => $mth->id, 'name' => 'Computer Science', 'award' => 'B.Sc.', 'durationYears' => 4], $headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['departmentId']);
        $this->assertSame($csc->id, $program->fresh()->department_id);

        $this->deleteJson("/api/admin/programs/{$program->id}", [], $headers)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Cannot delete "Computer Science": it still has 1 student.');
        $this->assertNotNull(Program::find($program->id));
    }

    public function test_a_super_admin_whose_role_changed_loses_access_immediately(): void
    {
        $user = $this->makeSuperAdmin();
        $headers = $this->bearer($user);
        $this->getJson('/api/admin/faculties', $headers)->assertOk();

        User::whereKey($user->id)->update(['role' => 'student']);

        $this->getJson('/api/admin/faculties', $this->bearer($user->fresh()))->assertForbidden();
    }
}
