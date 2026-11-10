<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Department */
class DepartmentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'facultyId' => (string) $this->faculty_id,
            'facultyName' => $this->faculty->name,
            'head' => $this->head ?? '',
            'programCount' => (int) ($this->resource->getAttributeValue('programs_count') ?? $this->programs()->count()),
        ];
    }
}
