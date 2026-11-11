<?php

namespace App\Http\Controllers\Api\Student;

use Illuminate\Http\Request;

/** Results and academic history. Only APPROVED results are ever returned (business rule 5). */
class StudentResultController extends StudentController
{
    /** GET /api/student/results */
    public function index(Request $request): array
    {
        $rows = $this->approvedResults($this->student($request))
            ->map(fn (array $row) => collect($row)->except('semesterId')->all())
            ->values()
            ->all();

        return ['data' => $rows, 'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => count($rows)]];
    }

    /**
     * GET /api/student/academic-history
     * Semester by semester with the semester GPA, plus the CGPA over everything approved.
     * GPA = sum(grade point x units) / sum(units), from GradingService.
     */
    public function history(Request $request): array
    {
        $student = $this->student($request);
        $results = $this->approvedResults($student);

        $semesters = $results->groupBy('semesterId')->map(function ($rows) {
            $first = $rows->first();

            return [
                'sessionName' => $first['sessionName'],
                'semester' => $first['semester'],
                'totalUnits' => (int) $rows->sum('units'),
                'gpa' => $this->grading()->gpa($rows->map(fn ($row) => ['point' => $row['gradePoint'], 'units' => $row['units']])),
                'courses' => $rows->map(fn ($row) => collect($row)->except('semesterId')->all())->values()->all(),
            ];
        })->values()->all();

        return [
            'matricNo' => $student->matric_no,
            'studentName' => $student->user?->name ?? '',
            'departmentName' => $student->department?->name ?? '',
            'programName' => $student->program?->name ?? '',
            'level' => (int) $student->level,
            'cgpa' => $this->grading()->gpa($results->map(fn ($row) => ['point' => $row['gradePoint'], 'units' => $row['units']])),
            'totalUnits' => (int) $results->sum('units'),
            'totalCourses' => $results->count(),
            'semesters' => $semesters,
        ];
    }
}
