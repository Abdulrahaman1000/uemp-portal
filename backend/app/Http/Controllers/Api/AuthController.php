<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::with(['department', 'student.department', 'lecturer.department'])
            ->where('email', strtolower(trim($data['email'])))
            ->first();

        // Unknown emails still pay for one hash, so response time does not reveal which emails exist.
        $passwordOk = $user
            ? Hash::check($data['password'], $user->password)
            : (Hash::make($data['password']) && false);

        if (! $user || ! $passwordOk) {
            if ($user) {
                $audit->record($user, 'LOGIN_FAILED', $user->name, 'Wrong password');
            }

            return response()->json(['message' => 'Invalid email or password.'], 401);
        }

        if ($user->status !== UserStatus::Active) {
            $audit->record($user, 'LOGIN_BLOCKED', $user->name, "Account is {$user->status->value}");

            return response()->json(['message' => 'This account is not active. Contact the administrator.'], 403);
        }

        $token = $user->createToken('api', ['*'], now()->addHours(12))->plainTextToken;
        $user->forceFill(['last_active_at' => now()])->save();
        $audit->record($user, 'USER_LOGIN', $user->name, 'Signed in');

        return response()->json([
            'token' => $token,
            'user' => (new UserResource($user))->resolve($request),
        ]);
    }

    public function logout(Request $request, AuditLogger $audit): JsonResponse
    {
        $user = $request->user();
        $request->user()->currentAccessToken()->delete();
        $audit->record($user, 'USER_LOGOUT', $user->name, 'Signed out');

        return response()->json(null, 204);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user()->load(['department', 'student.department', 'lecturer.department']));
    }
}
