<?php

namespace App\Http\Requests\DeptAdmin;

use App\Http\Requests\Admin\ProgramRequest;
use App\Support\DepartmentScope;

/**
 * Same rules as the super admin ProgramRequest, but the department is NOT taken from the
 * request: it is always the department admin's own. A program of another department is a 403.
 */
class DeptProgramRequest extends ProgramRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null || $user->resolveDepartmentId() === null) {
            return false;
        }

        $program = $this->route('program');

        return $program === null || DepartmentScope::canAccess($user, $program->department_id);
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();
        // Any departmentId in the body is overwritten.
        $this->merge(['departmentId' => $this->user()?->resolveDepartmentId()]);
    }
}
