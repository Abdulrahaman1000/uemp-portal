<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Admin\Concerns\ManagesRecords;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FacultyRequest;
use App\Http\Resources\FacultyResource;
use App\Models\Faculty;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Super admin CRUD for faculties. Access is limited by the route group (role:super_admin). */
class FacultyController extends Controller
{
    use ManagesRecords;

    public function __construct(private AuditLogger $audit) {}

    /** GET /api/admin/faculties */
    public function index(Request $request): array
    {
        $query = $this->applySearch(Faculty::query(), ApiResponse::search($request), ['name', 'code', 'dean'])
            ->withCount('departments')
            ->orderBy('name');

        return ApiResponse::paginated($query->paginate(ApiResponse::perPage($request)), FacultyResource::class);
    }

    /** GET /api/admin/faculties/{faculty} */
    public function show(Faculty $faculty): FacultyResource
    {
        return new FacultyResource($faculty->loadCount('departments'));
    }

    /** POST /api/admin/faculties */
    public function store(FacultyRequest $request): JsonResponse
    {
        $faculty = Faculty::create($request->columns());

        $this->audit->record($request->user(), 'RECORD_CREATED', "Faculty: {$faculty->name}",
            "Created faculty {$faculty->name} ({$faculty->code})", $faculty);

        return (new FacultyResource($faculty->loadCount('departments')))->response()->setStatusCode(201);
    }

    /** PUT /api/admin/faculties/{faculty} */
    public function update(FacultyRequest $request, Faculty $faculty): FacultyResource
    {
        $changes = $this->applyUpdate($faculty, $request->columns());

        if ($changes !== []) {
            $this->audit->record($request->user(), 'RECORD_UPDATED', "Faculty: {$faculty->name}",
                'Updated faculty: '.implode(', ', array_keys($changes)), $faculty, ['changes' => $changes]);
        }

        return new FacultyResource($faculty->loadCount('departments'));
    }

    /** DELETE /api/admin/faculties/{faculty} */
    public function destroy(Request $request, Faculty $faculty): Response|JsonResponse
    {
        $blocked = $this->blockedMessage($faculty->name, ['department' => $faculty->departments()->count()]);
        if ($blocked) {
            return ApiResponse::message($blocked, 422);
        }

        if ($failure = $this->deleteOrBlock($faculty, $faculty->name)) {
            return $failure;
        }

        $this->audit->record($request->user(), 'RECORD_DELETED', "Faculty: {$faculty->name}",
            "Deleted faculty {$faculty->name} ({$faculty->code})", null, ['id' => $faculty->id]);

        return response()->noContent();
    }
}
