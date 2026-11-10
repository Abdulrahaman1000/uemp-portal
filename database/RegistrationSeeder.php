<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Registration;
use App\Models\Semester;
use App\Models\Student;
use Illuminate\Database\Seeder;

/** One registration per student per semester, with its courses. */
class RegistrationSeeder extends Seeder
{
    public function run(): void
    {
        // matric, session, semester, registered at, course codes
        $registrations = [
            // Current semester: 2026/2027 First.
            ['CSC/2023/001', '2026/2027', 'First', '2026-10-02 09:40', ['CSC301', 'CSC305']],
            ['CSC/2024/014', '2026/2027', 'First', '2026-10-03 11:20', ['CSC202']],
            ['ECO/2024/009', '2026/2027', 'First', '2026-10-04 10:15', ['ECO201']],
            ['CSC/2023/012', '2026/2027', 'First', '2026-10-05 13:05', ['CSC301', 'CSC305']],
            ['MTH/2023/003', '2026/2027', 'First', '2026-10-06 08:50', ['MTH301']],
            // Avery's history from the previous session.
            ['CSC/2023/001', '2025/2026', 'First', '2025-10-01 10:00', ['CSC201', 'CSC202']],
            ['CSC/2023/001', '2025/2026', 'Second', '2026-03-02 10:00', ['CSC206']],
        ];
        foreach ($registrations as [$matric, $sessionName, $semesterName, $registeredAt, $codes]) {
            $student = Student::where('matric_no', $matric)->firstOrFail();
            $semester = Semester::where('name', $semesterName)
                ->whereHas('session', fn ($query) => $query->where('name', $sessionName))
                ->firstOrFail();
            $registration = Registration::updateOrCreate(
                ['student_id' => $student->id, 'semester_id' => $semester->id],
                ['registered_at' => DemoSeed::at($registeredAt)],
            );
            $registration->courses()->syncWithoutDetaching(
                Course::whereIn('code', $codes)->pluck('id')->all(),
            );
        }
    }
}
