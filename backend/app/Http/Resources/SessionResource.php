<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Matches the frontend `AcademicSession` type. The Sessions page calls `.map` on
 * `semesters`, so they are always included, earliest first.
 *
 * @mixin \App\Models\AcademicSession
 */
class SessionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'name' => $this->name,
            'startDate' => $this->start_date->toDateString(),
            'endDate' => $this->end_date->toDateString(),
            'isCurrent' => (bool) $this->is_current,
            'semesters' => $this->semesters
                ->sortBy(fn ($semester) => $semester->start_date->getTimestamp())
                ->values()
                ->map(fn ($semester) => (new SemesterResource($semester))->resolve($request))
                ->all(),
        ];
    }
}
