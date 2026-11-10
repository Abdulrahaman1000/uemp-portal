<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Admin\Concerns\ManagesRecords;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProgramRequest;
use App\Http\Resources\ProgramResource;
use App\Models\Program;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

/** Super admin CRUD for programs. Optional filter: ?departmentId=. */
class ProgramController extends Controller
{
    use ManagesRecords;

    public function __construct(private AuditLogger $audit) {}

    /** GET /api/admin/programs */
    public function index(Request $request): array
    {
        $query = $this->applySearch(Program::query(), ApiResponse::search($request), ['name', 'award'])
            ->with('department')
            ->withCount('students')
            ->when(ctype_digit((string) $request->query('departmentId')), fn ($q) => $q->where('department_id', (int) $request->query('departmentId')))
            ->orderBy('name');

        return ApiResponse::paginated($query->paginate(ApiResponse::perPage($request)), ProgramResource::class);
    }

    /** GET /api/admin/programs/{program} */
    public function show(Program $program): ProgramResource
    {
        return new ProgramResource($program->load('department')->loadCount('students'));
    }

    /** POST /api/admin/programs */
    public function store(ProgramRequest $request): JsonResponse
    {
        $program = Program::create($request->columns())->load('department');

        $this->audit->record($request->user(), 'RECORD_CREATED', "Program: {$program->name}",
            "Created program {$program->name} ({$program->award}) in {$program->department->name}", $program);

        return (new ProgramResource($program->loadCount('students')))->response()->setStatusCode(201);
    }

    /** PUT /api/admin/programs/{program} */
    public function update(ProgramRequest $request, Program $program): ProgramResource
    {
        $data = $request->columns();

        // Students and courses are tied to the program's department, so it cannot move while they exist.
        if ($data['department_id'] !== (int) $program->department_id
            && ($program->students()->exists() || $program->courses()->exists())) {
            throw ValidationException::withMessages([
                'departmentId' => 'This program has students or courses, so it cannot be moved to another department.',
            ]);
        }

        $changes = $this->applyUpdate($program, $data);
        $program->load('department');

        if ($changes !== []) {
            $this->audit->record($request->user(), 'RECORD_UPDATED', "Program: {$program->name}",
                'Updated program: '.implode(', ', array_keys($changes)), $program, ['changes' => $changes]);
        }

        return new ProgramResource($program->loadCount('students'));
    }

    /** DELETE /api/admin/programs/{program} */
    public function destroy(Request $request, Program $program): Response|JsonResponse
    {
        $blocked = $this->blockedMessage($program->name, [
            'student' => $program->students()->count(),
            'course' => $program->courses()->count(),
        ]);
        if ($blocked) {
            return ApiResponse::message($blocked, 422);
        }

        if ($failure = $this->deleteOrBlock($program, $program->name)) {
            return $failure;
        }

        $this->audit->record($request->user(), 'RECORD_DELETED', "Program: {$program->name}",
            "Deleted program {$program->name} ({$program->award})", null, ['id' => $program->id]);

        return response()->noContent();
    }
}
