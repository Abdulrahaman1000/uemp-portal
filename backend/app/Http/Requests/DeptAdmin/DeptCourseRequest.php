<?php

namespace App\Http\Requests\DeptAdmin;

use App\Http\Requests\Admin\CourseRequest;
use App\Support\DepartmentScope;

/**
 * Same rules as the super admin CourseRequest, but the department is NOT taken from the
 * request: it is always the department admin's own. A course of another department is a 403.
 */
class DeptCourseRequest extends CourseRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $departmentId = $user?->resolveDepartmentId();
        if ($departmentId === null) {
            return false;
        }
        $course = $this->route('course');

        return $course === null || DepartmentScope::canAccess($user, $course->department_id);
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();
        // Any departmentId in the body is overwritten.
        $this->merge(['departmentId' => $this->user()?->resolveDepartmentId()]);
    }
}
