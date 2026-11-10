<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Matches the frontend `Course` type. The lecturer comes from the assignment of the
 * CURRENT academic session, so load `department` and `currentAssignment.lecturer.user`.
 *
 * @mixin \App\Models\Course
 */
class CourseResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $assignment = $this->relationLoaded('currentAssignment') ? $this->currentAssignment : null;

        return [
            'id' => (string) $this->id,
            'code' => $this->code,
            'title' => $this->title,
            'units' => (int) $this->units,
            'level' => (int) $this->level,
            'semester' => $this->semester->value,
            'type' => $this->type->value,
            'departmentId' => (string) $this->department_id,
            'departmentName' => $this->department?->name ?? '',
            'programId' => (string) $this->program_id,
            'lecturerId' => $assignment ? (string) $assignment->lecturer_id : null,
            'lecturerName' => $assignment?->lecturer?->user?->name,
        ];
    }
}
