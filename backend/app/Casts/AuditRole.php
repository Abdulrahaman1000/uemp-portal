<?php

namespace App\Casts;

use App\Enums\UserRole;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Role stored on an audit row. Real users give a UserRole case; actions with no actor
 * (automatic or system actions) store the plain word "system", which is not a UserRole.
 *
 * @implements CastsAttributes<UserRole|string|null, UserRole|string|null>
 */
class AuditRole implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): UserRole|string|null
    {
        if ($value === null) {
            return null;
        }

        return UserRole::tryFrom((string) $value) ?? (string) $value;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value instanceof UserRole) {
            return $value->value;
        }

        return $value === null ? null : (string) $value;
    }
}
