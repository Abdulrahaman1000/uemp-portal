<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Matches the frontend `Faculty` type. List queries use withCount('departments');
 * the fallback covers a faculty that was just created or updated.
 *
 * @mixin \App\Models\Faculty
 */
class FacultyResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'dean' => $this->dean ?? '',
            'departmentCount' => (int) ($this->resource->getAttributeValue('departments_count') ?? $this->departments()->count()),
        ];
    }
}
