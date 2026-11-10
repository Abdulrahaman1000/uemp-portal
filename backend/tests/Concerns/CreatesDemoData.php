<?php

namespace Tests\Concerns;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\Lecturer;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;

/** Small builders so each test creates only what it needs. */
trait CreatesDemoData
{
    protected function makeDepartment(string $code = 'CSC', string $name = 'Computer Science'): Department
    {
        $faculty = Faculty::firstOrCreate(['code' => 'SCI'], ['name' => 'Faculty of Science', 'dean' => 'Prof. Harris']);

        return Department::firstOrCreate(
            ['code' => $code],
            ['faculty_id' => $faculty->id, 'name' => $name, 'head' => 'Head '.$code],
        );
    }

    protected function makeUser(UserRole $role, array $attributes = []): User
    {
        static $n = 0;
        $n++;

        return User::create(array_merge([
            'name' => ucfirst($role->value).' '.$n,
            'email' => "{$role->value}{$n}@northstar.edu",
            'password' => 'demo123',
            'role' => $role,
            'status' => UserStatus::Active,
        ], $attributes));
    }

    protected function makeSuperAdmin(array $attributes = []): User
    {
        return $this->makeUser(UserRole::SuperAdmin, $attributes);
    }

    protected function makeDeptAdmin(?Department $department = null, array $attributes = []): User
    {
        $department ??= $this->makeDepartment();

        return $this->makeUser(UserRole::DeptAdmin, array_merge(['department_id' => $department->id], $attributes));
    }

    protected function makeLecturer(?Department $department = null, array $attributes = []): User
    {
        $department ??= $this->makeDepartment();
        $user = $this->makeUser(UserRole::Lecturer, $attributes);
        Lecturer::create(['user_id' => $user->id, 'department_id' => $department->id, 'specialization' => 'Algorithms']);

        return $user;
    }

    protected function makeStudent(?Department $department = null, array $attributes = []): User
    {
        $department ??= $this->makeDepartment();
        $user = $this->makeUser(UserRole::Student, $attributes);
        $program = Program::firstOrCreate(['department_id' => $department->id, 'name' => $department->name], ['award' => 'B.Sc.', 'duration_years' => 4]);
        Student::create([
            'user_id' => $user->id,
            'matric_no' => $department->code.'/2023/'.str_pad((string) $user->id, 3, '0', STR_PAD_LEFT),
            'department_id' => $department->id,
            'program_id' => $program->id,
            'level' => 300,
        ]);

        return $user;
    }

    /** @return array<string, string> */
    protected function bearer(User $user): array
    {
        // The test client keeps the first authenticated user cached between requests.
        // Forgetting the guards makes every request authenticate from its own token.
        $this->app['auth']->forgetGuards();

        return ['Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken, 'Accept' => 'application/json'];
    }
}
