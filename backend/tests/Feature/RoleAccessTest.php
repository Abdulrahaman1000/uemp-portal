<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\Concerns\CreatesDemoData;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use CreatesDemoData;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['api', 'auth:sanctum', 'active', 'role:super_admin'])->get('/api/_test/super', fn () => ['ok' => true]);
        Route::middleware(['api', 'auth:sanctum', 'active', 'role:dept_admin,super_admin'])->get('/api/_test/admins', fn () => ['ok' => true]);
        Route::middleware(['api', 'auth:sanctum', 'active', 'role:nonsense'])->get('/api/_test/nobody', fn () => ['ok' => true]);
    }

    public function test_super_admin_route_allows_only_super_admin(): void
    {
        $this->getJson('/api/_test/super', $this->bearer($this->makeSuperAdmin()))->assertOk();

        foreach ([$this->makeDeptAdmin(), $this->makeLecturer(), $this->makeStudent()] as $user) {
            $this->getJson('/api/_test/super', $this->bearer($user))->assertStatus(403);
        }
    }

    public function test_route_with_two_roles_allows_both(): void
    {
        $this->getJson('/api/_test/admins', $this->bearer($this->makeDeptAdmin()))->assertOk();
        $this->getJson('/api/_test/admins', $this->bearer($this->makeSuperAdmin()))->assertOk();
        $this->getJson('/api/_test/admins', $this->bearer($this->makeStudent()))->assertStatus(403);
    }

    public function test_unauthenticated_request_is_401_not_403(): void
    {
        $this->getJson('/api/_test/super')->assertStatus(401);
    }

    public function test_unknown_role_name_matches_nobody(): void
    {
        $this->getJson('/api/_test/nobody', $this->bearer($this->makeSuperAdmin()))->assertStatus(403);
    }

    public function test_denied_access_is_written_to_the_audit_log(): void
    {
        $student = $this->makeStudent();

        $this->getJson('/api/_test/super', $this->bearer($student))->assertStatus(403);

        $this->assertDatabaseHas('audit_logs', ['user_id' => $student->id, 'action' => 'ACCESS_DENIED']);
    }

    public function test_403_message_is_the_standard_one(): void
    {
        $this->getJson('/api/_test/super', $this->bearer($this->makeLecturer()))
            ->assertStatus(403)
            ->assertJsonPath('message', 'You do not have permission to access this resource.');
    }
}
