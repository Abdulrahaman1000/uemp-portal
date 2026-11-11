<?php

namespace App\Http\Controllers\Api\Lecturer;

use App\Enums\ResultStatus;
use App\Models\AcademicSession;
use App\Models\Course;
use App\Models\ResultSubmission;
use App\Models\Semester;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LecturerDashboardController extends LecturerController
{
    /** GET /api/lecturer/dashboard */
    public function dashboard(Request $request): JsonResponse
    {
        $lecturer = $this->lecturer($request)->load('department', 'user');
        $courseIds = $this->assignedCourseIds($lecturer);
        $session = AcademicSession::current()->first();
        $semester = Semester::current()->first();

        // Courses of the current semester only (a Second semester course is idle during the First).
        $activeIds = $semester
            ? Course::query()->whereIn('id', $courseIds)->where('semester', $semester->name->value)->pluck('id')->all()
            : [];

        $totalStudents = 0;
        $started = 0;
        if ($semester && $activeIds !== []) {
            $totalStudents = DB::table('registration_items as ri')
                ->join('registrations as r', 'r.id', '=', 'ri.registration_id')
                ->where('r.semester_id', $semester->id)
                ->whereIn('ri.course_id', $activeIds)
                ->distinct()
                ->count('r.student_id');
            $started = ResultSubmission::query()
                ->where('lecturer_id', $lecturer->id)
                ->where('semester_id', $semester->id)
                ->whereIn('course_id', $activeIds)
                ->count();
        }

        $semesterIds = Semester::query()
            ->whereHas('session', fn ($query) => $query->where('is_current', true))
            ->pluck('id');
        $counts = ResultSubmission::query()
            ->where('lecturer_id', $lecturer->id)
            ->whereIn('semester_id', $semesterIds)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $byStatus = [];
        foreach (ResultStatus::cases() as $status) {
            $byStatus[$status->value] = (int) ($counts[$status->value] ?? 0);
        }

        return response()->json([
            'lecturerName' => $lecturer->user?->name ?? '',
            'departmentName' => $lecturer->department?->name ?? '',
            'assignedCourses' => count($courseIds),
            'totalStudents' => (int) $totalStudents,
            'notStarted' => max(0, count($activeIds) - $started),
            'submissionsByStatus' => $byStatus,
            'currentSession' => $session?->name ?? 'None',
            'currentSemester' => $semester?->name?->value ?? 'First',
        ]);
    }
}
