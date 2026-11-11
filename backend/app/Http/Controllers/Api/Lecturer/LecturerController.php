<?php

namespace App\Http\Controllers\Api\Lecturer;

use App\Enums\ResultStatus;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseAssignment;
use App\Models\Lecturer;
use App\Models\ResultSubmission;
use App\Models\Semester;
use App\Models\Student;
use App\Services\GradingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

/**
 * Shared helpers for the lecturer controllers (T2.7).
 * Rule: a lecturer only touches courses assigned to them in the CURRENT academic session.
 * Call ownCourse() first in every action that receives a course.
 */
abstract class LecturerController extends Controller
{
    protected function grading(): GradingService
    {
        return app(GradingService::class);
    }

    protected function lecturer(Request $request): Lecturer
    {
        $lecturer = $request->user()?->lecturer;

        if (! $lecturer) {
            abort(403, 'No lecturer profile is linked to this account.');
        }

        return $lecturer;
    }

    /** 403 unless the course is assigned to this lecturer in the current session. */
    protected function ownCourse(Request $request, Course $course): Lecturer
    {
        $lecturer = $this->lecturer($request);

        $assigned = CourseAssignment::query()
            ->where('course_id', $course->id)
            ->where('lecturer_id', $lecturer->id)
            ->whereHas('session', fn (Builder $query) => $query->where('is_current', true))
            ->exists();

        if (! $assigned) {
            abort(403, 'This course is not assigned to you in the current session.');
        }

        return $lecturer;
    }

    /** @return list<int> */
    protected function assignedCourseIds(Lecturer $lecturer): array
    {
        return CourseAssignment::query()
            ->where('lecturer_id', $lecturer->id)
            ->whereHas('session', fn (Builder $query) => $query->where('is_current', true))
            ->pluck('course_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** The semester row of the current session that matches the course's semester (First or Second). */
    protected function courseSemester(Course $course): ?Semester
    {
        return Semester::query()
            ->with('session')
            ->where('name', $course->semester->value)
            ->whereHas('session', fn (Builder $query) => $query->where('is_current', true))
            ->first();
    }

    /** Scores can only be entered while the course's semester is the current semester. */
    protected function writableSemester(Course $course): Semester
    {
        $semester = $this->courseSemester($course);

        if (! $semester || ! $semester->is_current) {
            abort(422, "Results for {$course->code} can only be entered during the {$course->semester->value} semester.");
        }

        return $semester;
    }

    /** Students registered for this course in that semester, ordered by matric number. */
    protected function classList(Course $course, ?Semester $semester): Collection
    {
        if (! $semester) {
            return new Collection();
        }

        return Student::query()
            ->select('students.*')
            ->join('registrations', 'registrations.student_id', '=', 'students.id')
            ->join('registration_items', 'registration_items.registration_id', '=', 'registrations.id')
            ->where('registrations.semester_id', $semester->id)
            ->where('registration_items.course_id', $course->id)
            ->with('user')
            ->orderBy('students.matric_no')
            ->get();
    }

    protected function submissionFor(Course $course, ?Semester $semester): ?ResultSubmission
    {
        if (! $semester) {
            return null;
        }

        return ResultSubmission::query()
            ->where('course_id', $course->id)
            ->where('semester_id', $semester->id)
            ->first();
    }

    protected function num(mixed $value): ?float
    {
        return $value === null ? null : (float) $value;
    }

    /**
     * The class list with each student's scores, plus the submission status.
     * Returned by GET .../students and by the save and submit actions.
     *
     * @return array<string, mixed>
     */
    protected function payload(Course $course): array
    {
        $semester = $this->courseSemester($course);
        $submission = $this->submissionFor($course, $semester);
        $status = $submission?->status ?? ResultStatus::Draft;
        $results = $submission ? $submission->results()->get()->keyBy('student_id') : collect();

        $rows = $this->classList($course, $semester)
            ->map(function (Student $student) use ($results) {
                $result = $results->get($student->id);

                return [
                    'studentId' => (string) $student->id,
                    'matricNo' => $student->matric_no,
                    'studentName' => $student->user?->name ?? '',
                    'level' => (int) $student->level,
                    'caScore' => $this->num($result?->ca_score),
                    'examScore' => $this->num($result?->exam_score),
                    'totalScore' => $this->num($result?->total_score),
                    'grade' => $result?->grade,
                    'gradePoint' => $this->num($result?->grade_point),
                ];
            })
            ->values()
            ->all();

        return [
            'courseId' => (string) $course->id,
            'courseCode' => $course->code,
            'courseTitle' => $course->title,
            'units' => (int) $course->units,
            'sessionName' => $semester?->session?->name ?? '',
            'semester' => $course->semester->value,
            'status' => $status->value,
            'feedback' => $submission?->feedback ?: null,
            'canEdit' => $status->isEditableByLecturer() && $semester?->is_current === true,
            'caMax' => (int) $this->grading()->caMax(),
            'examMax' => (int) $this->grading()->examMax(),
            'students' => $rows,
        ];
    }
}
