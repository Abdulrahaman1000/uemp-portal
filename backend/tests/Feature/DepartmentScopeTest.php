<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Support\DepartmentScope;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\CreatesDemoData;
use Tests\TestCase;

class DepartmentScopeTest extends TestCase
{
    use CreatesDemoData;

    public function test_dept_admin_only_sees_own_department(): void
    {
        $csc = $this->makeDepartment('CSC', 'Computer Science');
        $eco = $this->makeDepartment('ECO', 'Economics');
        $this->makeStudent($csc);
        $this->makeStudent($csc);
        $this->makeStudent($eco);

        $admin = $this->makeDeptAdmin($csc);

        $this->assertSame(2, DepartmentScope::apply(Student::query(), $admin)->count());
    }

    public function test_super_admin_sees_everything(): void
    {
        $this->makeStudent($this->makeDepartment('CSC', 'Computer Science'));
        $this->makeStudent($this->makeDepartment('ECO', 'Economics'));

        $this->assertSame(2, DepartmentScope::apply(Student::query(), $this->makeSuperAdmin())->count());
    }

    public function test_other_roles_see_nothing(): void
    {
        $csc = $this->makeDepartment();
        $this->makeStudent($csc);

        $this->assertSame(0, DepartmentScope::apply(Student::query(), $this->makeLecturer($csc))->count());
        $this->assertSame(0, DepartmentScope::apply(Student::query(), $this->makeStudent($csc))->count());
    }

    public function test_dept_admin_without_a_department_sees_nothing(): void
    {
        $this->makeStudent($this->makeDepartment());
        $orphan = $this->makeUser(\App\Enums\UserRole::DeptAdmin);

        $this->assertSame(0, DepartmentScope::apply(Student::query(), $orphan)->count());
    }

    public function test_authorize_blocks_another_department_with_403(): void
    {
        $csc = $this->makeDepartment('CSC', 'Computer Science');
        $eco = $this->makeDepartment('ECO', 'Economics');
        $admin = $this->makeDeptAdmin($csc);

        DepartmentScope::authorize($admin, $csc->id);
        $this->assertTrue(DepartmentScope::canAccess($admin, $csc->id));
        $this->assertFalse(DepartmentScope::canAccess($admin, $eco->id));

        try {
            DepartmentScope::authorize($admin, $eco->id);
            $this->fail('Expected a 403');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }
}
