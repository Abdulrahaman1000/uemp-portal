<?php

namespace App\Http\Controllers\Api\DeptAdmin;

use App\Enums\ResultStatus;
use App\Models\Course;
use App\Models\Lecturer;
use App\Models\Program;
use App\Models\ResultSubmission;
use App\Models\Semester;
use App\Models\Student;
use App\Support\DepartmentScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/** Department admin: numbers for the dashboard, counted for the admin's own department only. */
class DepartmentDashboardController extends DeptAdminController
{
    /** GET /api/dept/dashboard */
    public function dashboard(Request $request): array
    {
        $user = $request->user();

        $byStatus = [];
        foreach (ResultStatus::cases() as $status) {
            $byStatus[$status->value] = ResultSubmission::query()
                ->whereHas('course', fn (Builder $course) => DepartmentScope::apply($course, $user, 'department_id'))
                ->where('status', $status->value)
                ->count();
        }

        $semester = Semester::current()->with('session')->first();

        return [
            'departmentName' => $user->department?->name ?? '',
            'totalStudents' => DepartmentScope::apply(Student::query(), $user, 'department_id')->count(),
            'totalLecturers' => DepartmentScope::apply(Lecturer::query(), $user, 'department_id')->count(),
            'totalCourses' => DepartmentScope::apply(Course::query(), $user, 'department_id')->count(),
            'activePrograms' => DepartmentScope::apply(Program::query(), $user, 'department_id')->count(),
            'pendingSubmissions' => $byStatus[ResultStatus::Submitted->value],
            'submissionsByStatus' => $byStatus,
            'currentSession' => $semester?->session?->name ?? '',
            'currentSemester' => $semester?->name?->value ?? '',
        ];
    }
}
