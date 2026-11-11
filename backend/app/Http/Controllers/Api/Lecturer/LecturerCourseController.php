<?php

namespace App\Http\Controllers\Api\Lecturer;

use App\Http\Resources\CourseResource;
use App\Models\Course;
use Illuminate\Http\Request;

class LecturerCourseController extends LecturerController
{
    /** GET /api/lecturer/courses: the courses assigned to this lecturer in the current session. */
    public function index(Request $request): array
    {
        $lecturer = $this->lecturer($request);

        $courses = Course::query()
            ->whereIn('id', $this->assignedCourseIds($lecturer))
            ->with('department', 'currentAssignment.lecturer.user')
            ->orderBy('code')
            ->get();

        $rows = $courses->map(function (Course $course) use ($request) {
            $semester = $this->courseSemester($course);
            $submission = $this->submissionFor($course, $semester);

            return array_merge((new CourseResource($course))->resolve($request), [
                'studentCount' => $this->classList($course, $semester)->count(),
                'submissionStatus' => ($submission?->status->value) ?? 'draft',
                'feedback' => $submission?->feedback ?: null,
            ]);
        })->values()->all();

        return [
            'data' => $rows,
            'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => count($rows)],
        ];
    }

    /** GET /api/lecturer/courses/{course}/students: the class list with scores and the status. */
    public function students(Request $request, Course $course): array
    {
        $this->ownCourse($request, $course);

        return $this->payload($course);
    }

    /** GET /api/lecturer/courses/{course}/summary */
    public function summary(Request $request, Course $course): array
    {
        $this->ownCourse($request, $course);

        $semester = $this->courseSemester($course);
        $submission = $this->submissionFor($course, $semester);
        $classSize = $this->classList($course, $semester)->count();
        $results = $submission ? $submission->results()->whereNotNull('total_score')->get() : collect();

        $distribution = [];
        foreach ($this->grading()->bands() as $band) {
            $distribution[(string) $band['grade']] = 0;
        }
        foreach ($results as $result) {
            if (isset($distribution[$result->grade])) {
                $distribution[$result->grade]++;
            }
        }

        $totals = $results->map(fn ($result) => (float) $result->total_score);
        $passed = $results->filter(fn ($result) => (float) $result->grade_point > 0)->count();

        return [
            'courseId' => (string) $course->id,
            'courseCode' => $course->code,
            'courseTitle' => $course->title,
            'status' => ($submission?->status->value) ?? 'draft',
            'totalStudents' => $classSize,
            'scoredStudents' => $results->count(),
            'average' => $results->isEmpty() ? null : round($totals->avg(), 2),
            'highest' => $results->isEmpty() ? null : $totals->max(),
            'lowest' => $results->isEmpty() ? null : $totals->min(),
            'passRate' => $results->isEmpty() ? null : round($passed * 100 / $results->count(), 1),
            'gradeDistribution' => $distribution,
        ];
    }
}
