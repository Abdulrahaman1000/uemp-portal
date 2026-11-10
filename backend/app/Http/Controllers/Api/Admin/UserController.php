<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use App\Support\LikeSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Super Admin: user accounts, status and roles (T2.5c).
 * Student accounts cannot change role (they own academic records); roles that need a department
 * or a lecturer profile are refused with a clear message instead of leaving a half-configured user.
 */
class UserController extends Controller
{
    private const WITH = ['department', 'student.department', 'lecturer.department'];

    public function __construct(private readonly AuditLogger $audit)
    {
    }

    /** GET /api/admin/users?search=&role=&status=&page=&per_page= */
    public function index(Request $request): array
    {
        $query = User::query()->with(self::WITH)->orderBy('name');

        LikeSearch::apply($query, ApiResponse::search($request), ['name', 'email']);

        $role = $request->query('role');
        if (is_string($role) && UserRole::tryFrom($role)) {
            $query->where('role', $role);
        }
        $status = $request->query('status');
        if (is_string($status) && UserStatus::tryFrom($status)) {
            $query->where('status', $status);
        }

        return ApiResponse::paginated($query->paginate(ApiResponse::perPage($request)), UserResource::class);
    }

    /** PATCH /api/admin/users/{user}/status  body: {status} */
    public function updateStatus(Request $request, User $user): UserResource
    {
        $data = $request->validate(['status' => ['required', Rule::in(UserStatus::values())]]);

        if ($user->is($request->user())) {
            throw ValidationException::withMessages(['status' => 'You cannot change your own status.']);
        }

        $status = UserStatus::from($data['status']);

        if ($user->status !== $status) {
            DB::transaction(function () use ($request, $user, $status) {
                $user->update(['status' => $status]);

                // A deactivated or suspended user is signed out everywhere straight away.
                if ($status !== UserStatus::Active) {
                    $user->tokens()->delete();
                }

                $this->audit->record($request->user(), 'USER_STATUS_CHANGED', $user->name, "Status set to {$status->value}", $user);
            });
        }

        return new UserResource($user->fresh(self::WITH));
    }

    /** PATCH /api/admin/users/{user}/role  body: {role, departmentId?} */
    public function updateRole(Request $request, User $user): UserResource
    {
        $data = $request->validate([
            'role' => ['required', Rule::in(UserRole::values())],
            'departmentId' => ['nullable', 'integer', 'exists:departments,id'],
        ]);

        if ($user->is($request->user())) {
            throw ValidationException::withMessages(['role' => 'You cannot change your own role.']);
        }

        $from = $user->role;
        $to = UserRole::from($data['role']);

        if ($from === $to) {
            return new UserResource($user->load(self::WITH));
        }

        if ($from === UserRole::Student || $to === UserRole::Student) {
            throw ValidationException::withMessages([
                'role' => 'Student accounts cannot be converted: student records are created and managed from the department pages.',
            ]);
        }

        $user->loadMissing('lecturer');

        if ($to === UserRole::Lecturer && ! $user->lecturer) {
            throw ValidationException::withMessages(['role' => 'This user has no lecturer profile, so they cannot become a lecturer.']);
        }

        // Only department admins are scoped through users.department_id.
        $departmentId = null;
        if ($to === UserRole::DeptAdmin) {
            $departmentId = isset($data['departmentId'])
                ? (int) $data['departmentId']
                : $user->lecturer?->department_id;

            if (! $departmentId) {
                throw ValidationException::withMessages(['departmentId' => 'Choose a department for the new department admin.']);
            }
        }

        DB::transaction(function () use ($request, $user, $to, $departmentId) {
            $user->update(['role' => $to, 'department_id' => $departmentId]);

            $this->audit->record($request->user(), 'USER_ROLE_CHANGED', $user->name, "Role set to {$to->value}", $user);
        });

        return new UserResource($user->fresh(self::WITH));
    }
}
