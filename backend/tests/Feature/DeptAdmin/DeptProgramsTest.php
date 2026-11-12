<?php

namespace Tests\Feature\DeptAdmin;

use App\Models\Program;
use Tests\Concerns\CreatesDemoData;
use Tests\TestCase;

class DeptProgramsTest extends TestCase
{
    use CreatesDemoData;

    /** @param array<string, mixed> $override */
    private function body(array $override = []): array
    {
        return array_merge(['name' => 'Cyber Security', 'award' => 'B.Sc.', 'durationYears' => 4], $override);
    }

    public function test_only_department_admins_can_manage_programs(): void
    {
        $csc = $this->makeDepartment();
        $program = Program::create(['department_id' => $csc->id, 'name' => 'Data Science', 'award' => 'B.Sc.', 'duration_years' => 4]);

        $this->postJson('/api/dept/programs', $this->body())->assertUnauthorized();
        $this->putJson("/api/dept/programs/{$program->id}", $this->body())->assertUnauthorized();
        $this->deleteJson("/api/dept/programs/{$program->id}")->assertUnauthorized();

        foreach ([$this->makeLecturer($csc), $this->makeStudent($csc), $this->makeSuperAdmin()] as $user) {
            $this->postJson('/api/dept/programs', $this->body(), $this->bearer($user))->assertForbidden();
            $this->putJson("/api/dept/programs/{$program->id}", $this->body(), $this->bearer($user))->assertForbidden();
            $this->deleteJson("/api/dept/programs/{$program->id}", [], $this->bearer($user))->assertForbidden();
        }

        $this->assertDatabaseHas('programs', ['id' => $program->id, 'name' => 'Data Science']);
        $this->assertDatabaseMissing('programs', ['name' => 'Cyber Security']);
    }

    public function test_a_department_admin_creates_a_program_in_their_own_department_only(): void
    {
        $csc = $this->makeDepartment('CSC', 'Computer Science');
        $mth = $this->makeDepartment('MTH', 'Mathematics');
        $admin = $this->makeDeptAdmin($csc);

        // A departmentId in the body is ignored: the program always lands in the admin's department.
        $this->postJson('/api/dept/programs', $this->body(['departmentId' => $mth->id]), $this->bearer($admin))
            ->assertCreated()
            ->assertJsonPath('name', 'Cyber Security')
            ->assertJsonPath('award', 'B.Sc.')
            ->assertJsonPath('departmentId', (string) $csc->id)
            ->assertJsonPath('departmentName', 'Computer Science')
            ->assertJsonPath('studentCount', 0);

        $this->assertDatabaseHas('programs', ['name' => 'Cyber Security', 'department_id' => $csc->id]);
        $this->assertDatabaseMissing('programs', ['name' => 'Cyber Security', 'department_id' => $mth->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'RECORD_CREATED', 'user_id' => $admin->id]);

        $this->getJson('/api/dept/programs', $this->bearer($admin))
            ->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_program_validation_errors_use_the_field_names(): void
    {
        $csc = $this->makeDepartment();
        $admin = $this->makeDeptAdmin($csc);
        Program::create(['department_id' => $csc->id, 'name' => 'Data Science', 'award' => 'B.Sc.', 'duration_years' => 4]);

        $this->postJson('/api/dept/programs', [], $this->bearer($admin))
            ->assertStatus(422)->assertJsonValidationErrors(['name', 'award', 'durationYears']);
        $this->postJson('/api/dept/programs', $this->body(['durationYears' => 9]), $this->bearer($admin))
            ->assertStatus(422)->assertJsonValidationErrors(['durationYears']);
        $this->postJson('/api/dept/programs', $this->body(['name' => 'data science']), $this->bearer($admin))
            ->assertStatus(422)->assertJsonValidationErrors(['name']);

        $this->assertSame(1, Program::where('department_id', $csc->id)->count());
    }

    public function test_a_department_admin_can_edit_their_program_but_not_another_departments(): void
    {
        $csc = $this->makeDepartment('CSC', 'Computer Science');
        $mth = $this->makeDepartment('MTH', 'Mathematics');
        $own = Program::create(['department_id' => $csc->id, 'name' => 'Data Science', 'award' => 'B.Sc.', 'duration_years' => 4]);
        $other = Program::create(['department_id' => $mth->id, 'name' => 'Statistics', 'award' => 'B.Sc.', 'duration_years' => 4]);
        $admin = $this->makeDeptAdmin($csc);

        // The department cannot be changed through the body.
        $this->putJson("/api/dept/programs/{$own->id}", $this->body(['name' => 'Data Science and AI', 'durationYears' => 5, 'departmentId' => $mth->id]), $this->bearer($admin))
            ->assertOk()
            ->assertJsonPath('name', 'Data Science and AI')
            ->assertJsonPath('durationYears', 5)
            ->assertJsonPath('departmentId', (string) $csc->id);

        $this->assertSame($csc->id, (int) $own->fresh()->department_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'RECORD_UPDATED', 'user_id' => $admin->id]);

        $this->putJson("/api/dept/programs/{$other->id}", $this->body(['name' => 'Hijacked']), $this->bearer($admin))->assertForbidden();
        $this->assertSame('Statistics', $other->fresh()->name);
    }

    public function test_deleting_is_blocked_while_students_exist_and_for_other_departments(): void
    {
        $csc = $this->makeDepartment('CSC', 'Computer Science');
        $mth = $this->makeDepartment('MTH', 'Mathematics');
        $admin = $this->makeDeptAdmin($csc);
        $this->makeStudent($csc);
        $withStudents = Program::where('department_id', $csc->id)->firstOrFail();
        $empty = Program::create(['department_id' => $csc->id, 'name' => 'Data Science', 'award' => 'B.Sc.', 'duration_years' => 4]);
        $other = Program::create(['department_id' => $mth->id, 'name' => 'Statistics', 'award' => 'B.Sc.', 'duration_years' => 4]);

        $this->deleteJson("/api/dept/programs/{$withStudents->id}", [], $this->bearer($admin))->assertStatus(422);
        $this->assertDatabaseHas('programs', ['id' => $withStudents->id]);

        $this->deleteJson("/api/dept/programs/{$other->id}", [], $this->bearer($admin))->assertForbidden();
        $this->assertDatabaseHas('programs', ['id' => $other->id]);

        $this->deleteJson("/api/dept/programs/{$empty->id}", [], $this->bearer($admin))->assertNoContent();
        $this->assertDatabaseMissing('programs', ['id' => $empty->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'RECORD_DELETED', 'user_id' => $admin->id]);
    }

    public function test_a_department_admin_without_a_department_cannot_create_programs(): void
    {
        $csc = $this->makeDepartment();
        $admin = $this->makeDeptAdmin($csc, ['department_id' => null]);

        $this->postJson('/api/dept/programs', $this->body(), $this->bearer($admin))->assertForbidden();
        $this->assertDatabaseMissing('programs', ['name' => 'Cyber Security']);
    }
}
