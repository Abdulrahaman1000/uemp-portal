<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Tests\Concerns\CreatesDemoData;
use Tests\TestCase;

class UserAdminApiTest extends TestCase
{
    use CreatesDemoData;

    public function test_only_super_admins_can_use_the_user_endpoints(): void
    {
        $department = $this->makeDepartment();
        $target = $this->makeStudent($department);

        $this->getJson('/api/admin/users')->assertUnauthorized();

        foreach ([$this->makeDeptAdmin($department), $this->makeLecturer($department), $this->makeStudent($department)] as $user) {
            $this->getJson('/api/admin/users', $this->bearer($user))->assertForbidden();
            $this->patchJson("/api/admin/users/{$target->id}/status", ['status' => 'suspended'], $this->bearer($user))->assertForbidden();
            $this->patchJson("/api/admin/users/{$target->id}/role", ['role' => 'lecturer'], $this->bearer($user))->assertForbidden();
        }

        $this->assertSame(UserStatus::Active, $target->fresh()->status);
    }

    public function test_list_returns_users_and_supports_search_role_and_status_filters(): void
    {
        $department = $this->makeDepartment();
        $admin = $this->makeSuperAdmin(['name' => 'Morgan Ellis']);
        $lecturer = $this->makeLecturer($department, ['name' => 'Dr. Maya Chen']);
        $this->makeStudent($department, ['name' => 'Ethan Okafor', 'status' => UserStatus::Suspended]);

        $all = $this->getJson('/api/admin/users', $this->bearer($admin))
            ->assertOk()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonStructure(['data' => [['id', 'name', 'email', 'role', 'department_id', 'department_name', 'status']], 'meta' => ['current_page', 'last_page', 'total']]);
        $this->assertIsString($all->json('data.0.id'));

        $this->getJson('/api/admin/users?role=lecturer', $this->bearer($admin))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.email', $lecturer->email)
            ->assertJsonPath('data.0.department_name', 'Computer Science');

        $this->getJson('/api/admin/users?status=suspended', $this->bearer($admin))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Ethan Okafor');

        $this->getJson('/api/admin/users?search=maya', $this->bearer($admin))->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/admin/users?q=morgan', $this->bearer($admin))->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_suspending_a_user_signs_them_out_and_is_audited(): void
    {
        $student = $this->makeStudent();
        $student->createToken('device');
        $admin = $this->makeSuperAdmin();

        $this->patchJson("/api/admin/users/{$student->id}/status", ['status' => 'suspended'], $this->bearer($admin))
            ->assertOk()
            ->assertJsonPath('status', 'suspended')
            ->assertJsonPath('role', 'student');

        $this->assertSame(UserStatus::Suspended, $student->fresh()->status);
        $this->assertSame(0, $student->tokens()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'USER_STATUS_CHANGED', 'entity' => $student->name, 'user_id' => $admin->id]);

        $this->patchJson("/api/admin/users/{$student->id}/status", ['status' => 'active'], $this->bearer($admin))
            ->assertOk()
            ->assertJsonPath('status', 'active');
    }

    public function test_status_changes_are_validated_and_a_user_cannot_change_their_own(): void
    {
        $student = $this->makeStudent();
        $admin = $this->makeSuperAdmin();

        $this->patchJson("/api/admin/users/{$student->id}/status", ['status' => 'banned'], $this->bearer($admin))
            ->assertStatus(422)->assertJsonValidationErrors(['status']);
        $this->patchJson("/api/admin/users/{$admin->id}/status", ['status' => 'inactive'], $this->bearer($admin))
            ->assertStatus(422)->assertJsonValidationErrors(['status']);
        $this->patchJson('/api/admin/users/9999/status', ['status' => 'inactive'], $this->bearer($admin))->assertNotFound();

        $this->assertSame(UserStatus::Active, $admin->fresh()->status);
    }

    public function test_a_lecturer_can_become_a_department_admin_of_their_department(): void
    {
        $department = $this->makeDepartment();
        $lecturer = $this->makeLecturer($department);
        $admin = $this->makeSuperAdmin();

        $this->patchJson("/api/admin/users/{$lecturer->id}/role", ['role' => 'dept_admin'], $this->bearer($admin))
            ->assertOk()
            ->assertJsonPath('role', 'dept_admin')
            ->assertJsonPath('department_id', (string) $department->id);

        $this->assertSame(UserRole::DeptAdmin, $lecturer->fresh()->role);
        $this->assertSame($department->id, $lecturer->fresh()->department_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'USER_ROLE_CHANGED', 'entity' => $lecturer->name]);
    }

    public function test_a_super_admin_needs_a_department_to_become_a_department_admin(): void
    {
        $department = $this->makeDepartment();
        $other = $this->makeSuperAdmin();
        $admin = $this->makeSuperAdmin();

        $this->patchJson("/api/admin/users/{$other->id}/role", ['role' => 'dept_admin'], $this->bearer($admin))
            ->assertStatus(422)->assertJsonValidationErrors(['departmentId']);
        $this->assertSame(UserRole::SuperAdmin, $other->fresh()->role);

        $this->patchJson("/api/admin/users/{$other->id}/role", ['role' => 'dept_admin', 'departmentId' => $department->id], $this->bearer($admin))
            ->assertOk()
            ->assertJsonPath('department_id', (string) $department->id);
    }

    public function test_a_department_admin_promoted_to_super_admin_loses_the_department_scope(): void
    {
        $deptAdmin = $this->makeDeptAdmin();
        $admin = $this->makeSuperAdmin();

        $this->patchJson("/api/admin/users/{$deptAdmin->id}/role", ['role' => 'super_admin'], $this->bearer($admin))
            ->assertOk()
            ->assertJsonPath('role', 'super_admin')
            ->assertJsonPath('department_id', null);

        $this->assertNull($deptAdmin->fresh()->department_id);
    }

    public function test_role_changes_that_would_leave_a_broken_account_are_refused(): void
    {
        $department = $this->makeDepartment();
        $student = $this->makeStudent($department);
        $deptAdmin = $this->makeDeptAdmin($department);
        $admin = $this->makeSuperAdmin();

        // Students own academic records, so they cannot change role, and nobody can become a student.
        $this->patchJson("/api/admin/users/{$student->id}/role", ['role' => 'lecturer'], $this->bearer($admin))
            ->assertStatus(422)->assertJsonValidationErrors(['role']);
        $this->patchJson("/api/admin/users/{$deptAdmin->id}/role", ['role' => 'student'], $this->bearer($admin))
            ->assertStatus(422)->assertJsonValidationErrors(['role']);

        // No lecturer profile exists for a department admin who was never a lecturer.
        $this->patchJson("/api/admin/users/{$deptAdmin->id}/role", ['role' => 'lecturer'], $this->bearer($admin))
            ->assertStatus(422)->assertJsonValidationErrors(['role']);

        // Invalid role names and changing your own role.
        $this->patchJson("/api/admin/users/{$deptAdmin->id}/role", ['role' => 'principal'], $this->bearer($admin))
            ->assertStatus(422)->assertJsonValidationErrors(['role']);
        $this->patchJson("/api/admin/users/{$admin->id}/role", ['role' => 'dept_admin', 'departmentId' => $department->id], $this->bearer($admin))
            ->assertStatus(422)->assertJsonValidationErrors(['role']);

        $this->assertSame(UserRole::Student, $student->fresh()->role);
        $this->assertSame(UserRole::DeptAdmin, $deptAdmin->fresh()->role);
        $this->assertSame(UserRole::SuperAdmin, $admin->fresh()->role);
    }
}
