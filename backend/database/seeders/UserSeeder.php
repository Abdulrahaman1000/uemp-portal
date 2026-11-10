<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Department;
use App\Models\Lecturer;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;

/** Demo accounts for all four roles, plus the student and lecturer profile rows. */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Super admin: university-wide, no department.
        $this->user('Morgan Ellis', 'superadmin@northstar.edu', UserRole::SuperAdmin, UserStatus::Active, null, '2026-11-09 09:00');

        // Department admins: users.department_id is their scope.
        $this->user('Jordan Brooks', 'deptadmin@northstar.edu', UserRole::DeptAdmin, UserStatus::Active, 'CSC', '2026-11-09 08:30');
        $this->user('Prof. Helen Cooper', 'h.cooper@northstar.edu', UserRole::DeptAdmin, UserStatus::Active, 'ECO', '2026-11-07 10:00');

        // Lecturers: their department lives on the lecturers row.
        $lecturers = [
            ['Dr. Maya Chen', 'lecturer@northstar.edu', UserStatus::Active, 'LEC/001', 'CSC', 'Algorithms', '2026-11-08 15:00'],
            ['Prof. Daniel Reyes', 'daniel.reyes@northstar.edu', UserStatus::Active, 'LEC/002', 'ECO', 'Microeconomics', '2026-11-06 11:00'],
            ['Dr. Priya Shah', 'priya.shah@northstar.edu', UserStatus::Inactive, 'LEC/003', 'MTH', 'Linear Algebra', '2026-10-20 09:00'],
        ];
        foreach ($lecturers as [$name, $email, $status, $staffId, $departmentCode, $specialization, $lastActive]) {
            $user = $this->user($name, $email, UserRole::Lecturer, $status, null, $lastActive);
            Lecturer::updateOrCreate(['staff_id' => $staffId], [
                'user_id' => $user->id,
                'department_id' => $this->departmentId($departmentCode),
                'specialization' => $specialization,
            ]);
        }

        // Students: department, program and level live on the students row.
        $students = [
            ['Avery Johnson', 'student@northstar.edu', UserStatus::Active, 'CSC/2023/001', 'CSC', 'Computer Science', 300, '2026-11-09 07:45', '2003-04-17', '+234 803 555 0101', '12 University Avenue'],
            ['Leila Ahmed', 'leila.ahmed@northstar.edu', UserStatus::Active, 'CSC/2024/014', 'CSC', 'Computer Science', 200, '2026-11-05 12:00', '2004-08-02', '+234 803 555 0102', '4 Campus Road'],
            ['Noah Williams', 'noah.williams@northstar.edu', UserStatus::Active, 'CSC/2022/007', 'CSC', 'Software Engineering', 400, '2026-11-04 16:20', '2002-01-23', null, null],
            ['Sofia Martinez', 'sofia.martinez@northstar.edu', UserStatus::Active, 'MTH/2023/003', 'MTH', 'Mathematics', 300, '2026-11-06 09:10', '2003-11-30', null, null],
            ['Amina Yusuf', 'amina.yusuf@northstar.edu', UserStatus::Inactive, 'CSC/2025/021', 'CSC', 'Computer Science', 100, '2026-10-12 14:00', '2006-05-09', null, null],
            ['Ethan Okafor', 'ethan.okafor@northstar.edu', UserStatus::Suspended, 'ECO/2024/009', 'ECO', 'Economics', 200, '2026-10-02 10:30', '2004-02-14', null, null],
            // Added so the level-300 Computer Science classes have more than one student.
            ['Daniel Mensah', 'daniel.mensah@northstar.edu', UserStatus::Active, 'CSC/2023/012', 'CSC', 'Computer Science', 300, '2026-11-08 18:05', '2003-07-21', null, null],
        ];
        foreach ($students as [$name, $email, $status, $matric, $departmentCode, $programName, $level, $lastActive, $dob, $phone, $address]) {
            $user = $this->user($name, $email, UserRole::Student, $status, null, $lastActive);
            $departmentId = $this->departmentId($departmentCode);
            Student::updateOrCreate(['matric_no' => $matric], [
                'user_id' => $user->id,
                'department_id' => $departmentId,
                'program_id' => Program::where('department_id', $departmentId)->where('name', $programName)->value('id'),
                'level' => $level,
                'date_of_birth' => $dob,
                'phone' => $phone,
                'address' => $address,
            ]);
        }
    }

    private function user(string $name, string $email, UserRole $role, UserStatus $status, ?string $departmentCode, string $lastActive): User
    {
        return User::updateOrCreate(['email' => $email], [
            'name' => $name,
            'password' => DemoSeed::password(), // hashed by the User model cast
            'role' => $role,
            'status' => $status,
            'department_id' => $departmentCode ? $this->departmentId($departmentCode) : null,
            'last_active_at' => DemoSeed::at($lastActive),
        ]);
    }

    private function departmentId(string $code): int
    {
        return Department::where('code', $code)->firstOrFail()->id;
    }
}
