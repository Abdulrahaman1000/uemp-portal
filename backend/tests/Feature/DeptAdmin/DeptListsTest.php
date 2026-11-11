<?php

namespace Tests\Feature\DeptAdmin;

use App\Models\Registration;
use Tests\Concerns\CreatesDemoData;
use Tests\Concerns\CreatesWorkflowData;
use Tests\TestCase;

class DeptListsTest extends TestCase
{
    use CreatesDemoData;
    use CreatesWorkflowData;

    public function test_dashboard_counts_only_the_own_department(): void
    {
        $csc = $this->makeDepartment('CSC', 'Computer Science');
        $mth = $this->makeDepartment('MTH', 'Mathematics');
        $semester = $this->currentSemester();
        $this->makeStudent($csc);
        $this->makeStudent($csc);
        $this->makeStudent($mth);
        $cscLecturer = $this->makeLecturer($csc);
        $mthLecturer = $this->makeLecturer($mth);
        $this->makeSubmission($this->makeCourse('CSC301', $csc), $semester, $cscLecturer, 'submitted');
        $this->makeSubmission($this->makeCourse('CSC302', $csc), $semester, $cscLecturer, 'draft');
        $this->makeSubmission($this->makeCourse('MTH301', $mth), $semester, $mthLecturer, 'submitted');
        $admin = $this->makeDeptAdmin($csc);

        $this->getJson('/api/dept/dashboard', $this->bearer($admin))
            ->assertOk()
            ->assertJsonPath('departmentName', 'Computer Science')
            ->assertJsonPath('totalStudents', 2)
            ->assertJsonPath('totalLecturers', 1)
            ->assertJsonPath('totalCourses', 2)
            ->assertJsonPath('activePrograms', 1)
            ->assertJsonPath('pendingSubmissions', 1)
            ->assertJsonPath('submissionsByStatus.submitted', 1)
            ->assertJsonPath('submissionsByStatus.draft', 1)
            ->assertJsonPath('submissionsByStatus.approved', 0)
            ->assertJsonPath('currentSession', '2026/2027')
            ->assertJsonPath('currentSemester', 'First');
    }

    public function test_students_and_lecturers_are_limited_to_the_department(): void
    {
        $csc = $this->makeDepartment('CSC', 'Computer Science');
        $mth = $this->makeDepartment('MTH', 'Mathematics');
        $avery = $this->makeStudent($csc, ['name' => 'Avery Johnson']);
        $this->makeStudent($csc, ['name' => 'Old Timer'])->student->update(['level' => 400]);
        $this->makeStudent($mth, ['name' => 'Priya Shah']);
        $this->makeLecturer($csc, ['name' => 'Dr. Maya Chen']);
        $this->makeLecturer($mth, ['name' => 'Dr. Other']);
        $admin = $this->makeDeptAdmin($csc);

        $students = $this->getJson('/api/dept/students', $this->bearer($admin))
            ->assertOk()->assertJsonPath('meta.total', 2)
            ->assertJsonStructure(['data' => [['id', 'matricNo', 'name', 'email', 'departmentName', 'programName', 'level', 'status']]]);
        $this->assertSame(['Avery Johnson', 'Old Timer'], collect($students->json('data'))->pluck('name')->all());

        $this->getJson('/api/dept/students?search=priya', $this->bearer($admin))->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson('/api/dept/students?search=avery', $this->bearer($admin))->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/dept/students?q='.urlencode($avery->student->matric_no), $this->bearer($admin))->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/dept/students?level=400', $this->bearer($admin))->assertOk()->assertJsonPath('meta.total', 1);

