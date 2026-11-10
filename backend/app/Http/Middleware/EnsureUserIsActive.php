<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks accounts that became inactive or suspended after they logged in.
 * Their tokens are deleted, so the frontend gets a 401 and signs the user out.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->isActive()) {
            $user->tokens()->delete();

            return response()->json(['message' => 'This account is not active.'], 401);
        }

        // Refresh "last active" at most every 5 minutes to avoid a write on every request.
        if ($user && (! $user->last_active_at || $user->last_active_at->lt(now()->subMinutes(5)))) {
            $user->forceFill(['last_active_at' => now()])->saveQuietly();
        }

        return $next($request);
    }
}
