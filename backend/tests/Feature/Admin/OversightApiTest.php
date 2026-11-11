<?php

namespace Tests\Feature\Admin;

use App\Enums\UserStatus;
use App\Models\AcademicSession;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\CourseAssignment;
use App\Models\Program;
use App\Models\Registration;
use App\Models\Result;
use App\Models\ResultSubmission;
use App\Models\Semester;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesDemoData;
use Tests\TestCase;

class OversightApiTest extends TestCase
{
    use CreatesDemoData;

    private const PATHS = ['students', 'lecturers', 'registrations', 'submissions', 'activity-logs'];

    private function currentSemester(): Semester
    {
        $session = AcademicSession::create(['name' => '2026/2027', 'start_date' => '2026-09-14', 'end_date' => '2027-07-30', 'is_current' => true]);

        return Semester::create(['academic_session_id' => $session->id, 'name' => 'First', 'start_date' => '2026-09-14', 'end_date' => '2027-01-29', 'is_current' => true]);
    }

    private function course(string $code, $department): Course
    {
        $program = Program::firstOrCreate(['department_id' => $department->id, 'name' => $department->name], ['award' => 'B.Sc.', 'duration_years' => 4]);

        return Course::create([
            'code' => $code, 'title' => 'Title '.$code, 'units' => 3, 'level' => 300, 'semester' => 'First',
            'type' => 'core', 'department_id' => $department->id, 'program_id' => $program->id,
        ]);
    }

    public function test_only_super_admins_can_read_these_lists(): void
    {
        $department = $this->makeDepartment();

        foreach (self::PATHS as $path) {
            // Laravel keeps the last signed-in user inside one test, so reset it before a no-token request.
            $this->app['auth']->forgetGuards();
            $this->getJson("/api/admin/{$path}")->assertUnauthorized();
            foreach ([$this->makeDeptAdmin($department), $this->makeLecturer($department), $this->makeStudent($department)] as $user) {
                $this->getJson("/api/admin/{$path}", $this->bearer($user))->assertForbidden();
            }
        }
    }

