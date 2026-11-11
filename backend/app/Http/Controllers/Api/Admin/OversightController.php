<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\ResultStatus;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Http\Resources\LecturerResource;
use App\Http\Resources\RegistrationResource;
use App\Http\Resources\StudentResource;
use App\Http\Resources\SubmissionResource;
use App\Models\AuditLog;
use App\Models\Lecturer;
use App\Models\Registration;
use App\Models\ResultSubmission;
use App\Models\Student;
use App\Support\ApiResponse;
use App\Support\LikeSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Super Admin: read-only university-wide lists (T2.5d). Nothing here writes data.
 */
class OversightController extends Controller
{
    /** GET /api/admin/students?search=&departmentId=&programId=&level=&status= */
    public function students(Request $request): array
    {
        $query = Student::query()
            ->with(['user', 'department.faculty', 'program'])
            ->join('users', 'users.id', '=', 'students.user_id')
            ->select('students.*')
            ->orderBy('users.name');

        $term = ApiResponse::search($request);
        if ($term !== null && $term !== '') {
            $query->where(function (Builder $q) use ($term) {
                $q->where(fn (Builder $m) => LikeSearch::apply($m, $term, ['students.matric_no', 'users.name', 'users.email']));
            });
        }
        $this->whereId($query, $request, 'departmentId', 'students.department_id');
        $this->whereId($query, $request, 'programId', 'students.program_id');
        $this->whereId($query, $request, 'level', 'students.level');

        $status = $request->query('status');
        if ($status === 'graduated') {
            $query->whereNotNull('students.graduated_at');
        } elseif (is_string($status) && UserStatus::tryFrom($status)) {
            $query->whereNull('students.graduated_at')->where('users.status', $status);
        }

        return ApiResponse::paginated($query->paginate(ApiResponse::perPage($request)), StudentResource::class);
    }

    /** GET /api/admin/lecturers?search=&departmentId=&status= */
    public function lecturers(Request $request): array
    {
        // select() must come BEFORE withCount(): a later select() would drop the count column.
        $query = Lecturer::query()
            ->select('lecturers.*')
            ->with(['user', 'department'])
            ->withCount(['assignments as course_count' => fn (Builder $q) => $q->whereHas('session', fn (Builder $s) => $s->where('is_current', true))])
            ->join('users', 'users.id', '=', 'lecturers.user_id')
            ->orderBy('users.name');

        LikeSearch::apply($query, ApiResponse::search($request), ['users.name', 'users.email', 'lecturers.specialization']);
        $this->whereId($query, $request, 'departmentId', 'lecturers.department_id');

        $status = $request->query('status');
        if (is_string($status) && UserStatus::tryFrom($status)) {
            $query->where('users.status', $status);
        }

        return ApiResponse::paginated($query->paginate(ApiResponse::perPage($request)), LecturerResource::class);
    }

    /** GET /api/admin/registrations?search=&level=&departmentId=&semesterId= */
    public function registrations(Request $request): array
    {
        $query = Registration::query()
            ->with(['student.user', 'student.department', 'semester.session', 'courses'])
            ->join('students', 'students.id', '=', 'registrations.student_id')
            ->join('users', 'users.id', '=', 'students.user_id')
            ->join('departments', 'departments.id', '=', 'students.department_id')
            ->select('registrations.*')
            ->orderByDesc('registrations.registered_at')
            ->orderByDesc('registrations.id');

        LikeSearch::apply($query, ApiResponse::search($request), ['users.name', 'students.matric_no', 'departments.name']);
        $this->whereId($query, $request, 'level', 'students.level');
        $this->whereId($query, $request, 'departmentId', 'students.department_id');
        $this->whereId($query, $request, 'semesterId', 'registrations.semester_id');

        return ApiResponse::paginated($query->paginate(ApiResponse::perPage($request)), RegistrationResource::class);
    }

    /** GET /api/admin/submissions?status=&search=&departmentId= */
    public function submissions(Request $request): array
    {
        // select() must come BEFORE withCount(): a later select() would drop the count column.
        $query = ResultSubmission::query()
            ->select('result_submissions.*')
            ->with([
                'course.department',
                'lecturer.user',
                'semester.session',
                'auditLogs' => fn ($q) => $q->orderBy('created_at')->orderBy('id'),
            ])
            ->withCount('results as student_count')
            ->join('courses', 'courses.id', '=', 'result_submissions.course_id')
            ->orderByDesc('result_submissions.updated_at')
            ->orderByDesc('result_submissions.id');

        LikeSearch::apply($query, ApiResponse::search($request), ['courses.code', 'courses.title']);
        $this->whereId($query, $request, 'departmentId', 'courses.department_id');

        $status = $request->query('status');
        if (is_string($status) && ResultStatus::tryFrom($status)) {
            $query->where('result_submissions.status', $status);
        }

        return ApiResponse::paginated($query->paginate(ApiResponse::perPage($request)), SubmissionResource::class);
    }

    /** GET /api/admin/activity-logs?user=&action=&from=YYYY-MM-DD&to=YYYY-MM-DD */
    public function activityLogs(Request $request): array
    {
        $query = AuditLog::query()->orderByDesc('created_at')->orderByDesc('id');

        LikeSearch::apply($query, $this->text($request, 'user'), ['user_name']);
        LikeSearch::apply($query, $this->text($request, 'action'), ['action']);

        foreach (['from' => '>=', 'to' => '<='] as $key => $operator) {
            $date = $request->query($key);
            if (is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1) {
                $query->whereDate('created_at', $operator, $date);
            }
        }

        return ApiResponse::paginated($query->paginate(ApiResponse::perPage($request)), AuditLogResource::class);
    }

    /** Apply an exact-match filter when the query value is a whole number (ids and levels). */
    private function whereId(Builder $query, Request $request, string $param, string $column): void
    {
        $value = $request->query($param);
        if (is_string($value) && ctype_digit($value)) {
            $query->where($column, (int) $value);
        }
    }

    private function text(Request $request, string $key): ?string
    {
        $value = $request->query($key);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
