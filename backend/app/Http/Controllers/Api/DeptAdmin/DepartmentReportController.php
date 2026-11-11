<?php

namespace App\Http\Controllers\Api\DeptAdmin;

use App\Enums\ResultStatus;
use App\Models\Registration;
use App\Models\ResultSubmission;
use App\Models\Student;
use App\Support\DepartmentScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/** Department admin: small reports for the own department. Each row is { label, value }. */
class DepartmentReportController extends DeptAdminController
{
    /** GET /api/dept/reports/{students|registrations|results} */
    public function show(Request $request, string $type): array
    {
        return match ($type) {
            'students' => $this->students($request),
            'registrations' => $this->registrations($request),
            'results' => $this->results($request),
            default => abort(404),
        };
    }

    /** Students of the department grouped by level. */
    private function students(Request $request): array
    {
        return DepartmentScope::apply(Student::query(), $request->user(), 'department_id')
            ->orderBy('level')
            ->get(['level'])
            ->groupBy('level')
            ->map(fn ($group, $level) => ['label' => "{$level} Level", 'value' => $group->count()])
            ->values()
            ->all();
    }

    /** Registrations of the CURRENT session grouped by the student's level. */
    private function registrations(Request $request): array
    {
        $user = $request->user();

        return Registration::query()
            ->whereHas('student', fn (Builder $student) => DepartmentScope::apply($student, $user, 'department_id'))
            ->whereHas('semester.session', fn (Builder $session) => $session->where('is_current', true))
            ->with('student')
            ->get()
            ->sortBy(fn ($registration) => $registration->student->level)
            ->groupBy(fn ($registration) => $registration->student->level)
            ->map(fn ($group, $level) => ['label' => "{$level} Level", 'value' => $group->count()])
            ->values()
            ->all();
    }

    /** Result submissions of the department by status. Every status is listed, even with zero. */
    private function results(Request $request): array
    {
        $user = $request->user();
        $rows = [];

        foreach (ResultStatus::cases() as $status) {
            $rows[] = [
                'label' => ucfirst($status->value),
                'value' => ResultSubmission::query()
                    ->whereHas('course', fn (Builder $course) => DepartmentScope::apply($course, $user, 'department_id'))
                    ->where('status', $status->value)
                    ->count(),
            ];
        }

        return $rows;
    }
}
