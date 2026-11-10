<?php

namespace Database\Seeders;

use App\Models\AcademicSession;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\Registration;
use App\Models\ResultSubmission;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

/**
 * Sample activity. The audit log is append-only, so this seeder only runs on an empty table
 * (use `php artisan migrate:fresh --seed` to start over).
 */
class AuditLogSeeder extends Seeder
{
    public function run(): void
    {
        if (AuditLog::query()->exists()) {
            return;
        }

        $submission = fn (string $code, string $session) => ResultSubmission::where('course_id', Course::where('code', $code)->value('id'))
            ->whereHas('semester.session', fn ($query) => $query->where('name', $session))
            ->firstOrFail();
        $registration = fn (string $matric) => Registration::where('student_id', Student::where('matric_no', $matric)->value('id'))
            ->whereHas('semester', fn ($query) => $query->where('is_current', true))
            ->firstOrFail();

        $chen = 'lecturer@northstar.edu';
        $jordan = 'deptadmin@northstar.edu';
        $morgan = 'superadmin@northstar.edu';

        // time, actor email, action, entity, details, ip, record, extra metadata
        $events = [
            ['2026-09-14 08:00', $morgan, 'USER_STATUS_CHANGED', 'Ethan Okafor', 'Status set to suspended', '102.89.34.10', User::where('email', 'ethan.okafor@northstar.edu')->first(), null],
            ['2026-10-02 09:40', 'student@northstar.edu', 'COURSE_REGISTERED', 'CSC301, CSC305', 'Registered 2 courses (6 units)', '197.210.8.12', $registration('CSC/2023/001'), null],
            ['2026-10-03 11:20', 'leila.ahmed@northstar.edu', 'COURSE_REGISTERED', 'CSC202', 'Registered 1 course (3 units)', '197.210.8.31', $registration('CSC/2024/014'), null],
            ['2026-10-04 10:15', 'ethan.okafor@northstar.edu', 'COURSE_REGISTERED', 'ECO201', 'Registered 1 course (3 units)', '197.210.8.77', $registration('ECO/2024/009'), null],
            ['2026-10-28 09:00', 'priya.shah@northstar.edu', 'RESULT_SUBMITTED', 'MTH301', 'Submitted 1 result for review', '102.89.34.30', $submission('MTH301', '2026/2027'), null],
            ['2026-10-29 16:20', $morgan, 'RESULT_REOPENED', 'MTH301', 'Reopened to correct a missing CA score', '102.89.34.10', $submission('MTH301', '2026/2027'), ['note' => 'Missing CA score.']],
            ['2026-11-01 10:00', $chen, 'RESULT_SUBMITTED', 'CSC305', 'Submitted 2 results for review', '102.89.34.21', $submission('CSC305', '2026/2027'), null],
            ['2026-11-02 14:30', $jordan, 'RESULT_APPROVED', 'CSC305', 'Approved 2 results', '102.89.40.5', $submission('CSC305', '2026/2027'), null],
            ['2026-11-03 08:45', $chen, 'RESULT_SUBMITTED', 'CSC202', 'Submitted 1 result for review', '102.89.34.21', $submission('CSC202', '2026/2027'), null],
            ['2026-11-04 11:05', $jordan, 'RESULT_REJECTED', 'CSC202', 'Feedback: exam scores look swapped', '102.89.40.5', $submission('CSC202', '2026/2027'), ['note' => 'Exam scores for two students look swapped.']],
            ['2026-11-05 09:12', $chen, 'RESULT_SUBMITTED', 'CSC301', 'Submitted 2 results for review', '102.89.34.21', $submission('CSC301', '2026/2027'), null],
            ['2026-11-06 13:00', 'daniel.reyes@northstar.edu', 'RESULT_DRAFT_SAVED', 'ECO201', 'Saved a draft for 1 student', '102.89.34.44', $submission('ECO201', '2026/2027'), null],
            ['2026-11-09 09:30', $morgan, 'SESSION_ACTIVATED', 'Session 2026/2027', 'Set as current session', '102.89.34.10', AcademicSession::where('name', '2026/2027')->first(), null],
        ];

        foreach ($events as [$time, $email, $action, $entity, $details, $ip, $record, $metadata]) {
            $actor = User::where('email', $email)->firstOrFail();
            $this->log($actor, $time, $action, $entity, $details, $ip, $record, $metadata);
        }
    }

    private function log(User $actor, string $time, string $action, string $entity, string $details, string $ip, ?Model $record, ?array $metadata): void
    {
        $log = new AuditLog([
            'user_id' => $actor->id,
            'user_name' => $actor->name,
            'role' => $actor->role->value,
            'action' => $action,
            'entity' => $entity,
            'details' => $details,
            'ip' => $ip,
            'metadata' => $metadata,
        ]);
        if ($record) {
            $log->auditable()->associate($record);
        }
        $log->created_at = DemoSeed::at($time);
        $log->save();
    }
}
