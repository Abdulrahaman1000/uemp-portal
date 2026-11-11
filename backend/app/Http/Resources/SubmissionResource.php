<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Matches the frontend `ResultSubmission` type. Load `course.department`, `lecturer.user`,
 * `semester.session`, `auditLogs`, and withCount('results as student_count').
 *
 * @mixin \App\Models\ResultSubmission
 */
class SubmissionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $audit = $this->relationLoaded('auditLogs') ? $this->auditLogs : collect();

        return [
            'id' => (string) $this->id,
            'courseId' => (string) $this->course_id,
            'courseCode' => $this->course?->code ?? '',
            'courseTitle' => $this->course?->title ?? '',
            'departmentId' => (string) ($this->course?->department_id ?? ''),
            'departmentName' => $this->course?->department?->name ?? '',
            'lecturerName' => $this->lecturer?->user?->name ?? '',
            'sessionName' => $this->semester?->session?->name ?? '',
            'semester' => $this->semester?->name?->value ?? '',
            'studentCount' => (int) ($this->resource->getAttributeValue('student_count') ?? 0),
            'status' => $this->status->value,
            'submittedAt' => $this->submitted_at?->toIso8601String(),
            'feedback' => $this->feedback ?: null,
            'audit' => $audit->map(fn ($log) => [
                'id' => (string) $log->id,
                'at' => $log->created_at?->toIso8601String(),
                'actorName' => $log->user_name,
                'actorRole' => $log->getRawOriginal('role'),
                'action' => $log->action,
                'note' => ($log->metadata['note'] ?? null) ?: ($log->details ?: null),
            ])->values()->all(),
        ];
    }
}
