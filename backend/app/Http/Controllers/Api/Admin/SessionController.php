<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\SemesterName;
use App\Http\Controllers\Api\Admin\Concerns\ManagesRecords;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SessionRequest;
use App\Http\Resources\SessionResource;
use App\Models\AcademicSession;
use App\Models\CourseAssignment;
use App\Models\Registration;
use App\Models\ResultSubmission;
use App\Models\Semester;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Super admin CRUD for academic sessions, plus "set as current".
 * Only one session and one semester can be current. The database enforces it with
 * partial unique indexes, so the old flags are always cleared before the new ones are set.
 */
class SessionController extends Controller
{
    use ManagesRecords;

    public function __construct(private AuditLogger $audit) {}

    /** GET /api/admin/sessions (newest first) */
    public function index(Request $request): array
    {
        $query = $this->applySearch(AcademicSession::query(), ApiResponse::search($request), ['name'])
            ->with('semesters')
            ->orderByDesc('start_date');

        return ApiResponse::paginated($query->paginate(ApiResponse::perPage($request)), SessionResource::class);
    }

    /** GET /api/admin/sessions/{session} */
    public function show(AcademicSession $session): SessionResource
    {
        return new SessionResource($session->load('semesters'));
    }

    /** POST /api/admin/sessions: also creates the First and Second semesters. */
    public function store(SessionRequest $request): JsonResponse
    {
        $session = DB::transaction(function () use ($request) {
            $session = AcademicSession::create($request->columns() + ['is_current' => false]);

            $start = Carbon::parse($session->start_date)->startOfDay();
            $end = Carbon::parse($session->end_date)->startOfDay();

            Semester::create([
                'academic_session_id' => $session->id,
                'name' => SemesterName::First,
                'start_date' => $start->toDateString(),
                'end_date' => $start->copy()->addMonthsNoOverflow(4)->addDays(15)->toDateString(),
                'is_current' => false,
            ]);
            Semester::create([
                'academic_session_id' => $session->id,
                'name' => SemesterName::Second,
                'start_date' => $start->copy()->addMonthsNoOverflow(5)->toDateString(),
                'end_date' => $end->toDateString(),
                'is_current' => false,
            ]);

            return $session;
        });

        $this->audit->record($request->user(), 'RECORD_CREATED', "Session {$session->name}",
            "Created session {$session->name} with First and Second semesters", $session);

        return (new SessionResource($session->load('semesters')))->response()->setStatusCode(201);
    }

    /** PUT /api/admin/sessions/{session} (the current flag is changed only by activate) */
    public function update(SessionRequest $request, AcademicSession $session): SessionResource
    {
        $changes = $this->applyUpdate($session, $request->columns());

        if ($changes !== []) {
            $this->audit->record($request->user(), 'RECORD_UPDATED', "Session {$session->name}",
                'Updated session: '.implode(', ', array_keys($changes)), $session, ['changes' => $changes]);
        }

        return new SessionResource($session->load('semesters'));
    }

    /** DELETE /api/admin/sessions/{session} */
    public function destroy(Request $request, AcademicSession $session): Response|JsonResponse
    {
        if ($session->is_current) {
            return ApiResponse::message("Cannot delete \"{$session->name}\": it is the current session. Set another session as current first.", 422);
        }

        $semesterIds = $session->semesters()->pluck('id');

        $blocked = $this->blockedMessage($session->name, [
            'registration' => Registration::whereIn('semester_id', $semesterIds)->count(),
            'result submission' => ResultSubmission::whereIn('semester_id', $semesterIds)->count(),
            'course assignment' => CourseAssignment::where('academic_session_id', $session->id)->count(),
        ]);
        if ($blocked) {
            return ApiResponse::message($blocked, 422);
        }

        if ($failure = $this->deleteOrBlock($session, $session->name)) {
            return $failure;
        }

        $this->audit->record($request->user(), 'RECORD_DELETED', "Session {$session->name}",
            "Deleted session {$session->name} and its semesters", null, ['id' => $session->id]);

        return response()->noContent();
    }

    /** POST /api/admin/sessions/{session}/activate: the session and its First semester become current. */
    public function activate(Request $request, AcademicSession $session): SessionResource|JsonResponse
    {
        if ($session->is_current) {
            return ApiResponse::message("Session {$session->name} is already the current session.", 422);
        }

        $first = $session->semesters()->orderBy('start_date')->first();
        if (! $first) {
            return ApiResponse::message("Session {$session->name} has no semesters. Add a semester before making it current.", 422);
        }

        $previous = AcademicSession::current()->first();

        DB::transaction(function () use ($session, $first) {
            // Clear every current flag first: the partial unique indexes allow only one.
            Semester::where('is_current', true)->update(['is_current' => false]);
            AcademicSession::where('is_current', true)->update(['is_current' => false]);

            // Write by key, not through the loaded models: their in-memory flags may be stale.
            AcademicSession::whereKey($session->id)->update(['is_current' => true]);
            Semester::whereKey($first->id)->update(['is_current' => true]);
        });

        $this->audit->record($request->user(), 'SESSION_ACTIVATED', "Session {$session->name}",
            'Set as current session'.($previous ? " (previous: {$previous->name})" : ''), $session,
            ['previousSession' => $previous?->name]);

        return new SessionResource($session->refresh()->load('semesters'));
    }
}
