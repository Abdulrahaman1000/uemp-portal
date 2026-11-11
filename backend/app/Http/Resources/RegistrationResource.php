<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Matches the frontend `RegistrationRecord` type. Load `student.user`, `student.department`,
 * `semester.session` and `courses`.
 *
 * @mixin \App\Models\Registration
 */
class RegistrationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'studentId' => (string) $this->student_id,
            'studentName' => $this->student?->user?->name ?? '',
            'matricNo' => $this->student?->matric_no ?? '',
            'departmentName' => $this->student?->department?->name ?? '',
            'level' => (int) ($this->student?->level ?? 0),
            'sessionName' => $this->semester?->session?->name ?? '',
            'semester' => $this->semester?->name?->value ?? '',
            'courses' => $this->courses->map(fn ($course) => [
                'courseId' => (string) $course->id,
                'code' => $course->code,
                'title' => $course->title,
                'units' => (int) $course->units,
            ])->values()->all(),
            'totalUnits' => (int) $this->courses->sum('units'),
            'registeredAt' => ($this->registered_at ?? $this->created_at)?->toIso8601String(),
        ];
    }
}
