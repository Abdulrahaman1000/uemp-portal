<?php

namespace Tests\Concerns;

use App\Models\AcademicSession;
use App\Models\Course;
use App\Models\Department;
use App\Models\Program;
use App\Models\ResultSubmission;
use App\Models\Semester;
use App\Models\User;

/** Builders for tests that need a current semester, courses and result submissions. */
trait CreatesWorkflowData
{
    /** Creates the current session 2026/2027 with its current First semester. Call once per test. */
    protected function currentSemester(): Semester
    {
        $session = AcademicSession::create(['name' => '2026/2027', 'start_date' => '2026-09-14', 'end_date' => '2027-07-30', 'is_current' => true]);

        return Semester::create(['academic_session_id' => $session->id, 'name' => 'First', 'start_date' => '2026-09-14', 'end_date' => '2027-01-29', 'is_current' => true]);
    }

    protected function makeCourse(string $code, Department $department): Course
    {
        $program = Program::firstOrCreate(['department_id' => $department->id, 'name' => $department->name], ['award' => 'B.Sc.', 'duration_years' => 4]);

        return Course::create([
            'code' => $code, 'title' => 'Title '.$code, 'units' => 3, 'level' => 300, 'semester' => 'First',
            'type' => 'core', 'department_id' => $department->id, 'program_id' => $program->id,
        ]);
    }

    /** @param  User  $lecturer  A user made with makeLecturer(). */
    protected function makeSubmission(Course $course, Semester $semester, User $lecturer, string $status = 'submitted'): ResultSubmission
    {
        return ResultSubmission::create([
            'course_id' => $course->id,
            'semester_id' => $semester->id,
            'lecturer_id' => $lecturer->lecturer->id,
            'status' => $status,
            'submitted_at' => $status === 'draft' ? null : now(),
        ]);
    }
}
