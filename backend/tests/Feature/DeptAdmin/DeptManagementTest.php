<?php

namespace Tests\Feature\DeptAdmin;

use App\Models\Course;
use App\Models\Program;
use Tests\Concerns\CreatesDemoData;
use Tests\Concerns\CreatesWorkflowData;
use Tests\TestCase;

/** T2.6c: the department admin manages courses, assignments, students and lecturers of their own department only. */
class DeptManagementTest extends TestCase
{
    use CreatesDemoData;
    use CreatesWorkflowData;

    private function courseBody(int $programId, array $over = []): array
    {
        return array_merge([
            'code' => 'csc 401', 'title' => 'Compilers', 'units' => 3, 'level' => 400,
            'semester' => 'First', 'type' => 'core', 'programId' => $programId, 'lecturerId' => null,
        ], $over);
    }

    public function test_course_is_created_in_the_admins_own_department_whatever_the_body_says(): void
    {
        $csc = $this->makeDepartment('CSC', 'Computer Science');
        $mth = $this->makeDepartment('MTH', 'Mathematics');
        $program = Program::create(['department_id' => $csc->id, 'name' => 'Computer Science', 'award' => 'B.Sc.', 'duration_years' => 4]);
        $admin = $this->makeDeptAdmin($csc);

        $this->postJson('/api/dept/courses', $this->courseBody($program->id, ['departmentId' => $mth->id]), $this->bearer($admin))
            ->assertCreated()
            ->assertJsonPath('code', 'CSC401')
            ->assertJsonPath('departmentId', (string) $csc->id);

        $this->assertDatabaseHas('audit_logs', ['action' => 'RECORD_CREATED', 'entity' => 'CSC401']);
    }

    public function test_program_of_another_department_is_rejected(): void
    {
        $csc = $this->makeDepartment('CSC', 'Computer Science');
        $mth = $this->makeDepartment('MTH', 'Mathematics');
        $other = Program::create(['department_id' => $mth->id, 'name' => 'Mathematics', 'award' => 'B.Sc.', 'duration_years' => 4]);
        $admin = $this->makeDeptAdmin($csc);

        $this->postJson('/api/dept/courses', $this->courseBody($other->id), $this->bearer($admin))
            ->assertStatus(422)->assertJsonValidationErrors('programId');
    }

    public function test_cannot_update_delete_or_assign_a_course_of_another_department(): void
    {
        $csc = $this->makeDepartment('CSC', 'Computer Science');
        $mth = $this->makeDepartment('MTH', 'Mathematics');
        $this->currentSemester();
        $foreign = $this->makeCourse('MTH301', $mth);
        $mthLecturer = $this->makeLecturer($mth);
        $admin = $this->makeDeptAdmin($csc);
        $h = $this->bearer($admin);

        $this->putJson("/api/dept/courses/{$foreign->id}", $this->courseBody($foreign->program_id, ['code' => 'MTH301']), $h)->assertForbidden();
        $this->deleteJson("/api/dept/courses/{$foreign->id}", [], $h)->assertForbidden();
        $this->postJson("/api/dept/courses/{$foreign->id}/assign", ['lecturerId' => $mthLecturer->lecturer->id], $h)->assertForbidden();
        $this->assertDatabaseHas('courses', ['id' => $foreign->id]);
    }

    public function test_assign_and_unassign_a_lecturer(): void
    {
        $csc = $this->makeDepartment('CSC', 'Computer Science');
        $mth = $this->makeDepartment('MTH', 'Mathematics');
        $this->currentSemester();
        $course = $this->makeCourse('CSC301', $csc);
        $lecturer = $this->makeLecturer($csc, ['name' => 'Dr. Maya Chen']);
        $outsider = $this->makeLecturer($mth);
        $admin = $this->makeDeptAdmin($csc);
        $h = $this->bearer($admin);

        $this->postJson("/api/dept/courses/{$course->id}/assign", ['lecturerId' => $lecturer->lecturer->id], $h)
            ->assertOk()->assertJsonPath('lecturerName', 'Dr. Maya Chen');
        $this->assertDatabaseHas('audit_logs', ['action' => 'COURSE_ASSIGNED']);

        $this->postJson("/api/dept/courses/{$course->id}/assign", ['lecturerId' => $outsider->lecturer->id], $h)
            ->assertStatus(422)->assertJsonValidationErrors('lecturerId');

        $this->postJson("/api/dept/courses/{$course->id}/assign", ['lecturerId' => null], $h)
            ->assertOk()->assertJsonPath('lecturerId', null);
        $this->assertDatabaseHas('audit_logs', ['action' => 'COURSE_UNASSIGNED']);
    }

