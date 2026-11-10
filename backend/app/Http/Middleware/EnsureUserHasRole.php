<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Services\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Usage: ->middleware('role:super_admin') or ->middleware('role:dept_admin,super_admin'). */
class EnsureUserHasRole
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Unknown role names resolve to null and therefore match nobody.
        $allowed = array_filter(array_map(fn (string $r) => UserRole::tryFrom($r), $roles));

        if (! $user->hasRole(...$allowed)) {
            $this->audit->record($user, 'ACCESS_DENIED', $request->method().' /'.$request->path(), 'Role '.$user->role->value.' is not allowed here');

            return response()->json(['message' => 'You do not have permission to access this resource.'], 403);
        }

        return $next($request);
    }
}
