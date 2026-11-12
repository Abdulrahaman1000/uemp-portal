<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\CourseAssignment;
use App\Models\User;
use Tests\Concerns\CreatesDemoData;
use Tests\Concerns\CreatesWorkflowData;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use CreatesDemoData;
    use CreatesWorkflowData;

    /** @param array<string, mixed> $override */
    private function body(array $override = []): array
    {
        return array_merge([
            'name' => 'Dr. New Person',
            'email' => 'new.person@northstar.edu',
            'password' => 'Welcome123',
            'role' => 'lecturer',
            'departmentId' => null,
            'specialization' => 'Networks',
        ], $override);
    }

    public function test_only_super_admins_can_create_users_or_move_them(): void
    {
        $csc = $this->makeDepartment();
        $target = $this->makeLecturer($csc);

        $this->postJson('/api/admin/users', $this->body(['departmentId' => $csc->id]))->assertUnauthorized();
        $this->patchJson("/api/admin/users/{$target->id}/department", ['departmentId' => $csc->id])->assertUnauthorized();

        foreach ([$this->makeDeptAdmin($csc), $this->makeLecturer($csc), $this->makeStudent($csc)] as $user) {
            $this->postJson('/api/admin/users', $this->body(['departmentId' => $csc->id]), $this->bearer($user))->assertForbidden();
            $this->patchJson("/api/admin/users/{$target->id}/department", ['departmentId' => $csc->id], $this->bearer($user))->assertForbidden();
        }

        $this->assertSame(0, User::where('email', 'new.person@northstar.edu')->count());
    }

    public function test_creating_a_lecturer_makes_the_profile_and_a_working_login(): void
    {
        $csc = $this->makeDepartment();
        $admin = $this->makeSuperAdmin();

        $this->postJson('/api/admin/users', $this->body(['departmentId' => $csc->id, 'email' => 'New.Person@Northstar.edu']), $this->bearer($admin))
            ->assertCreated()
            ->assertJsonPath('role', 'lecturer')
            ->assertJsonPath('email', 'new.person@northstar.edu')
            ->assertJsonPath('status', 'active')
            ->assertJsonPath('department_id', (string) $csc->id)
            ->assertJsonPath('department_name', 'Computer Science');

        $user = User::where('email', 'new.person@northstar.edu')->firstOrFail();
        $this->assertSame(UserRole::Lecturer, $user->role);
        $this->assertNull($user->department_id);
        $this->assertSame($csc->id, (int) $user->lecturer->department_id);
        $this->assertSame('Networks', $user->lecturer->specialization);
        $this->assertDatabaseHas('audit_logs', ['action' => 'USER_CREATED', 'user_id' => $admin->id, 'entity' => 'Dr. New Person']);

        $this->postJson('/api/login', ['email' => 'new.person@northstar.edu', 'password' => 'Welcome123'])
            ->assertOk()
            ->assertJsonPath('user.role', 'lecturer');
    }

    public function test_creating_a_department_admin_scopes_them_to_that_department(): void
    {
        $csc = $this->makeDepartment('CSC', 'Computer Science');
        $admin = $this->makeSuperAdmin();

        $this->postJson('/api/admin/users', $this->body(['role' => 'dept_admin', 'departmentId' => $csc->id, 'email' => 'head@northstar.edu']), $this->bearer($admin))
            ->assertCreated()
            ->assertJsonPath('role', 'dept_admin')
            ->assertJsonPath('department_id', (string) $csc->id);

        $user = User::where('email', 'head@northstar.edu')->firstOrFail();
        $this->assertSame($csc->id, (int) $user->department_id);
        $this->assertNull($user->lecturer);

        $this->getJson('/api/dept/dashboard', $this->bearer($user))
            ->assertOk()
            ->assertJsonPath('departmentName', 'Computer Science');
    }

    public function test_a_super_admin_account_gets_no_department(): void
    {
        $csc = $this->makeDepartment();
        $admin = $this->makeSuperAdmin();

        $this->postJson('/api/admin/users', $this->body(['role' => 'super_admin', 'departmentId' => $csc->id, 'email' => 'boss@northstar.edu']), $this->bearer($admin))
            ->assertCreated()
            ->assertJsonPath('role', 'super_admin')
            ->assertJsonPath('department_id', null);

        $this->assertNull(User::where('email', 'boss@northstar.edu')->firstOrFail()->department_id);
    }

    public function test_create_validation_errors_use_the_field_names(): void
    {
        $csc = $this->makeDepartment();
        $existing = $this->makeLecturer($csc);
        $admin = $this->makeSuperAdmin();
        $before = User::count();

        // A lecturer and a department admin need a department.
        $this->postJson('/api/admin/users', $this->body(), $this->bearer($admin))
            ->assertStatus(422)->assertJsonValidationErrors(['departmentId']);
        $this->postJson('/api/admin/users', $this->body(['role' => 'dept_admin']), $this->bearer($admin))
            ->assertStatus(422)->assertJsonValidationErrors(['departmentId']);

        // Students are created by the department admin, and unknown roles are refused.
        $this->postJson('/api/admin/users', $this->body(['role' => 'student', 'departmentId' => $csc->id]), $this->bearer($admin))
            ->assertStatus(422)->assertJsonValidationErrors(['role']);
        $this->postJson('/api/admin/users', $this->body(['role' => 'principal', 'departmentId' => $csc->id]), $this->bearer($admin))
            ->assertStatus(422)->assertJsonValidationErrors(['role']);

        $this->postJson('/api/admin/users', $this->body(['departmentId' => $csc->id, 'email' => strtoupper($existing->email)]), $this->bearer($admin))
            ->assertStatus(422)->assertJsonValidationErrors(['email']);
        $this->postJson('/api/admin/users', $this->body(['departmentId' => $csc->id, 'password' => 'short']), $this->bearer($admin))
            ->assertStatus(422)->assertJsonValidationErrors(['password']);
        $this->postJson('/api/admin/users', $this->body(['departmentId' => 999999]), $this->bearer($admin))
            ->assertStatus(422)->assertJsonValidationErrors(['departmentId']);
        $this->postJson('/api/admin/users', ['role' => 'lecturer', 'departmentId' => $csc->id], $this->bearer($admin))
            ->assertStatus(422)->assertJsonValidationErrors(['name', 'email', 'password']);

        $this->assertSame($before, User::count());
    }

    public function test_a_lecturer_with_no_current_work_can_move_to_another_department(): void
    {
        $csc = $this->makeDepartment('CSC', 'Computer Science');
        $mth = $this->makeDepartment('MTH', 'Mathematics');
        $lecturer = $this->makeLecturer($csc);
        $admin = $this->makeSuperAdmin();

        $this->patchJson("/api/admin/users/{$lecturer->id}/department", ['departmentId' => $mth->id], $this->bearer($admin))
            ->assertOk()
            ->assertJsonPath('role', 'lecturer')
            ->assertJsonPath('department_id', (string) $mth->id)
            ->assertJsonPath('department_name', 'Mathematics');

        $this->assertSame($mth->id, (int) $lecturer->lecturer()->first()->department_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'USER_DEPARTMENT_CHANGED', 'user_id' => $admin->id, 'entity' => $lecturer->name]);
    }

    public function test_a_lecturer_with_current_courses_or_open_results_cannot_move(): void
    {
        $csc = $this->makeDepartment('CSC', 'Computer Science');
        $mth = $this->makeDepartment('MTH', 'Mathematics');
        $semester = $this->currentSemester();
        $admin = $this->makeSuperAdmin();

        $teaching = $this->makeLecturer($csc);
        $course = $this->makeCourse('CSC301', $csc);
        CourseAssignment::create(['course_id' => $course->id, 'lecturer_id' => $teaching->lecturer->id, 'academic_session_id' => $semester->academic_session_id]);

        $draft = $this->makeLecturer($csc);
        $this->makeSubmission($this->makeCourse('CSC302', $csc), $semester, $draft, 'draft');

        $finished = $this->makeLecturer($csc);
        $this->makeSubmission($this->makeCourse('CSC303', $csc), $semester, $finished, 'approved');

        foreach ([$teaching, $draft] as $blocked) {
            $this->patchJson("/api/admin/users/{$blocked->id}/department", ['departmentId' => $mth->id], $this->bearer($admin))
                ->assertStatus(422)->assertJsonValidationErrors(['departmentId']);
            $this->assertSame($csc->id, (int) $blocked->lecturer()->first()->department_id);
        }
        $this->assertDatabaseMissing('audit_logs', ['action' => 'USER_DEPARTMENT_CHANGED']);

        // Only approved results are history, so this lecturer can move.
        $this->patchJson("/api/admin/users/{$finished->id}/department", ['departmentId' => $mth->id], $this->bearer($admin))
            ->assertOk()->assertJsonPath('department_id', (string) $mth->id);
    }

    public function test_a_department_admin_can_move_and_the_scope_follows(): void
    {
        $csc = $this->makeDepartment('CSC', 'Computer Science');
        $mth = $this->makeDepartment('MTH', 'Mathematics');
        $deptAdmin = $this->makeDeptAdmin($csc);
        $admin = $this->makeSuperAdmin();

        $this->patchJson("/api/admin/users/{$deptAdmin->id}/department", ['departmentId' => $mth->id], $this->bearer($admin))
            ->assertOk()
            ->assertJsonPath('role', 'dept_admin')
            ->assertJsonPath('department_id', (string) $mth->id);

        $this->assertSame($mth->id, (int) $deptAdmin->fresh()->department_id);
        $this->getJson('/api/dept/dashboard', $this->bearer($deptAdmin->fresh()))
            ->assertOk()->assertJsonPath('departmentName', 'Mathematics');
    }

    public function test_students_and_super_admins_cannot_be_moved_and_bad_input_is_refused(): void
    {
        $csc = $this->makeDepartment('CSC', 'Computer Science');
        $mth = $this->makeDepartment('MTH', 'Mathematics');
        $student = $this->makeStudent($csc);
        $other = $this->makeSuperAdmin();
        $lecturer = $this->makeLecturer($csc);
        $admin = $this->makeSuperAdmin();

        $this->patchJson("/api/admin/users/{$student->id}/department", ['departmentId' => $mth->id], $this->bearer($admin))
            ->assertStatus(422)->assertJsonValidationErrors(['departmentId']);
        $this->patchJson("/api/admin/users/{$other->id}/department", ['departmentId' => $mth->id], $this->bearer($admin))
            ->assertStatus(422)->assertJsonValidationErrors(['departmentId']);
        $this->patchJson("/api/admin/users/{$lecturer->id}/department", ['departmentId' => 999999], $this->bearer($admin))
            ->assertStatus(422)->assertJsonValidationErrors(['departmentId']);
        $this->patchJson("/api/admin/users/{$lecturer->id}/department", [], $this->bearer($admin))
            ->assertStatus(422)->assertJsonValidationErrors(['departmentId']);
        $this->patchJson('/api/admin/users/999999/department', ['departmentId' => $mth->id], $this->bearer($admin))
            ->assertNotFound();

        $this->assertSame($csc->id, (int) $student->student()->first()->department_id);
    }

    public function test_moving_to_the_same_department_changes_nothing(): void
    {
        $csc = $this->makeDepartment();
        $lecturer = $this->makeLecturer($csc);
        $admin = $this->makeSuperAdmin();

        $this->patchJson("/api/admin/users/{$lecturer->id}/department", ['departmentId' => $csc->id], $this->bearer($admin))
            ->assertOk()->assertJsonPath('department_id', (string) $csc->id);

        $this->assertDatabaseMissing('audit_logs', ['action' => 'USER_DEPARTMENT_CHANGED']);
    }
}
