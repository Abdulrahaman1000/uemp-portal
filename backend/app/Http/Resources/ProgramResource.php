<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Program */
class ProgramResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'name' => $this->name,
            'award' => $this->award,
            'departmentId' => (string) $this->department_id,
            'departmentName' => $this->department->name,
            'durationYears' => (int) $this->duration_years,
            'studentCount' => (int) ($this->resource->getAttributeValue('students_count') ?? $this->students()->count()),
        ];
    }
}
