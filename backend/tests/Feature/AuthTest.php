<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\AuditLog;
use Tests\Concerns\CreatesDemoData;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use CreatesDemoData;

    public function test_login_returns_token_and_user_in_frontend_shape(): void
    {
        $user = $this->makeStudent(null, ['email' => 'student@northstar.edu']);

        $response = $this->postJson('/api/login', ['email' => 'student@northstar.edu', 'password' => 'demo123']);

        $response->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'role', 'department_id', 'department_name', 'status', 'studentId']])
            ->assertJsonPath('user.id', (string) $user->id)
            ->assertJsonPath('user.role', 'student')
            ->assertJsonPath('user.department_name', 'Computer Science')
            ->assertJsonPath('user.status', 'active');
        $this->assertIsString($response->json('user.department_id'));
    }

    public function test_super_admin_has_no_department(): void
    {
        $this->makeSuperAdmin(['email' => 'root@northstar.edu']);

        $this->postJson('/api/login', ['email' => 'root@northstar.edu', 'password' => 'demo123'])
            ->assertOk()
            ->assertJsonPath('user.department_id', null)
            ->assertJsonPath('user.department_name', null);
    }

    public function test_dept_admin_department_comes_from_users_table(): void
    {
        $this->makeDeptAdmin(null, ['email' => 'dept@northstar.edu']);

        $this->postJson('/api/login', ['email' => 'dept@northstar.edu', 'password' => 'demo123'])
            ->assertOk()
            ->assertJsonPath('user.department_name', 'Computer Science');
    }

    public function test_email_is_case_insensitive(): void
    {
        $this->makeSuperAdmin(['email' => 'root@northstar.edu']);

        $this->postJson('/api/login', ['email' => ' ROOT@Northstar.edu ', 'password' => 'demo123'])->assertOk();
    }

    public function test_wrong_password_is_401_and_logged(): void
    {
        $user = $this->makeSuperAdmin(['email' => 'root@northstar.edu']);

        $this->postJson('/api/login', ['email' => 'root@northstar.edu', 'password' => 'nope'])
            ->assertStatus(401)
            ->assertJsonPath('message', 'Invalid email or password.');

        $this->assertDatabaseHas('audit_logs', ['user_id' => $user->id, 'action' => 'LOGIN_FAILED']);
    }

    public function test_unknown_email_is_401_with_the_same_message(): void
    {
        $this->postJson('/api/login', ['email' => 'ghost@northstar.edu', 'password' => 'demo123'])
            ->assertStatus(401)
            ->assertJsonPath('message', 'Invalid email or password.');
    }

    public function test_missing_fields_are_422_with_field_errors(): void
    {
        $this->postJson('/api/login', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_suspended_and_inactive_accounts_are_refused_with_403(): void
    {
        $this->makeSuperAdmin(['email' => 'sus@northstar.edu', 'status' => UserStatus::Suspended]);
        $this->makeSuperAdmin(['email' => 'off@northstar.edu', 'status' => UserStatus::Inactive]);

        foreach (['sus@northstar.edu', 'off@northstar.edu'] as $email) {
            $this->postJson('/api/login', ['email' => $email, 'password' => 'demo123'])->assertStatus(403);
        }
        $this->assertDatabaseHas('audit_logs', ['action' => 'LOGIN_BLOCKED']);
    }

    public function test_login_is_rate_limited(): void
    {
        $this->makeSuperAdmin(['email' => 'root@northstar.edu']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', ['email' => 'root@northstar.edu', 'password' => 'bad'])->assertStatus(401);
        }
        $this->postJson('/api/login', ['email' => 'root@northstar.edu', 'password' => 'bad'])->assertStatus(429);
    }

    public function test_me_returns_the_current_user(): void
    {
        $user = $this->makeLecturer();

        $this->getJson('/api/me', $this->bearer($user))
            ->assertOk()
            ->assertJsonPath('id', (string) $user->id)
            ->assertJsonPath('role', 'lecturer');
    }

    public function test_me_without_token_is_401(): void
    {
        $this->getJson('/api/me')->assertStatus(401);
    }

    public function test_logout_revokes_only_the_current_token(): void
    {
        $user = $this->makeSuperAdmin();
        $first = $user->createToken('a')->plainTextToken;
        $user->createToken('b');

        $this->postJson('/api/logout', [], ['Authorization' => "Bearer {$first}"])->assertNoContent();

        $this->assertSame(1, $user->tokens()->count());
        $this->assertDatabaseHas('audit_logs', ['user_id' => $user->id, 'action' => 'USER_LOGOUT']);
    }

    public function test_account_suspended_after_login_loses_access(): void
    {
        $user = $this->makeSuperAdmin();
        $headers = $this->bearer($user);

        $user->update(['status' => UserStatus::Suspended]);

        $this->getJson('/api/me', $headers)->assertStatus(401);
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_successful_login_is_audited_with_snapshot(): void
    {
        $user = $this->makeSuperAdmin(['email' => 'root@northstar.edu']);

        $this->postJson('/api/login', ['email' => 'root@northstar.edu', 'password' => 'demo123'])->assertOk();

        $log = AuditLog::where('action', 'USER_LOGIN')->firstOrFail();
        $this->assertSame($user->name, $log->user_name);
        $this->assertSame('super_admin', $log->role->value);
    }
}
