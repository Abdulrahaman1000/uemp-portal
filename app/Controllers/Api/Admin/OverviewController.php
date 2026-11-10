<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\ResultStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\Lecturer;
use App\Models\Program;
use App\Models\Registration;
use App\Models\ResultSubmission;
use App\Models\Semester;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Super Admin read-only overview endpoints: dashboard, grading scale, roles and reports.
 * Access is limited to super admins by the route group (role:super_admin).
 * Response keys match frontend/client/types/portal.ts exactly.
 */
class OverviewController extends Controller
{
    /** GET /api/admin/dashboard */
    public function dashboard(): JsonResponse
    {
        $session = AcademicSession::current()->first();
        $semester = Semester::current()->first();

        return response()->json([
            'totalStudents' => Student::count(),
            'totalLecturers' => Lecturer::count(),
            'totalFaculties' => Faculty::count(),
            'totalDepartments' => Department::count(),
            'activePrograms' => Program::count(),
            'pendingSubmissions' => ResultSubmission::where('status', ResultStatus::Submitted->value)->count(),
            'currentSession' => $session?->name ?? 'None',
            'currentSemester' => $semester?->name?->value ?? 'First',
        ]);
    }

    /** GET /api/admin/grading-scale (read from config/grading.php) */
    public function gradingScale(): JsonResponse
    {
        $bands = collect(config('grading.bands'))
            ->map(fn (array $band) => [
                'min' => (int) $band['min'],
                'max' => (int) $band['max'],
                'grade' => (string) $band['grade'],
                'point' => $band['point'] + 0,
            ])
            ->values()
            ->all();

        return response()->json([
            'caMax' => (int) config('grading.ca_max'),
            'examMax' => (int) config('grading.exam_max'),
            'bands' => $bands,
            'gpaFormula' => (string) config('grading.gpa_formula'),
        ]);
    }

    /** GET /api/admin/roles: the four roles with a user count (read-only permission matrix). */
    public function roles(): JsonResponse
    {
        $counts = DB::table('users')
            ->selectRaw('role, count(*) as total')
            ->groupBy('role')
            ->pluck('total', 'role');

        $roles = collect(UserRole::cases())
            ->map(fn (UserRole $role) => [
                'role' => $role->value,
                'label' => $role->label(),
                'description' => (string) config("roles.{$role->value}.description", ''),
                'userCount' => (int) ($counts[$role->value] ?? 0),
                'permissions' => array_values(config("roles.{$role->value}.permissions", [])),
            ])
            ->values()
            ->all();

        return response()->json($roles);
    }

    /** GET /api/admin/reports/{students|registrations|results} returns [{label, value}] */
    public function report(string $type): JsonResponse
    {
        $rows = match ($type) {
            'students' => $this->studentsPerDepartment(),
            'registrations' => $this->registrationsPerDepartment(),
            'results' => $this->resultsPerStatus(),
            default => abort(404, 'Unknown report type.'),
        };

        return response()->json($rows);
    }

    /** @return list<array{label: string, value: int}> */
    private function studentsPerDepartment(): array
    {
        return Department::query()
            ->withCount('students')
            ->orderBy('name')
            ->get()
            ->map(fn (Department $department) => [
                'label' => $department->name,
                'value' => (int) $department->students_count,
            ])
            ->values()
            ->all();
    }

    /** Registration records in the CURRENT session, grouped by the student's department. */
    private function registrationsPerDepartment(): array
    {
        $semesterIds = Semester::query()
            ->whereHas('session', fn ($query) => $query->where('is_current', true))
            ->pluck('id')
            ->all();

        return Department::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Department $department) => [
                'label' => $department->name,
                'value' => Registration::query()
                    ->whereIn('semester_id', $semesterIds)
                    ->whereHas('student', fn ($query) => $query->where('department_id', $department->id))
                    ->count(),
            ])
            ->values()
            ->all();
    }

    /** Result submissions by status. Every status is listed, even with zero submissions. */
    private function resultsPerStatus(): array
    {
        $counts = DB::table('result_submissions')
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return collect(ResultStatus::cases())
            ->map(fn (ResultStatus $status) => [
                'label' => $status->value,
                'value' => (int) ($counts[$status->value] ?? 0),
            ])
            ->values()
            ->all();
    }
}
