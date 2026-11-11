<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Models\Result;
use App\Models\Semester;
use App\Models\Student;
use App\Services\GradingService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Shared helpers for the student controllers (T2.8).
 * Every query starts from the signed-in student's own profile row, so a student can never
 * read or change another student's data: there is no student id in any URL or body.
 */
abstract class StudentController extends Controller
{
    protected function student(Request $request): Student
    {
        $student = $request->user()?->student;

        if (! $student) {
            abort(403, 'No student profile is linked to this account.');
        }

        return $student->loadMissing('user', 'department.faculty', 'program');
    }

    protected function currentSemester(): ?Semester
    {
        return Semester::query()->current()->with('session')->first();
    }

    protected function grading(): GradingService
    {
        return app(GradingService::class);
    }

    /**
     * The student's APPROVED results only (business rule 5), oldest semester first.
     * Draft, submitted, rejected and reopened results are never returned.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function approvedResults(Student $student): Collection
    {
        return Result::query()
            ->where('student_id', $student->id)
            ->whereNotNull('grade')
            ->whereHas('submission', fn ($query) => $query->where('status', 'approved'))
            ->with('submission.course', 'submission.semester.session')
            ->get()
            ->sortBy([
                fn ($a, $b) => $a->submission->semester->start_date <=> $b->submission->semester->start_date,
                fn ($a, $b) => $a->submission->course->code <=> $b->submission->course->code,
            ])
            ->values()
            ->map(fn (Result $result) => [
                'id' => (string) $result->id,
                'semesterId' => (string) $result->submission->semester_id,
                'courseId' => (string) $result->submission->course_id,
                'courseCode' => $result->submission->course->code,
                'courseTitle' => $result->submission->course->title,
                'units' => (int) $result->submission->course->units,
                'sessionName' => $result->submission->semester->session?->name ?? '',
                'semester' => $result->submission->semester->name->value,
                'caScore' => (float) $result->ca_score,
                'examScore' => (float) $result->exam_score,
                'totalScore' => (float) $result->total_score,
                'grade' => $result->grade,
                'gradePoint' => (float) $result->grade_point,
            ]);
    }
}