    public function test_course_update_and_delete_work_in_own_department(): void
    {
        $csc = $this->makeDepartment('CSC', 'Computer Science');
        $this->currentSemester();
        $course = $this->makeCourse('CSC301', $csc);
        $admin = $this->makeDeptAdmin($csc);
        $h = $this->bearer($admin);

        $this->putJson("/api/dept/courses/{$course->id}", $this->courseBody($course->program_id, ['code' => 'CSC301', 'title' => 'New title', 'level' => 300]), $h)
            ->assertOk()->assertJsonPath('title', 'New title');
        $this->deleteJson("/api/dept/courses/{$course->id}", [], $h)->assertNoContent();
        $this->assertNull(Course::find($course->id));
    }

    public function test_student_is_created_in_own_department_and_can_log_in(): void
    {
        $csc = $this->makeDepartment('CSC', 'Computer Science');
        $mth = $this->makeDepartment('MTH', 'Mathematics');
        $program = Program::create(['department_id' => $csc->id, 'name' => 'Computer Science', 'award' => 'B.Sc.', 'duration_years' => 4]);
        $admin = $this->makeDeptAdmin($csc);

        $body = ['name' => 'New Student', 'email' => 'New.Student@northstar.edu', 'password' => 'secret123', 'matricNo' => 'csc/2026/001', 'programId' => $program->id, 'level' => 100, 'departmentId' => $mth->id];
        $this->postJson('/api/dept/students', $body, $this->bearer($admin))
            ->assertCreated()
            ->assertJsonPath('matricNo', 'CSC/2026/001')
            ->assertJsonPath('departmentId', (string) $csc->id)
            ->assertJsonPath('status', 'active');

        $this->app['auth']->forgetGuards();
        $this->postJson('/api/login', ['email' => 'new.student@northstar.edu', 'password' => 'secret123'])->assertOk()->assertJsonPath('user.role', 'student');
    }

    public function test_student_create_validates_and_rejects_duplicates_and_foreign_programs(): void
    {
        $csc = $this->makeDepartment('CSC', 'Computer Science');
        $mth = $this->makeDepartment('MTH', 'Mathematics');
        $program = Program::create(['department_id' => $csc->id, 'name' => 'Computer Science', 'award' => 'B.Sc.', 'duration_years' => 4]);
        $foreign = Program::create(['department_id' => $mth->id, 'name' => 'Mathematics', 'award' => 'B.Sc.', 'duration_years' => 4]);
        $existing = $this->makeStudent($csc);
        $admin = $this->makeDeptAdmin($csc);
        $h = $this->bearer($admin);

        $ok = ['name' => 'X Y', 'email' => 'x@northstar.edu', 'password' => 'secret123', 'matricNo' => 'CSC/2026/050', 'programId' => $program->id, 'level' => 100];
        $this->postJson('/api/dept/students', array_merge($ok, ['email' => $existing->email]), $h)->assertStatus(422)->assertJsonValidationErrors('email');
        $this->postJson('/api/dept/students', array_merge($ok, ['matricNo' => $existing->student->matric_no]), $h)->assertStatus(422)->assertJsonValidationErrors('matricNo');
        $this->postJson('/api/dept/students', array_merge($ok, ['programId' => $foreign->id]), $h)->assertStatus(422)->assertJsonValidationErrors('programId');
        $this->postJson('/api/dept/students', array_merge($ok, ['password' => 'short']), $h)->assertStatus(422)->assertJsonValidationErrors('password');
        $this->postJson('/api/dept/students', array_merge($ok, ['level' => 150]), $h)->assertStatus(422)->assertJsonValidationErrors('level');
    }

