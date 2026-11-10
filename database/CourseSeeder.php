<?php

namespace Database\Seeders;

use App\Models\AcademicSession;
use App\Models\Course;
use App\Models\CourseAssignment;
use App\Models\Department;
use App\Models\Lecturer;
use App\Models\Program;
use Illuminate\Database\Seeder;

/** Courses and the lecturer assignment of each course for an academic session. */
class CourseSeeder extends Seeder
{
    public function run(): void
    {
        // code, title, units, level, semester, type, department, program
        $courses = [
            ['CSC101', 'Introduction to Programming', 3, 100, 'First', 'core', 'CSC', 'Computer Science'],
            ['CSC202', 'Discrete Structures', 3, 200, 'First', 'core', 'CSC', 'Computer Science'],
            ['CSC301', 'Data Structures and Algorithms', 3, 300, 'First', 'core', 'CSC', 'Computer Science'],
            ['CSC305', 'Database Systems', 3, 300, 'First', 'core', 'CSC', 'Computer Science'],
            ['CSC311', 'Human-Computer Interaction', 2, 300, 'First', 'elective', 'CSC', 'Computer Science'],
            ['CSC312', 'Operating Systems', 3, 300, 'Second', 'core', 'CSC', 'Computer Science'],
            ['MTH301', 'Linear Algebra II', 3, 300, 'First', 'core', 'MTH', 'Mathematics'],
            ['ECO201', 'Intermediate Microeconomics', 3, 200, 'First', 'core', 'ECO', 'Economics'],
            // Added to give the student academic history (2025/2026) something to show.
            ['CSC201', 'Object-Oriented Programming', 3, 200, 'First', 'core', 'CSC', 'Computer Science'],
            ['CSC206', 'Web Technologies', 2, 200, 'Second', 'elective', 'CSC', 'Computer Science'],
        ];
        foreach ($courses as [$code, $title, $units, $level, $semester, $type, $departmentCode, $programName]) {
            $departmentId = Department::where('code', $departmentCode)->value('id');
            Course::updateOrCreate(['code' => $code], [
                'title' => $title,
                'units' => $units,
                'level' => $level,
                'semester' => $semester,
                'type' => $type,
                'department_id' => $departmentId,
                'program_id' => Program::where('department_id', $departmentId)->where('name', $programName)->value('id'),
            ]);
        }

        // session, lecturer email => course codes. CSC311 and CSC312 are left unassigned on purpose.
        $assignments = [
            ['2026/2027', 'lecturer@northstar.edu', ['CSC101', 'CSC202', 'CSC301', 'CSC305']],
            ['2026/2027', 'priya.shah@northstar.edu', ['MTH301']],
            ['2026/2027', 'daniel.reyes@northstar.edu', ['ECO201']],
            ['2025/2026', 'lecturer@northstar.edu', ['CSC201', 'CSC202', 'CSC206']],
        ];
        foreach ($assignments as [$sessionName, $email, $codes]) {
            $sessionId = AcademicSession::where('name', $sessionName)->firstOrFail()->id;
            $lecturerId = Lecturer::whereHas('user', fn ($query) => $query->where('email', $email))->firstOrFail()->id;
            foreach ($codes as $code) {
                CourseAssignment::updateOrCreate(
                    ['course_id' => Course::where('code', $code)->firstOrFail()->id, 'academic_session_id' => $sessionId],
                    ['lecturer_id' => $lecturerId],
                );
            }
        }
    }
}
