<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Matches the frontend `Lecturer` type. Load `user` and `department`, and add
 * withCount('assignments as course_count') (current session) on list queries.
 *
 * @mixin \App\Models\Lecturer
 */
class LecturerResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'name' => $this->user?->name ?? '',
            'email' => $this->user?->email ?? '',
            'departmentId' => (string) $this->department_id,
            'departmentName' => $this->department?->name ?? '',
            'specialization' => $this->specialization ?? '',
            'courseCount' => (int) ($this->resource->getAttributeValue('course_count') ?? 0),
            'status' => $this->user?->status?->value ?? 'inactive',
        ];
    }
}