    public function test_student_update_and_status_are_scoped_and_status_revokes_tokens(): void
    {
        $csc = $this->makeDepartment('CSC', 'Computer Science');
        $mth = $this->makeDepartment('MTH', 'Mathematics');
        $own = $this->makeStudent($csc);
        $foreign = $this->makeStudent($mth);
        $admin = $this->makeDeptAdmin($csc);
        $h = $this->bearer($admin);

        $this->patchJson("/api/dept/students/{$foreign->student->id}/status", ['status' => 'suspended'], $h)->assertForbidden();
        $this->putJson("/api/dept/students/{$foreign->student->id}", ['name' => 'Hacked', 'email' => 'h@x.com', 'programId' => $foreign->student->program_id, 'level' => 100], $h)->assertForbidden();

        $own->createToken('t');
        $this->patchJson("/api/dept/students/{$own->student->id}/status", ['status' => 'suspended'], $h)
            ->assertOk()->assertJsonPath('status', 'suspended');
        $this->assertSame(0, $own->tokens()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'USER_STATUS_CHANGED']);

        $this->putJson("/api/dept/students/{$own->student->id}", ['name' => 'Renamed', 'email' => $own->email, 'programId' => $own->student->program_id, 'level' => 400], $h)
            ->assertOk()->assertJsonPath('name', 'Renamed')->assertJsonPath('level', 400);
    }

    public function test_lecturer_create_update_status_are_scoped(): void
    {
        $csc = $this->makeDepartment('CSC', 'Computer Science');
        $mth = $this->makeDepartment('MTH', 'Mathematics');
        $foreign = $this->makeLecturer($mth);
        $admin = $this->makeDeptAdmin($csc);
        $h = $this->bearer($admin);

        $created = $this->postJson('/api/dept/lecturers', ['name' => 'Dr. New', 'email' => 'new.lec@northstar.edu', 'password' => 'secret123', 'specialization' => 'AI'], $h)
            ->assertCreated()->assertJsonPath('departmentId', (string) $csc->id)->assertJsonPath('courseCount', 0);
        $id = $created->json('id');

        $this->putJson("/api/dept/lecturers/{$id}", ['name' => 'Dr. Newer', 'email' => 'new.lec@northstar.edu', 'specialization' => 'ML'], $h)
            ->assertOk()->assertJsonPath('specialization', 'ML');
        $this->patchJson("/api/dept/lecturers/{$id}/status", ['status' => 'inactive'], $h)->assertOk()->assertJsonPath('status', 'inactive');
        $this->patchJson("/api/dept/lecturers/{$foreign->lecturer->id}/status", ['status' => 'inactive'], $h)->assertForbidden();
        $this->putJson("/api/dept/lecturers/{$foreign->lecturer->id}", ['name' => 'X', 'email' => 'x@x.com'], $h)->assertForbidden();
    }

    public function test_programs_list_is_limited_to_own_department_and_roles_are_enforced(): void
    {
        $csc = $this->makeDepartment('CSC', 'Computer Science');
        $mth = $this->makeDepartment('MTH', 'Mathematics');
        Program::create(['department_id' => $csc->id, 'name' => 'Computer Science', 'award' => 'B.Sc.', 'duration_years' => 4]);
        Program::create(['department_id' => $mth->id, 'name' => 'Mathematics', 'award' => 'B.Sc.', 'duration_years' => 4]);
        $admin = $this->makeDeptAdmin($csc);

        $this->getJson('/api/dept/programs', $this->bearer($admin))->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.departmentName', 'Computer Science');

        foreach ([$this->makeStudent($csc), $this->makeLecturer($csc), $this->makeSuperAdmin()] as $other) {
            $this->postJson('/api/dept/students', [], $this->bearer($other))->assertForbidden();
            $this->postJson('/api/dept/courses', [], $this->bearer($other))->assertForbidden();
        }
    }
}
