<?php

namespace Database\Seeders;

use App\Models\AcademicSession;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\Program;
use App\Models\Semester;
use Illuminate\Database\Seeder;

/** Faculties, departments, programs, academic sessions and semesters. */
class AcademicStructureSeeder extends Seeder
{
    public function run(): void
    {
        $faculties = [
            ['SCI', 'Faculty of Science', 'Prof. Robert Harris'],
            ['SOC', 'Faculty of Social Sciences', 'Prof. Grace Evans'],
        ];
        foreach ($faculties as [$code, $name, $dean]) {
            Faculty::updateOrCreate(['code' => $code], ['name' => $name, 'dean' => $dean]);
        }

        $departments = [
            ['CSC', 'Computer Science', 'SCI', 'Dr. Maya Chen'],
            ['MTH', 'Mathematics', 'SCI', 'Dr. Priya Shah'],
            ['ECO', 'Economics', 'SOC', 'Prof. Daniel Reyes'],
        ];
        foreach ($departments as [$code, $name, $facultyCode, $head]) {
            Department::updateOrCreate(['code' => $code], [
                'name' => $name,
                'faculty_id' => Faculty::where('code', $facultyCode)->value('id'),
                'head' => $head,
            ]);
        }

        $programs = [
            ['CSC', 'Computer Science'],
            ['CSC', 'Software Engineering'],
            ['MTH', 'Mathematics'],
            ['ECO', 'Economics'],
        ];
        foreach ($programs as [$departmentCode, $name]) {
            Program::updateOrCreate(
                ['department_id' => Department::where('code', $departmentCode)->value('id'), 'name' => $name],
                ['award' => 'B.Sc.', 'duration_years' => 4],
            );
        }

        $sessions = [
            ['2025/2026', '2025-09-15', '2026-07-31', [['First', '2025-09-15', '2026-01-30'], ['Second', '2026-02-16', '2026-07-31']]],
            ['2026/2027', '2026-09-14', '2027-07-30', [['First', '2026-09-14', '2027-01-29'], ['Second', '2027-02-15', '2027-07-30']]],
        ];
        foreach ($sessions as [$name, $start, $end, $semesters]) {
            $session = AcademicSession::updateOrCreate(['name' => $name], ['start_date' => $start, 'end_date' => $end]);
            foreach ($semesters as [$semesterName, $semesterStart, $semesterEnd]) {
                Semester::updateOrCreate(
                    ['academic_session_id' => $session->id, 'name' => $semesterName],
                    ['start_date' => $semesterStart, 'end_date' => $semesterEnd],
                );
            }
        }

        // Only one session and one semester may be current (database partial unique index),
        // so clear the old flags first, then set the new ones.
        AcademicSession::query()->update(['is_current' => false]);
        Semester::query()->update(['is_current' => false]);
        AcademicSession::where('name', '2026/2027')->update(['is_current' => true]);
        Semester::where('name', 'First')
            ->whereHas('session', fn ($query) => $query->where('name', '2026/2027'))
            ->update(['is_current' => true]);
    }
}
