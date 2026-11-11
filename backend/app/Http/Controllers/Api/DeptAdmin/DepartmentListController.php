<?php

namespace App\Http\Controllers\Api\DeptAdmin;

use App\Enums\UserStatus;
use App\Http\Resources\CourseResource;
use App\Http\Resources\LecturerResource;
use App\Http\Resources\RegistrationResource;
use App\Http\Resources\StudentResource;
use App\Models\Course;
use App\Models\Lecturer;
use App\Models\Registration;
use App\Models\Student;
use App\Support\ApiResponse;
use App\Support\DepartmentScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/** Department admin: read-only lists, always limited to the admin's own department. */
class DepartmentListController extends DeptAdminController
{
    /** GET /api/dept/students?search=&programId=&level=&status= */
    public function students(Request $request): array
    {
        $query = Student::query()
            ->with(['user', 'department.faculty', 'program'])
            ->join('users', 'users.id', '=', 'students.user_id')
            ->select('students.*')
            ->orderBy('users.name');

        DepartmentScope::apply($query, $request->user(), 'students.department_id');
        $this->search($query, $request, ['students.matric_no', 'users.name', 'users.email']);
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

    /** GET /api/dept/lecturers?search=&status= */
    public function lecturers(Request $request): array
    {
        // select() must come BEFORE withCount(): a later select() would drop the count column.
        $query = Lecturer::query()
            ->select('lecturers.*')
            ->with(['user', 'department'])
            ->withCount(['assignments as course_count' => fn (Builder $q) => $q->whereHas('session', fn (Builder $s) => $s->where('is_current', true))])
            ->join('users', 'users.id', '=', 'lecturers.user_id')
            ->orderBy('users.name');

        DepartmentScope::apply($query, $request->user(), 'lecturers.department_id');
        $this->search($query, $request, ['users.name', 'users.email', 'lecturers.specialization']);

        $status = $request->query('status');
        if (is_string($status) && UserStatus::tryFrom($status)) {
            $query->where('users.status', $status);
        }

        return ApiResponse::paginated($query->paginate(ApiResponse::perPage($request)), LecturerResource::class);
    }

    /** GET /api/dept/courses?search=&level=&semester= */
    public function courses(Request $request): array
    {
        $query = Course::query()
            ->with(['department', 'currentAssignment.lecturer.user'])
            ->orderBy('code');

        DepartmentScope::apply($query, $request->user(), 'courses.department_id');
        $this->search($query, $request, ['courses.code', 'courses.title']);
        $this->whereId($query, $request, 'level', 'courses.level');

        if (in_array($request->query('semester'), ['First', 'Second'], true)) {
            $query->where('courses.semester', $request->query('semester'));
        }

        return ApiResponse::paginated($query->paginate(ApiResponse::perPage($request)), CourseResource::class);
    }

    /** GET /api/dept/registrations?search=&level=&semesterId= */
    public function registrations(Request $request): array
    {
        $query = Registration::query()
            ->with(['student.user', 'student.department', 'semester.session', 'courses'])
            ->join('students', 'students.id', '=', 'registrations.student_id')
            ->join('users', 'users.id', '=', 'students.user_id')
            ->select('registrations.*')
            ->orderByDesc('registrations.registered_at')
            ->orderByDesc('registrations.id');

        DepartmentScope::apply($query, $request->user(), 'students.department_id');
        $this->search($query, $request, ['users.name', 'students.matric_no']);
        $this->whereId($query, $request, 'level', 'students.level');
        $this->whereId($query, $request, 'semesterId', 'registrations.semester_id');

        return ApiResponse::paginated($query->paginate(ApiResponse::perPage($request)), RegistrationResource::class);
    }
}
