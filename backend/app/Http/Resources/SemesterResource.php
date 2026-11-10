<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Semester */
class SemesterResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'sessionId' => (string) $this->academic_session_id,
            'name' => $this->name->value,
            'startDate' => $this->start_date->toDateString(),
            'endDate' => $this->end_date->toDateString(),
            'isCurrent' => (bool) $this->is_current,
        ];
    }
}
