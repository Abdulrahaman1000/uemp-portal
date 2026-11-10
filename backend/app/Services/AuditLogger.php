<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * The single place that writes the audit trail. Call it for every
 * submission, approval, rejection, reopen, registration and admin change.
 */
class AuditLogger
{
    /**
     * @param  Model|null  $record  The affected record (linked polymorphically).
     * @param  array<string, mixed>  $metadata  Extra structured data (e.g. old/new values).
     */
    public function record(
        ?User $actor,
        string $action,
        string $entity,
        ?string $details = null,
        ?Model $record = null,
        array $metadata = [],
    ): AuditLog {
        return AuditLog::create([
            'user_id' => $actor?->id,
            'user_name' => $actor?->name ?? 'System',
            'role' => $actor?->role?->value ?? 'system',
            'action' => $action,
            'entity' => $entity,
            'details' => $details,
            'ip' => request()?->ip(),
            'auditable_type' => $record ? $record::class : null,
            'auditable_id' => $record?->getKey(),
            'metadata' => $metadata ?: null,
        ]);
    }
}
