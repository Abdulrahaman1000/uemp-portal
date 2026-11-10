<?php

namespace Tests\Feature\Admin;

use App\Models\AcademicSession;
use App\Models\Course;
use App\Models\CourseAssignment;
use App\Models\Department;
use App\Models\Program;
use App\Models\Registration;
use App\Models\ResultSubmission;
use App\Models\Semester;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesDemoData;
use Tests\TestCase;

class CourseApiTest extends TestCase
{
    use CreatesDemoData;

    private function currentSession(): AcademicSession
    {
        $session = AcademicSession::create([
            'name' => '2026/2027', 'start_date' => '2026-09-14', 'end_date' => '2027-07-30', 'is_current' => true,
        ]);
        Semester::create([
            'academic_session_id' => $session->id, 'name' => 'First',
            'start_date' => '2026-09-14', 'end_date' => '2027-01-29', 'is_current' => true,
        ]);

        return $session;
    }

    private function program(Department $department): Program
    {
        return Program::firstOrCreate(
            ['department_id' => $department->id, 'name' => $department->name],
            ['award' => 'B.Sc.', 'duration_years' => 4],
        );
    }

    private function course(Department $department, string $code = 'CSC301', int $level = 300): Course
    {
        return Course::create([
            'code' => $code, 'title' => 'Course '.$code, 'units' => 3, 'level' => $level, 'semester' => 'First',
            'type' => 'core', 'department_id' => $department->id, 'program_id' => $this->program($department)->id,
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(Department $department, array $overrides = []): array
    {
        return array_merge([
            'code' => 'CSC301',
            'title' => 'Data Structures',
            'units' => 3,
            'level' => 300,
            'semester' => 'First',
            'type' => 'core',
            'departmentId' => (string) $department->id,
            'programId' => (string) $this->program($department)->id,
            'lecturerId' => null,
        ], $overrides);
    }

    public function test_only_super_admins_can_use_the_course_endpoints(): void
    {
        $department = $this->makeDepartment();

        $this->getJson('/api/admin/courses')->assertUnauthorized();

        foreach ([$this->makeDeptAdmin($department), $this->makeLecturer($department), $this->makeStudent($department)] as $user) {
            $this->getJson('/api/admin/courses', $this->bearer($user))->assertForbidden();
            $this->postJson('/api/admin/courses', $this->payload($department), $this->bearer($user))->assertForbidden();
        }
    }

    public function test_list_shows_the_lecturer_of_the_current_session_only(): void
    {
        $department = $this->makeDepartment();
        $current = $this->currentSession();
        $old = AcademicSession::create(['name' => '2025/2026', 'start_date' => '2025-09-15', 'end_date' => '2026-07-31', 'is_current' => false]);
        $lecturer = $this->makeLecturer($department);

        $assigned = $this->course($department, 'CSC301');
        $oldOnly = $this->course($department, 'CSC302');
        CourseAssignment::create(['course_id' => $assigned->id, 'lecturer_id' => $lecturer->lecturer->id, 'academic_session_id' => $current->id]);
        CourseAssignment::create(['course_id' => $oldOnly->id, 'lecturer_id' => $lecturer->lecturer->id, 'academic_session_id' => $old->id]);

        $response = $this->getJson('/api/admin/courses', $this->bearer($this->makeSuperAdmin()))
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonStructure(['data' => [['id', 'code', 'title', 'units', 'level', 'semester', 'type', 'departmentId', 'departmentName', 'programId', 'lecturerId', 'lecturerName']], 'meta' => ['current_page', 'last_page', 'total']]);

        $rows = collect($response->json('data'))->keyBy('code');
        $this->assertSame((string) $lecturer->lecturer->id, $rows['CSC301']['lecturerId']);
        $this->assertSame($lecturer->name, $rows['CSC301']['lecturerName']);
        $this->assertSame('Computer Science', $rows['CSC301']['departmentName']);
        $this->assertNull($rows['CSC302']['lecturerId']);
        $this->assertNull($rows['CSC302']['lecturerName']);
        $this->assertIsString($rows['CSC301']['id']);
    }

    public function test_list_can_be_searched_by_code_or_title(): void
    {
        $csc = $this->makeDepartment('CSC', 'Computer Science');
        $mth = $this->makeDepartment('MTH', 'Mathematics');
        $this->course($csc, 'CSC301');
        $this->course($mth, 'MTH201');
        $admin = $this->makeSuperAdmin();

        $this->getJson('/api/admin/courses?search=csc', $this->bearer($admin))->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/admin/courses?q=mth', $this->bearer($admin))->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/admin/courses?search=course', $this->bearer($admin))->assertOk()->assertJsonPath('meta.total', 2);
    }

    public function test_super_admin_creates_a_course_and_the_code_is_normalised(): void
    {
        $department = $this->makeDepartment();
        $admin = $this->makeSuperAdmin();

        $this->postJson('/api/admin/courses', $this->payload($department, ['code' => ' csc 301 ']), $this->bearer($admin))
            ->assertCreated()
            ->assertJsonPath('code', 'CSC301')
            ->assertJsonPath('semester', 'First')
            ->assertJsonPath('lecturerId', null);

        $this->assertDatabaseHas('courses', ['code' => 'CSC301', 'department_id' => $department->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'RECORD_CREATED', 'entity' => 'CSC301', 'user_id' => $admin->id]);
    }

    public function test_creating_a_course_with_a_lecturer_assigns_them_for_the_current_session(): void
    {
        $department = $this->makeDepartment();
        $session = $this->currentSession();
        $lecturer = $this->makeLecturer($department);

        $this->postJson('/api/admin/courses', $this->payload($department, ['lecturerId' => (string) $lecturer->lecturer->id]), $this->bearer($this->makeSuperAdmin()))
            ->assertCreated()
            ->assertJsonPath('lecturerId', (string) $lecturer->lecturer->id)
            ->assertJsonPath('lecturerName', $lecturer->name);

        $this->assertDatabaseHas('course_assignments', ['lecturer_id' => $lecturer->lecturer->id, 'academic_session_id' => $session->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'COURSE_ASSIGNED', 'entity' => 'CSC301']);
    }

    public function test_assigning_a_lecturer_needs_a_current_session_and_rolls_back_the_course(): void
    {
        $department = $this->makeDepartment();
        $lecturer = $this->makeLecturer($department);

        $this->postJson('/api/admin/courses', $this->payload($department, ['lecturerId' => (string) $lecturer->lecturer->id]), $this->bearer($this->makeSuperAdmin()))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lecturerId']);

        $this->assertDatabaseMissing('courses', ['code' => 'CSC301']);
    }

    public function test_validation_errors_use_the_camel_case_field_names(): void
    {
        $csc = $this->makeDepartment('CSC', 'Computer Science');
        $mth = $this->makeDepartment('MTH', 'Mathematics');
        $this->course($csc, 'CSC301');
        $mthLecturer = $this->makeLecturer($mth);
        $admin = $this->makeSuperAdmin();

        $this->postJson('/api/admin/courses', [], $this->bearer($admin))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code', 'title', 'units', 'level', 'semester', 'type', 'departmentId', 'programId']);

        $this->postJson('/api/admin/courses', $this->payload($csc, ['units' => 0, 'level' => 350, 'semester' => 'Third', 'type' => 'optional']), $this->bearer($admin))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['units', 'level', 'semester', 'type']);

        $this->postJson('/api/admin/courses', $this->payload($csc), $this->bearer($admin))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code']); // CSC301 already exists

        $this->postJson('/api/admin/courses', $this->payload($csc, ['code' => 'CSC999', 'programId' => (string) $this->program($mth)->id]), $this->bearer($admin))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['programId']); // program belongs to another department

        $this->currentSession();
        $this->postJson('/api/admin/courses', $this->payload($csc, ['code' => 'CSC998', 'lecturerId' => (string) $mthLecturer->lecturer->id]), $this->bearer($admin))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lecturerId']); // lecturer belongs to another department
    }