        $this->getJson('/api/dept/lecturers', $this->bearer($admin))
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.name', 'Dr. Maya Chen')
            ->assertJsonStructure(['data' => [['id', 'name', 'email', 'departmentName', 'specialization', 'courseCount', 'status']]]);
        $this->getJson('/api/dept/lecturers?search=other', $this->bearer($admin))->assertOk()->assertJsonPath('meta.total', 0);
    }

    public function test_courses_are_limited_to_the_department(): void
    {
        $csc = $this->makeDepartment('CSC', 'Computer Science');
        $mth = $this->makeDepartment('MTH', 'Mathematics');
        $this->makeCourse('CSC301', $csc);
        $this->makeCourse('CSC302', $csc);
        $this->makeCourse('MTH301', $mth);
        $admin = $this->makeDeptAdmin($csc);

        $response = $this->getJson('/api/dept/courses', $this->bearer($admin))
            ->assertOk()->assertJsonPath('meta.total', 2)
            ->assertJsonStructure(['data' => [['id', 'code', 'title', 'units', 'level', 'semester', 'type', 'departmentId', 'lecturerId', 'lecturerName']]]);
        $this->assertSame(['CSC301', 'CSC302'], collect($response->json('data'))->pluck('code')->all());

        $this->getJson('/api/dept/courses?search=mth', $this->bearer($admin))->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson('/api/dept/courses?level=300&semester=First', $this->bearer($admin))->assertOk()->assertJsonPath('meta.total', 2);
        $this->getJson('/api/dept/courses?semester=Second', $this->bearer($admin))->assertOk()->assertJsonPath('meta.total', 0);
    }

    public function test_registrations_are_limited_to_the_department(): void
    {
        $csc = $this->makeDepartment('CSC', 'Computer Science');
        $mth = $this->makeDepartment('MTH', 'Mathematics');
        $semester = $this->currentSemester();
        $avery = $this->makeStudent($csc, ['name' => 'Avery Johnson']);
        $priya = $this->makeStudent($mth, ['name' => 'Priya Shah']);
        Registration::create(['student_id' => $avery->student->id, 'semester_id' => $semester->id]);
        Registration::create(['student_id' => $priya->student->id, 'semester_id' => $semester->id]);
        $admin = $this->makeDeptAdmin($csc);

        $this->getJson('/api/dept/registrations', $this->bearer($admin))
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.studentName', 'Avery Johnson')
            ->assertJsonStructure(['data' => [['id', 'studentId', 'studentName', 'matricNo', 'departmentName', 'level', 'sessionName', 'semester', 'courses', 'totalUnits', 'registeredAt']]]);
        $this->getJson('/api/dept/registrations?search=priya', $this->bearer($admin))->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson('/api/dept/registrations?level=200', $this->bearer($admin))->assertOk()->assertJsonPath('meta.total', 0);
    }

    public function test_reports_cover_only_the_own_department(): void
    {
        $csc = $this->makeDepartment('CSC', 'Computer Science');
        $mth = $this->makeDepartment('MTH', 'Mathematics');
        $semester = $this->currentSemester();
        $first = $this->makeStudent($csc);
        $this->makeStudent($csc)->student->update(['level' => 200]);
        $this->makeStudent($mth);
        Registration::create(['student_id' => $first->student->id, 'semester_id' => $semester->id]);
        $lecturer = $this->makeLecturer($csc);
        $this->makeSubmission($this->makeCourse('CSC301', $csc), $semester, $lecturer, 'submitted');
        $this->makeSubmission($this->makeCourse('MTH301', $mth), $semester, $this->makeLecturer($mth), 'approved');
        $admin = $this->makeDeptAdmin($csc);

        $this->getJson('/api/dept/reports/students', $this->bearer($admin))
            ->assertOk()->assertExactJson([['label' => '200 Level', 'value' => 1], ['label' => '300 Level', 'value' => 1]]);
        $this->getJson('/api/dept/reports/registrations', $this->bearer($admin))
            ->assertOk()->assertExactJson([['label' => '300 Level', 'value' => 1]]);

        $results = $this->getJson('/api/dept/reports/results', $this->bearer($admin))->assertOk();
        $this->assertSame(
            ['Draft' => 0, 'Submitted' => 1, 'Approved' => 0, 'Rejected' => 0, 'Reopened' => 0],
            collect($results->json())->pluck('value', 'label')->all(),
        );

        $this->getJson('/api/dept/reports/unknown', $this->bearer($admin))->assertNotFound();
    }
}
