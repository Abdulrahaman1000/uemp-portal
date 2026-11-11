<?php

namespace App\Http\Controllers\Api\DeptAdmin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Resources\LecturerResource;
use App\Http\Resources\StudentResource;
use App\Models\Lecturer;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\DepartmentScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Department admin: create, edit and (de)activate the students and lecturers of their own
 * department. The department is never read from the request. Every change is audited.
 */
class DepartmentPeopleController extends DeptAdminController
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    // ----- students -----

    /** POST /api/dept/students */
    public function storeStudent(Request $request): JsonResponse
    {
        $actor = $request->user();
        $departmentId = $this->departmentId($actor);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:100'],
            'matricNo' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9\/\-]+$/', 'unique:students,matric_no'],
            'programId' => ['required', 'integer', 'exists:programs,id'],
            'level' => ['required', 'integer', Rule::in([100, 200, 300, 400, 500, 600])],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\-\s]{6,30}$/'],
        ]);
        $this->programInDepartment((int) $data['programId'], $departmentId);

        $student = DB::transaction(function () use ($data, $departmentId, $actor) {
            $user = User::create([
                'name' => trim($data['name']),
                'email' => strtolower($data['email']),
                'password' => $data['password'],
                'role' => UserRole::Student,
                'status' => UserStatus::Active,
            ]);
            $student = Student::create([
                'user_id' => $user->id,
                'matric_no' => strtoupper($data['matricNo']),
                'department_id' => $departmentId,
                'program_id' => (int) $data['programId'],
                'level' => (int) $data['level'],
                'phone' => $data['phone'] ?? null,
            ]);
            $this->audit->record($actor, 'RECORD_CREATED', $student->matric_no, "Created student {$user->name} ({$student->matric_no})", $student);

            return $student;
        });

        return (new StudentResource($this->freshStudent($student)))->response()->setStatusCode(201);
    }

    /** PUT /api/dept/students/{student}  (matric number and department cannot change) */
    public function updateStudent(Request $request, Student $student): StudentResource
    {
        $actor = $request->user();
        DepartmentScope::authorize($actor, $student->department_id);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($student->user_id)],
            'programId' => ['required', 'integer', 'exists:programs,id'],
            'level' => ['required', 'integer', Rule::in([100, 200, 300, 400, 500, 600])],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\-\s]{6,30}$/'],
        ]);
        $this->programInDepartment((int) $data['programId'], (int) $student->department_id);

        DB::transaction(function () use ($data, $student, $actor) {
            $student->user->fill(['name' => trim($data['name']), 'email' => strtolower($data['email'])]);
            $student->fill(['program_id' => (int) $data['programId'], 'level' => (int) $data['level'], 'phone' => $data['phone'] ?? null]);
            $changed = array_merge(array_keys($student->user->getDirty()), array_keys($student->getDirty()));
            $student->user->save();
            $student->save();
            if ($changed !== []) {
                $this->audit->record($actor, 'RECORD_UPDATED', $student->matric_no, "Updated student {$student->matric_no} (".implode(', ', $changed).')', $student);
            }
        });

        return new StudentResource($this->freshStudent($student));
    }

    /** PATCH /api/dept/students/{student}/status */
    public function studentStatus(Request $request, Student $student): StudentResource
    {
        DepartmentScope::authorize($request->user(), $student->department_id);
        $this->changeStatus($request, $student->user, $student->matric_no);

        return new StudentResource($this->freshStudent($student));
    }

    // ----- lecturers -----

    /** POST /api/dept/lecturers */
    public function storeLecturer(Request $request): JsonResponse
    {
        $actor = $request->user();
        $departmentId = $this->departmentId($actor);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:100'],
            'specialization' => ['nullable', 'string', 'max:255'],
        ]);

        $lecturer = DB::transaction(function () use ($data, $departmentId, $actor) {
            $user = User::create([
                'name' => trim($data['name']),
                'email' => strtolower($data['email']),
                'password' => $data['password'],
                'role' => UserRole::Lecturer,
                'status' => UserStatus::Active,
            ]);
            $lecturer = Lecturer::create([
                'user_id' => $user->id,
                'department_id' => $departmentId,
                'specialization' => $data['specialization'] ?? null,
            ]);
            $this->audit->record($actor, 'RECORD_CREATED', $user->name, "Created lecturer {$user->name}", $lecturer);

            return $lecturer;
        });

        return (new LecturerResource($this->freshLecturer($lecturer)))->response()->setStatusCode(201);
    }

    /** PUT /api/dept/lecturers/{lecturer} */
    public function updateLecturer(Request $request, Lecturer $lecturer): LecturerResource
    {
        $actor = $request->user();
        DepartmentScope::authorize($actor, $lecturer->department_id);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($lecturer->user_id)],
            'specialization' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($data, $lecturer, $actor) {
            $lecturer->user->fill(['name' => trim($data['name']), 'email' => strtolower($data['email'])]);
            $lecturer->fill(['specialization' => $data['specialization'] ?? null]);
            $changed = array_merge(array_keys($lecturer->user->getDirty()), array_keys($lecturer->getDirty()));
            $lecturer->user->save();
            $lecturer->save();
            if ($changed !== []) {
                $this->audit->record($actor, 'RECORD_UPDATED', $lecturer->user->name, "Updated lecturer {$lecturer->user->name} (".implode(', ', $changed).')', $lecturer);
            }
        });

        return new LecturerResource($this->freshLecturer($lecturer));
    }

    /** PATCH /api/dept/lecturers/{lecturer}/status */
    public function lecturerStatus(Request $request, Lecturer $lecturer): LecturerResource
    {
        DepartmentScope::authorize($request->user(), $lecturer->department_id);
        $this->changeStatus($request, $lecturer->user, $lecturer->user->name);

        return new LecturerResource($this->freshLecturer($lecturer));
    }

    // ----- programs (read only) -----

    /** GET /api/dept/programs: the programs (and so the levels' programs) of this department. */
    public function programs(Request $request): array
    {
        $query = Program::query()->with('department')->withCount('students')->orderBy('name');
        DepartmentScope::apply($query, $request->user(), 'programs.department_id');

        return \App\Support\ApiResponse::paginated($query->paginate(\App\Support\ApiResponse::perPage($request)), \App\Http\Resources\ProgramResource::class);
    }

    // ----- helpers -----

    private function departmentId(User $actor): int
    {
        $id = $actor->resolveDepartmentId();
        abort_if($id === null, 403, 'Your account is not linked to a department.');

        return (int) $id;
    }

    private function programInDepartment(int $programId, int $departmentId): void
    {
        if (! Program::whereKey($programId)->where('department_id', $departmentId)->exists()) {
            throw ValidationException::withMessages(['programId' => 'The selected program does not belong to your department.']);
        }
    }

    private function changeStatus(Request $request, User $target, string $label): void
    {
        $actor = $request->user();
        $data = $request->validate(['status' => ['required', Rule::in(UserStatus::values())]]);
        $new = UserStatus::from($data['status']);
        if ($target->status === $new) {
            return;
        }
        $old = $target->status->value;
        DB::transaction(function () use ($target, $new, $actor, $label, $old) {
            $target->update(['status' => $new]);
            if ($new !== UserStatus::Active) {
                $target->tokens()->delete(); // sign the account out everywhere
            }
            $this->audit->record($actor, 'USER_STATUS_CHANGED', $label, "Changed {$label} from {$old} to {$new->value}", $target);
        });
    }

    private function freshStudent(Student $student): Student
    {
        return $student->fresh(['user', 'department.faculty', 'program']);
    }

    private function freshLecturer(Lecturer $lecturer): Lecturer
    {
        return Lecturer::query()->with(['user', 'department'])
            ->withCount(['assignments as course_count' => fn ($q) => $q->whereHas('session', fn ($s) => $s->where('is_current', true))])
            ->findOrFail($lecturer->id);
    }
}
