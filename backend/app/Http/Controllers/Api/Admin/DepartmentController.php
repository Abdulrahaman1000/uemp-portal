<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Admin\Concerns\ManagesRecords;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DepartmentRequest;
use App\Http\Resources\DepartmentResource;
use App\Models\Department;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Super admin CRUD for departments. Optional filter: ?facultyId=. */
class DepartmentController extends Controller
{
    use ManagesRecords;

    public function __construct(private AuditLogger $audit) {}

    /** GET /api/admin/departments */
    public function index(Request $request): array
    {
        $query = $this->applySearch(Department::query(), ApiResponse::search($request), ['name', 'code', 'head'])
            ->with('faculty')
            ->withCount('programs')
            ->when(ctype_digit((string) $request->query('facultyId')), fn ($q) => $q->where('faculty_id', (int) $request->query('facultyId')))
            ->orderBy('name');

        return ApiResponse::paginated($query->paginate(ApiResponse::perPage($request)), DepartmentResource::class);
    }

    /** GET /api/admin/departments/{department} */
    public function show(Department $department): DepartmentResource
    {
        return new DepartmentResource($department->load('faculty')->loadCount('programs'));
    }

    /** POST /api/admin/departments */
    public function store(DepartmentRequest $request): JsonResponse
    {
        $department = Department::create($request->columns())->load('faculty');

        $this->audit->record($request->user(), 'RECORD_CREATED', "Department: {$department->name}",
            "Created department {$department->name} ({$department->code}) in {$department->faculty->name}", $department);

        return (new DepartmentResource($department->loadCount('programs')))->response()->setStatusCode(201);
    }

    /** PUT /api/admin/departments/{department} */
    public function update(DepartmentRequest $request, Department $department): DepartmentResource
    {
        $changes = $this->applyUpdate($department, $request->columns());
        $department->load('faculty');

        if ($changes !== []) {
            $this->audit->record($request->user(), 'RECORD_UPDATED', "Department: {$department->name}",
                'Updated department: '.implode(', ', array_keys($changes)), $department, ['changes' => $changes]);
        }

        return new DepartmentResource($department->loadCount('programs'));
    }

    /** DELETE /api/admin/departments/{department} */
    public function destroy(Request $request, Department $department): Response|JsonResponse
    {
        $blocked = $this->blockedMessage($department->name, [
            'program' => $department->programs()->count(),
            'course' => $department->courses()->count(),
            'student' => $department->students()->count(),
            'lecturer' => $department->lecturers()->count(),
            'department admin' => $department->admins()->count(),
        ]);
        if ($blocked) {
            return ApiResponse::message($blocked, 422);
        }

        if ($failure = $this->deleteOrBlock($department, $department->name)) {
            return $failure;
        }

        $this->audit->record($request->user(), 'RECORD_DELETED', "Department: {$department->name}",
            "Deleted department {$department->name} ({$department->code})", null, ['id' => $department->id]);

        return response()->noContent();
    }
}
