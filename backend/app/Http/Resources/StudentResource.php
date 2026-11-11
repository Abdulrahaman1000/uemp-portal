<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Matches the frontend `Student` type. Load `user`, `department.faculty` and `program`.
 * Status is "graduated" once graduated_at is set, otherwise the account status.
 *
 * @mixin \App\Models\Student
 */
class StudentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'matricNo' => $this->matric_no,
            'name' => $this->user?->name ?? '',
            'email' => $this->user?->email ?? '',
            'departmentId' => (string) $this->department_id,
            'departmentName' => $this->department?->name ?? '',
            'facultyName' => $this->department?->faculty?->name ?? '',
            'programId' => (string) $this->program_id,
            'programName' => $this->program?->name ?? '',
            'level' => (int) $this->level,
            'status' => $this->graduated_at ? 'graduated' : ($this->user?->status?->value ?? 'inactive'),
        ];
    }
}
