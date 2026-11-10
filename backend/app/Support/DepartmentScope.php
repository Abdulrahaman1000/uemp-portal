<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Department scoping for department admins. It fails closed: any role that is
 * not super admin or dept admin (or a dept admin without a department) sees nothing.
 */
class DepartmentScope
{
    /** Restrict a query to the departments the user may see. */
    public static function apply(Builder $query, User $user, string $column = 'department_id'): Builder
    {
        if ($user->role === UserRole::SuperAdmin) {
            return $query;
        }

        if ($user->role === UserRole::DeptAdmin && $user->department_id) {
            return $query->where($column, $user->department_id);
        }

        return $query->whereRaw('1 = 0');
    }

    public static function canAccess(User $user, ?int $departmentId): bool
    {
        if ($user->role === UserRole::SuperAdmin) {
            return true;
        }

        return $user->role === UserRole::DeptAdmin
            && $user->department_id !== null
            && $departmentId !== null
            && (int) $user->department_id === (int) $departmentId;
    }

    /** Use on every single-record action. Aborts with 403 when the record is in another department. */
    public static function authorize(User $user, ?int $departmentId): void
    {
        abort_unless(self::canAccess($user, $departmentId), 403, 'This record belongs to another department.');
    }
}
