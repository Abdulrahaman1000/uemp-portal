<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\CourseAssignment;
use App\Models\Result;
use App\Models\ResultSubmission;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;

/** Result submissions in every workflow status, with per-student scores. */
class ResultSeeder extends Seeder
{
    public function run(): void
    {
        $morgan = User::where('email', 'superadmin@northstar.edu')->firstOrFail();
        $jordan = User::where('email', 'deptadmin@northstar.edu')->firstOrFail();

        // course, session, semester, status, feedback, submitted at, reviewer, reviewed at, scores [matric => [ca, exam]]
        $submissions = [
            ['CSC301', '2026/2027', 'First', 'submitted', null, '2026-11-05 09:12', null, null,
                ['CSC/2023/001' => [24, 52], 'CSC/2023/012' => [18, 38]]],
            ['CSC305', '2026/2027', 'First', 'approved', null, '2026-11-01 10:00', $jordan, '2026-11-02 14:30',
                ['CSC/2023/001' => [27, 55], 'CSC/2023/012' => [20, 44]]],
            ['CSC202', '2026/2027', 'First', 'rejected', 'Exam scores for two students look swapped.', '2026-11-03 08:45', $jordan, '2026-11-04 11:05',
                ['CSC/2024/014' => [22, 31]]],
            // The CA score is missing on purpose: it explains why the results were reopened.
            ['MTH301', '2026/2027', 'First', 'reopened', 'Reopened to correct a missing CA score.', '2026-10-28 09:00', $morgan, '2026-10-29 16:20',
                ['MTH/2023/003' => [null, 58]]],
            // A draft that is still being entered: the exam score is not in yet.
            ['ECO201', '2026/2027', 'First', 'draft', null, null, null, null,
                ['ECO/2024/009' => [15, null]]],
            // Approved history for Avery Johnson (previous session).
            ['CSC201', '2025/2026', 'First', 'approved', null, '2026-02-05 09:00', $jordan, '2026-02-10 15:00',
                ['CSC/2023/001' => [25, 48]]],
            ['CSC202', '2025/2026', 'First', 'approved', null, '2026-02-05 09:30', $jordan, '2026-02-10 15:10',
                ['CSC/2023/001' => [19, 41]]],
            ['CSC206', '2025/2026', 'Second', 'approved', null, '2026-08-05 09:00', $jordan, '2026-08-10 12:00',
                ['CSC/2023/001' => [21, 37]]],
        ];

        foreach ($submissions as [$code, $sessionName, $semesterName, $status, $feedback, $submittedAt, $reviewer, $reviewedAt, $scores]) {
            $course = Course::where('code', $code)->firstOrFail();
            $semester = Semester::where('name', $semesterName)
                ->whereHas('session', fn ($query) => $query->where('name', $sessionName))
                ->firstOrFail();
            // The lecturer is whoever taught the course in that session.
            $lecturerId = CourseAssignment::where('course_id', $course->id)
                ->where('academic_session_id', $semester->academic_session_id)
                ->firstOrFail()->lecturer_id;

            $submission = ResultSubmission::updateOrCreate(
                ['course_id' => $course->id, 'semester_id' => $semester->id],
                [
                    'lecturer_id' => $lecturerId,
                    'status' => $status,
                    'feedback' => $feedback,
                    'submitted_at' => $submittedAt ? DemoSeed::at($submittedAt) : null,
                    'reviewed_by' => $reviewer?->id,
                    'reviewed_at' => $reviewedAt ? DemoSeed::at($reviewedAt) : null,
                ],
            );

            foreach ($scores as $matric => [$ca, $exam]) {
                $complete = $ca !== null && $exam !== null;
                [$grade, $point] = $complete ? DemoSeed::grade($ca + $exam) : [null, null];
                Result::updateOrCreate(
                    ['result_submission_id' => $submission->id, 'student_id' => Student::where('matric_no', $matric)->firstOrFail()->id],
                    [
                        'ca_score' => $ca,
                        'exam_score' => $exam,
                        'total_score' => $complete ? $ca + $exam : null,
                        'grade' => $grade,
                        'grade_point' => $point,
                    ],
                );
            }
        }
    }
}
