<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Department;
use App\Models\Lecturer;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Super Admin: create staff accounts and assign users to departments.
 * Student accounts are not created here: they need a program and a matric number, so the
 * department admin creates them. Every change is audited.
 */
class UserManagementController extends Controller
{
    private const WITH = ['department', 'student.department', 'lecturer.department'];

    public function __construct(private readonly AuditLogger $audit)
    {
    }

    /** POST /api/admin/users  body: {name, email, password, role, departmentId?, specialization?} */
    public function store(Request $request): JsonResponse
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => strtolower(trim($request->input('email')))]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:100'],
            'role' => ['required', Rule::in([UserRole::SuperAdmin->value, UserRole::DeptAdmin->value, UserRole::Lecturer->value])],
            'departmentId' => ['nullable', 'integer', 'exists:departments,id'],
            'specialization' => ['nullable', 'string', 'max:255'],
        ], [
            'role.in' => 'Choose Super admin, Department admin or Lecturer. Student accounts are created by the department admin.',
        ]);

        $role = UserRole::from($data['role']);
        $departmentId = isset($data['departmentId']) ? (int) $data['departmentId'] : null;

        if ($role !== UserRole::SuperAdmin && $departmentId === null) {
            throw ValidationException::withMessages(['departmentId' => 'Choose a department for this user.']);
        }

        $actor = $request->user();

        $user = DB::transaction(function () use ($data, $role, $departmentId, $actor) {
            $user = User::create([
                'name' => trim($data['name']),
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => $role,
                'status' => UserStatus::Active,
                // Only department admins are scoped through users.department_id.
                'department_id' => $role === UserRole::DeptAdmin ? $departmentId : null,
            ]);

            if ($role === UserRole::Lecturer) {
                Lecturer::create([
                    'user_id' => $user->id,
                    'department_id' => $departmentId,
                    'specialization' => $data['specialization'] ?? null,
                ]);
            }

            $this->audit->record($actor, 'USER_CREATED', $user->name, "Created {$role->value} account {$user->email}", $user);

            return $user;
        });

        return (new UserResource($user->fresh(self::WITH)))->response()->setStatusCode(201);
    }

    /** PATCH /api/admin/users/{user}/department  body: {departmentId} (lecturers and department admins only) */
    public function updateDepartment(Request $request, User $user): UserResource
    {
        $data = $request->validate(['departmentId' => ['required', 'integer', 'exists:departments,id']]);
        $departmentId = (int) $data['departmentId'];

        if (! in_array($user->role, [UserRole::Lecturer, UserRole::DeptAdmin], true)) {
            throw ValidationException::withMessages([
                'departmentId' => 'Only lecturers and department admins can be moved to another department.',
            ]);
        }

        $user->loadMissing('lecturer');

        if ($user->role === UserRole::Lecturer && ! $user->lecturer) {
            throw ValidationException::withMessages(['departmentId' => 'This user has no lecturer profile.']);
        }

        $currentId = $user->resolveDepartmentId();
        if ($currentId !== null && (int) $currentId === $departmentId) {
            return new UserResource($user->load(self::WITH));
        }

        if ($user->role === UserRole::Lecturer) {
            $this->guardLecturerMove($user->lecturer);
        }

        $from = Department::find($currentId)?->name ?? 'no department';
        $to = Department::findOrFail($departmentId)->name;

        DB::transaction(function () use ($request, $user, $departmentId, $from, $to) {
            if ($user->role === UserRole::Lecturer) {
                $user->lecturer->update(['department_id' => $departmentId]);
            } else {
                $user->update(['department_id' => $departmentId]);
            }

            $this->audit->record($request->user(), 'USER_DEPARTMENT_CHANGED', $user->name, "Moved {$user->name} from {$from} to {$to}", $user);
        });

        return new UserResource($user->fresh(self::WITH));
    }

    /**
     * A lecturer must belong to the same department as the courses they teach, so a lecturer with
     * current assignments or unfinished result sheets cannot be moved until that work is cleared.
     */
    private function guardLecturerMove(Lecturer $lecturer): void
    {
        $hasCurrentCourses = $lecturer->assignments()
            ->whereHas('session', fn ($session) => $session->where('is_current', true))
            ->exists();
        if ($hasCurrentCourses) {
            throw ValidationException::withMessages([
                'departmentId' => 'This lecturer still has courses assigned in the current session. Remove the assignments first.',
            ]);
        }

        $hasOpenResults = $lecturer->resultSubmissions()->where('status', '!=', 'approved')->exists();
        if ($hasOpenResults) {
            throw ValidationException::withMessages([
                'departmentId' => 'This lecturer has result sheets that are not approved yet. Finish or reassign them first.',
            ]);
        }
    }
}
