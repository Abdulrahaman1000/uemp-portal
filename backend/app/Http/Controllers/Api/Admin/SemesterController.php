<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Admin\Concerns\ManagesRecords;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SemesterRequest;
use App\Http\Resources\SemesterResource;
use App\Models\AcademicSession;
use App\Models\Registration;
use App\Models\ResultSubmission;
use App\Models\Semester;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/** Super admin CRUD for semesters. Optional filter: ?sessionId=. */
class SemesterController extends Controller
{
    use ManagesRecords;

    public function __construct(private AuditLogger $audit) {}

    private function label(Semester $semester): string
    {
        return "{$semester->session->name} {$semester->name->value} semester";
    }

    /** GET /api/admin/semesters */
    public function index(Request $request): array
    {
        $query = Semester::query()
            ->with('session')
            ->when(ctype_digit((string) $request->query('sessionId')), fn ($q) => $q->where('academic_session_id', (int) $request->query('sessionId')))
            ->orderByDesc('start_date');

        return ApiResponse::paginated($query->paginate(ApiResponse::perPage($request)), SemesterResource::class);
    }

    /** GET /api/admin/semesters/{semester} */
    public function show(Semester $semester): SemesterResource
    {
        return new SemesterResource($semester);
    }

    /** POST /api/admin/semesters */
    public function store(SemesterRequest $request): JsonResponse
    {
        $semester = Semester::create($request->columns() + ['is_current' => false])->load('session');

        $this->audit->record($request->user(), 'RECORD_CREATED', $this->label($semester),
            "Created {$this->label($semester)}", $semester);

        return (new SemesterResource($semester))->response()->setStatusCode(201);
    }

    /** PUT /api/admin/semesters/{semester} */
    public function update(SemesterRequest $request, Semester $semester): SemesterResource
    {
        $changes = $this->applyUpdate($semester, $request->columns());
        $semester->load('session');

        if ($changes !== []) {
            $this->audit->record($request->user(), 'RECORD_UPDATED', $this->label($semester),
                'Updated semester: '.implode(', ', array_keys($changes)), $semester, ['changes' => $changes]);
        }

        return new SemesterResource($semester);
    }

    /** DELETE /api/admin/semesters/{semester} */
    public function destroy(Request $request, Semester $semester): Response|JsonResponse
    {
        $label = $this->label($semester->load('session'));

        if ($semester->is_current) {
            return ApiResponse::message("Cannot delete \"{$label}\": it is the current semester. Set another semester as current first.", 422);
        }

        $blocked = $this->blockedMessage($label, [
            'registration' => Registration::where('semester_id', $semester->id)->count(),
            'result submission' => ResultSubmission::where('semester_id', $semester->id)->count(),
        ]);
        if ($blocked) {
            return ApiResponse::message($blocked, 422);
        }

        if ($failure = $this->deleteOrBlock($semester, $label)) {
            return $failure;
        }

        $this->audit->record($request->user(), 'RECORD_DELETED', $label, "Deleted {$label}", null, ['id' => $semester->id]);

        return response()->noContent();
    }

    /** POST /api/admin/semesters/{semester}/activate: the semester and its session become current. */
    public function activate(Request $request, Semester $semester): SemesterResource|JsonResponse
    {
        $semester->load('session');
        $label = $this->label($semester);

        if ($semester->is_current) {
            return ApiResponse::message("The {$label} is already current.", 422);
        }

        DB::transaction(function () use ($semester) {
            // Clear every current flag first: the partial unique indexes allow only one.
            Semester::where('is_current', true)->update(['is_current' => false]);
            AcademicSession::where('is_current', true)->update(['is_current' => false]);

            // Write by key, not through the loaded models: their in-memory flags are now stale,
            // so model->update() could see "no change" and skip the write.
            AcademicSession::whereKey($semester->academic_session_id)->update(['is_current' => true]);
            Semester::whereKey($semester->id)->update(['is_current' => true]);
        });

        $this->audit->record($request->user(), 'SEMESTER_ACTIVATED', $label, 'Set as current semester', $semester);

        return new SemesterResource($semester->refresh());
    }
}
