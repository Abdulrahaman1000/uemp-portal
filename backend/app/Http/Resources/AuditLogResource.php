<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Matches the frontend `AuditLog` type. The role is read raw because system actions
 * store "system", which is not a UserRole case.
 *
 * @mixin \App\Models\AuditLog
 */
class AuditLogResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'timestamp' => $this->created_at?->toIso8601String(),
            'userName' => $this->user_name,
            'role' => $this->resource->getRawOriginal('role'),
            'action' => $this->action,
            'entity' => $this->entity,
            'details' => $this->details ?? '',
            'ip' => $this->ip ?? '',
        ];
    }
}
