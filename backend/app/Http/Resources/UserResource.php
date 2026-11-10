<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Matches the frontend `User` type. Note the snake_case department_id and
 * department_name: that is what portal.ts expects.
 *
 * @mixin \App\Models\User
 */
class UserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $departmentId = $this->resolveDepartmentId();
        $department = match ($this->role->value) {
            'student' => $this->student?->department,
            'lecturer' => $this->lecturer?->department,
            'dept_admin' => $this->department,
            default => null,
        };

        return array_filter([
            'id' => (string) $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role->value,
            'department_id' => $departmentId !== null ? (string) $departmentId : null,
            'department_name' => $department?->name,
            'status' => $this->status->value,
            'studentId' => $this->student ? (string) $this->student->id : null,
            'lastActive' => $this->last_active_at?->toDateString(),
        ], fn ($value, $key) => ! in_array($key, ['studentId', 'lastActive'], true) || $value !== null, ARRAY_FILTER_USE_BOTH);
    }
}