    public function test_students_list_shape_search_and_filters(): void
    {
        $csc = $this->makeDepartment('CSC', 'Computer Science');
        $mth = $this->makeDepartment('MTH', 'Mathematics');
        $avery = $this->makeStudent($csc, ['name' => 'Avery Johnson']);
        $this->makeStudent($mth, ['name' => 'Priya Shah', 'status' => UserStatus::Inactive]);
        $grad = $this->makeStudent($csc, ['name' => 'Old Timer']);
        $grad->student->update(['graduated_at' => now(), 'level' => 400]);
        $admin = $this->makeSuperAdmin();

        $all = $this->getJson('/api/admin/students', $this->bearer($admin))
            ->assertOk()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonStructure(['data' => [['id', 'matricNo', 'name', 'email', 'departmentId', 'departmentName', 'facultyName', 'programId', 'programName', 'level', 'status']], 'meta' => ['current_page', 'last_page', 'total']]);
        $rows = collect($all->json('data'))->keyBy('name');
        $this->assertSame('Faculty of Science', $rows['Avery Johnson']['facultyName']);
        $this->assertSame((string) $avery->student->id, $rows['Avery Johnson']['id']);
        $this->assertSame('graduated', $rows['Old Timer']['status']);
        $this->assertSame('inactive', $rows['Priya Shah']['status']);
        $this->assertSame('active', $rows['Avery Johnson']['status']);

        $this->getJson('/api/admin/students?search=avery', $this->bearer($admin))->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/admin/students?q='.urlencode($avery->student->matric_no), $this->bearer($admin))->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson("/api/admin/students?departmentId={$mth->id}", $this->bearer($admin))->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/admin/students?level=400', $this->bearer($admin))->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/admin/students?status=graduated', $this->bearer($admin))->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/admin/students?status=active', $this->bearer($admin))->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/admin/students?per_page=1', $this->bearer($admin))->assertOk()->assertJsonPath('meta.last_page', 3);
    }

    public function test_lecturers_list_counts_current_session_courses_only(): void
    {
        $csc = $this->makeDepartment('CSC', 'Computer Science');
        $mth = $this->makeDepartment('MTH', 'Mathematics');
        $semester = $this->currentSemester();
        $old = AcademicSession::create(['name' => '2025/2026', 'start_date' => '2025-09-15', 'end_date' => '2026-07-31', 'is_current' => false]);
        $chen = $this->makeLecturer($csc, ['name' => 'Dr. Maya Chen']);
        $this->makeLecturer($mth, ['name' => 'Dr. Priya', 'status' => UserStatus::Suspended]);
        CourseAssignment::create(['course_id' => $this->course('CSC301', $csc)->id, 'lecturer_id' => $chen->lecturer->id, 'academic_session_id' => $semester->academic_session_id]);
        CourseAssignment::create(['course_id' => $this->course('CSC302', $csc)->id, 'lecturer_id' => $chen->lecturer->id, 'academic_session_id' => $semester->academic_session_id]);
        CourseAssignment::create(['course_id' => $this->course('CSC303', $csc)->id, 'lecturer_id' => $chen->lecturer->id, 'academic_session_id' => $old->id]);
        $admin = $this->makeSuperAdmin();

        $response = $this->getJson('/api/admin/lecturers?per_page=100', $this->bearer($admin))
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonStructure(['data' => [['id', 'name', 'email', 'departmentId', 'departmentName', 'specialization', 'courseCount', 'status']]]);
        $rows = collect($response->json('data'))->keyBy('name');
        $this->assertSame(2, $rows['Dr. Maya Chen']['courseCount']);
        $this->assertSame((string) $chen->lecturer->id, $rows['Dr. Maya Chen']['id']);
        $this->assertSame('suspended', $rows['Dr. Priya']['status']);

        $this->getJson("/api/admin/lecturers?departmentId={$mth->id}", $this->bearer($admin))->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/admin/lecturers?search=chen', $this->bearer($admin))->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/admin/lecturers?status=suspended', $this->bearer($admin))->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_registrations_list_shows_courses_units_and_filters(): void
    {
        $csc = $this->makeDepartment('CSC', 'Computer Science');
        $mth = $this->makeDepartment('MTH', 'Mathematics');
        $semester = $this->currentSemester();
        $avery = $this->makeStudent($csc, ['name' => 'Avery Johnson']);
        $priya = $this->makeStudent($mth, ['name' => 'Priya Shah']);
        $priya->student->update(['level' => 200]);
        $c1 = $this->course('CSC301', $csc);
        $c2 = $this->course('CSC302', $csc);

        $registration = Registration::create(['student_id' => $avery->student->id, 'semester_id' => $semester->id]);
        foreach ([$c1, $c2] as $course) {
            DB::table('registration_items')->insert(['registration_id' => $registration->id, 'course_id' => $course->id, 'created_at' => now(), 'updated_at' => now()]);
        }
        Registration::create(['student_id' => $priya->student->id, 'semester_id' => $semester->id]);
        $admin = $this->makeSuperAdmin();

        $response = $this->getJson('/api/admin/registrations', $this->bearer($admin))
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonStructure(['data' => [['id', 'studentId', 'studentName', 'matricNo', 'departmentName', 'level', 'sessionName', 'semester', 'courses', 'totalUnits', 'registeredAt']]]);
        $rows = collect($response->json('data'))->keyBy('studentName');
        $this->assertSame(6, $rows['Avery Johnson']['totalUnits']);
        $this->assertCount(2, $rows['Avery Johnson']['courses']);
        $this->assertSame(['courseId', 'code', 'title', 'units'], array_keys($rows['Avery Johnson']['courses'][0]));
        $this->assertSame('2026/2027', $rows['Avery Johnson']['sessionName']);
        $this->assertSame('First', $rows['Avery Johnson']['semester']);
        $this->assertSame(0, $rows['Priya Shah']['totalUnits']);

        $this->getJson('/api/admin/registrations?level=200', $this->bearer($admin))->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.studentName', 'Priya Shah');
        $this->getJson('/api/admin/registrations?search=avery', $this->bearer($admin))->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/admin/registrations?search=mathematics', $this->bearer($admin))->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_submissions_list_has_audit_trail_status_filter_and_student_count(): void
    {
        $csc = $this->makeDepartment('CSC', 'Computer Science');
        $semester = $this->currentSemester();
        $lecturer = $this->makeLecturer($csc, ['name' => 'Dr. Maya Chen']);
        $deptAdmin = $this->makeDeptAdmin($csc, ['name' => 'Jordan Reyes']);
        $a = $this->course('CSC301', $csc);
        $b = $this->course('CSC302', $csc);

        $submitted = ResultSubmission::create(['course_id' => $a->id, 'semester_id' => $semester->id, 'lecturer_id' => $lecturer->lecturer->id, 'status' => 'submitted', 'submitted_at' => now()]);
        ResultSubmission::create(['course_id' => $b->id, 'semester_id' => $semester->id, 'lecturer_id' => $lecturer->lecturer->id, 'status' => 'draft']);
        foreach ([$this->makeStudent($csc), $this->makeStudent($csc)] as $student) {
            Result::create(['result_submission_id' => $submitted->id, 'student_id' => $student->student->id, 'ca_score' => 20, 'exam_score' => 50]);
        }
        $audit = app(AuditLogger::class);
        $audit->record($lecturer, 'RESULT_SUBMITTED', 'CSC301', 'Submitted 2 results for review', $submitted);
        $audit->record($deptAdmin, 'RESULT_REJECTED', 'CSC301', 'Feedback: check exam scores', $submitted, ['note' => 'Exam scores look swapped.']);
        $admin = $this->makeSuperAdmin();

        $response = $this->getJson('/api/admin/submissions', $this->bearer($admin))
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonStructure(['data' => [['id', 'courseId', 'courseCode', 'courseTitle', 'departmentId', 'departmentName', 'lecturerName', 'sessionName', 'semester', 'studentCount', 'status', 'submittedAt', 'audit']]]);
        $rows = collect($response->json('data'))->keyBy('courseCode');
        $this->assertSame(2, $rows['CSC301']['studentCount']);
        $this->assertSame(0, $rows['CSC302']['studentCount']);
        $this->assertSame('Dr. Maya Chen', $rows['CSC301']['lecturerName']);
        $this->assertNull($rows['CSC302']['submittedAt']);
        $this->assertSame([], $rows['CSC302']['audit']);

        $trail = $rows['CSC301']['audit'];
        $this->assertCount(2, $trail);
        $this->assertSame(['id', 'at', 'actorName', 'actorRole', 'action', 'note'], array_keys($trail[0]));
        $this->assertSame('RESULT_SUBMITTED', $trail[0]['action']);
        $this->assertSame('lecturer', $trail[0]['actorRole']);
        $this->assertSame('Submitted 2 results for review', $trail[0]['note']);
        $this->assertSame('dept_admin', $trail[1]['actorRole']);
        $this->assertSame('Exam scores look swapped.', $trail[1]['note']);

        $this->getJson('/api/admin/submissions?status=submitted', $this->bearer($admin))->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.courseCode', 'CSC301');
        $this->getJson('/api/admin/submissions?status=nonsense', $this->bearer($admin))->assertOk()->assertJsonPath('meta.total', 2);
        $this->getJson('/api/admin/submissions?search=csc302', $this->bearer($admin))->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_activity_logs_filter_by_user_action_and_dates(): void
    {
        $csc = $this->makeDepartment();
        $lecturer = $this->makeLecturer($csc, ['name' => 'Dr. Maya Chen']);
        $admin = $this->makeSuperAdmin(['name' => 'Morgan Ellis']);
        $audit = app(AuditLogger::class);
        $audit->record($lecturer, 'RESULT_SUBMITTED', 'CSC301', 'Submitted 2 results');
        $audit->record($admin, 'USER_STATUS_CHANGED', 'Priya Shah', 'Suspended');
        $audit->record(null, 'SESSION_ACTIVATED', '2026/2027', 'Automatic');
        AuditLog::query()->where('action', 'RESULT_SUBMITTED')->update(['created_at' => '2026-11-01 09:00:00']);
        AuditLog::query()->where('action', 'USER_STATUS_CHANGED')->update(['created_at' => '2026-11-05 09:00:00']);
        AuditLog::query()->where('action', 'SESSION_ACTIVATED')->update(['created_at' => '2026-11-08 09:00:00']);

        $response = $this->getJson('/api/admin/activity-logs', $this->bearer($admin))
            ->assertOk()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonStructure(['data' => [['id', 'timestamp', 'userName', 'role', 'action', 'entity', 'details', 'ip']], 'meta' => ['current_page', 'last_page', 'total']]);
        $this->assertSame('SESSION_ACTIVATED', $response->json('data.0.action')); // newest first
        $this->assertSame('system', $response->json('data.0.role')); // system actions are readable, not a crash
        $this->assertSame('System', $response->json('data.0.userName'));
        $this->assertSame('lecturer', $response->json('data.2.role'));

        $this->getJson('/api/admin/activity-logs?user=maya', $this->bearer($admin))->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/admin/activity-logs?action=status', $this->bearer($admin))->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/admin/activity-logs?from=2026-11-05&to=2026-11-05', $this->bearer($admin))->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.action', 'USER_STATUS_CHANGED');
        $this->getJson('/api/admin/activity-logs?from=2026-11-02', $this->bearer($admin))->assertOk()->assertJsonPath('meta.total', 2);
        $this->getJson('/api/admin/activity-logs?to=2026-11-04', $this->bearer($admin))->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/admin/activity-logs?from=garbage', $this->bearer($admin))->assertOk()->assertJsonPath('meta.total', 3);
    }
}