    public function test_update_changes_fields_and_can_replace_or_remove_the_lecturer(): void
    {
        $department = $this->makeDepartment();
        $session = $this->currentSession();
        $first = $this->makeLecturer($department);
        $second = $this->makeLecturer($department);
        $course = $this->course($department);
        $admin = $this->makeSuperAdmin();

        $this->putJson("/api/admin/courses/{$course->id}", $this->payload($department, ['title' => 'Algorithms', 'lecturerId' => (string) $first->lecturer->id]), $this->bearer($admin))
            ->assertOk()
            ->assertJsonPath('title', 'Algorithms')
            ->assertJsonPath('lecturerName', $first->name);

        $this->putJson("/api/admin/courses/{$course->id}", $this->payload($department, ['title' => 'Algorithms', 'lecturerId' => (string) $second->lecturer->id]), $this->bearer($admin))
            ->assertOk()
            ->assertJsonPath('lecturerName', $second->name);
        $this->assertSame(1, CourseAssignment::where('course_id', $course->id)->count());

        $this->putJson("/api/admin/courses/{$course->id}", $this->payload($department, ['title' => 'Algorithms', 'lecturerId' => null]), $this->bearer($admin))
            ->assertOk()
            ->assertJsonPath('lecturerId', null);
        $this->assertDatabaseMissing('course_assignments', ['course_id' => $course->id, 'academic_session_id' => $session->id]);

        $this->assertDatabaseHas('audit_logs', ['action' => 'RECORD_UPDATED', 'entity' => 'CSC301']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'COURSE_UNASSIGNED', 'entity' => 'CSC301']);
    }

    public function test_update_of_an_unknown_course_is_404(): void
    {
        $department = $this->makeDepartment();

        $this->putJson('/api/admin/courses/9999', $this->payload($department), $this->bearer($this->makeSuperAdmin()))->assertNotFound();
    }

    public function test_a_lecturer_cannot_be_swapped_once_results_were_started(): void
    {
        $department = $this->makeDepartment();
        $session = $this->currentSession();
        $semester = $session->semesters()->first();
        $first = $this->makeLecturer($department);
        $second = $this->makeLecturer($department);
        $course = $this->course($department);
        CourseAssignment::create(['course_id' => $course->id, 'lecturer_id' => $first->lecturer->id, 'academic_session_id' => $session->id]);
        ResultSubmission::create(['course_id' => $course->id, 'semester_id' => $semester->id, 'lecturer_id' => $first->lecturer->id, 'status' => 'draft']);

        $this->putJson("/api/admin/courses/{$course->id}", $this->payload($department, ['lecturerId' => (string) $second->lecturer->id]), $this->bearer($this->makeSuperAdmin()))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lecturerId']);

        $this->assertDatabaseHas('course_assignments', ['course_id' => $course->id, 'lecturer_id' => $first->lecturer->id]);
    }

    public function test_academic_fields_are_locked_once_students_have_registered(): void
    {
        $department = $this->makeDepartment();
        $session = $this->currentSession();
        $semester = $session->semesters()->first();
        $course = $this->course($department);
        $student = $this->makeStudent($department)->student;
        $registration = Registration::create(['student_id' => $student->id, 'semester_id' => $semester->id]);
        DB::table('registration_items')->insert([
            'registration_id' => $registration->id, 'course_id' => $course->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $admin = $this->makeSuperAdmin();

        $this->putJson("/api/admin/courses/{$course->id}", $this->payload($department, ['level' => 400, 'units' => 4]), $this->bearer($admin))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['level', 'units']);

        // Changing only the title is still fine.
        $this->putJson("/api/admin/courses/{$course->id}", $this->payload($department, ['title' => 'Renamed']), $this->bearer($admin))
            ->assertOk()
            ->assertJsonPath('title', 'Renamed');
    }

    public function test_delete_removes_an_unused_course_but_blocks_a_course_with_records(): void
    {
        $department = $this->makeDepartment();
        $session = $this->currentSession();
        $semester = $session->semesters()->first();
        $unused = $this->course($department, 'CSC101', 100);
        $used = $this->course($department, 'CSC301');
        $lecturer = $this->makeLecturer($department);
        CourseAssignment::create(['course_id' => $unused->id, 'lecturer_id' => $lecturer->lecturer->id, 'academic_session_id' => $session->id]);
        ResultSubmission::create(['course_id' => $used->id, 'semester_id' => $semester->id, 'lecturer_id' => $lecturer->lecturer->id, 'status' => 'submitted']);
        $admin = $this->makeSuperAdmin();

        $this->deleteJson("/api/admin/courses/{$unused->id}", [], $this->bearer($admin))->assertNoContent();
        $this->assertDatabaseMissing('courses', ['id' => $unused->id]);
        $this->assertDatabaseMissing('course_assignments', ['course_id' => $unused->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'RECORD_DELETED', 'entity' => 'CSC101']);

        $response = $this->deleteJson("/api/admin/courses/{$used->id}", [], $this->bearer($admin))->assertStatus(422);
        $this->assertStringContainsString('Cannot delete', $response->json('message'));
        $this->assertDatabaseHas('courses', ['id' => $used->id]);
    }
}
