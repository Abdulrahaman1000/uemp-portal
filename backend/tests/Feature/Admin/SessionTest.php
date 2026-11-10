<?php

namespace Tests\Feature\Admin;

use App\Enums\ResultStatus;
use App\Models\AcademicSession;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\CourseAssignment;
use App\Models\Program;
use App\Models\Registration;
use App\Models\ResultSubmission;
use App\Models\Semester;
use Tests\Concerns\CreatesDemoData;
use Tests\TestCase;

/** T2.5b: academic sessions and semesters, including "only one is current". */
class SessionTest extends TestCase
{
    use CreatesDemoData;

    private function admin(): array
    {
        return $this->bearer($this->makeSuperAdmin());
    }

    /** A session with a First and a Second semester, like the API creates. */
    private function makeSession(string $name, bool $current = false, bool $firstCurrent = false): AcademicSession
    {
        [$from] = explode('/', $name);
        $year = (int) $from;

        $session = AcademicSession::create([
            'name' => $name,
            'start_date' => "{$year}-09-14",
            'end_date' => ($year + 1).'-07-30',
            'is_current' => $current,
        ]);
        Semester::create(['academic_session_id' => $session->id, 'name' => 'First', 'start_date' => "{$year}-09-14", 'end_date' => ($year + 1).'-01-29', 'is_current' => $firstCurrent]);
        Semester::create(['academic_session_id' => $session->id, 'name' => 'Second', 'start_date' => ($year + 1).'-02-15', 'end_date' => ($year + 1).'-07-30', 'is_current' => false]);

        return $session;
    }

    private function semester(AcademicSession $session, string $name): Semester
    {
        return $session->semesters()->where('name', $name)->firstOrFail();
    }

    private function lastLog(string $action): ?AuditLog
    {
        return AuditLog::where('action', $action)->latest('id')->first();
    }

    private function currentFlags(): array
    {
        return [
            'sessions' => AcademicSession::where('is_current', true)->pluck('name')->all(),
            'semesters' => Semester::where('is_current', true)->get()->map(fn ($s) => $s->session->name.' '.$s->name->value)->all(),
        ];
    }

    // ---------- access control ----------

    public function test_only_super_admin_can_reach_session_endpoints(): void
    {
        $session = $this->makeSession('2026/2027');
        $semester = $this->semester($session, 'First');

        foreach ([$this->makeDeptAdmin(), $this->makeLecturer(), $this->makeStudent()] as $user) {
            $headers = $this->bearer($user);
            $this->getJson('/api/admin/sessions', $headers)->assertForbidden();
            $this->postJson('/api/admin/sessions', [], $headers)->assertForbidden();
            $this->postJson("/api/admin/sessions/{$session->id}/activate", [], $headers)->assertForbidden();
            $this->postJson("/api/admin/semesters/{$semester->id}/activate", [], $headers)->assertForbidden();
            $this->deleteJson("/api/admin/sessions/{$session->id}", [], $headers)->assertForbidden();
            $this->getJson('/api/admin/semesters', $headers)->assertForbidden();
        }

        $this->assertSame([], $this->currentFlags()['sessions']);
        $this->assertNotNull(AcademicSession::find($session->id));
    }

    // ---------- listing ----------

    public function test_session_list_has_the_frontend_shape_newest_first_with_semesters(): void
    {
        $old = $this->makeSession('2025/2026');
        $new = $this->makeSession('2026/2027', true, true);

        $response = $this->getJson('/api/admin/sessions?per_page=50', $this->admin())->assertOk();

        $this->assertSame(['2026/2027', '2025/2026'], array_column($response->json('data'), 'name'));
        $response->assertJsonPath('meta.total', 2);

        $first = $response->json('data.0');
        $this->assertSame((string) $new->id, $first['id']);
        $this->assertSame('2026-09-14', $first['startDate']);
        $this->assertSame('2027-07-30', $first['endDate']);
        $this->assertTrue($first['isCurrent']);
        $this->assertSame(['First', 'Second'], array_column($first['semesters'], 'name'));
        $this->assertSame([
            'id' => (string) $this->semester($new, 'First')->id,
            'sessionId' => (string) $new->id,
            'name' => 'First',
            'startDate' => '2026-09-14',
            'endDate' => '2027-01-29',
            'isCurrent' => true,
        ], $first['semesters'][0]);
        $this->assertFalse($response->json('data.1.isCurrent'));
        $this->assertNotNull($old);
    }

    public function test_session_search_by_name(): void
    {
        $this->makeSession('2025/2026');
        $this->makeSession('2026/2027');

        $this->getJson('/api/admin/sessions?search=2025', $this->admin())->assertJsonPath('meta.total', 1);
        $this->getJson('/api/admin/sessions?q=2026/2027', $this->admin())->assertJsonPath('meta.total', 1);
    }

    // ---------- create ----------

    public function test_creating_a_session_also_creates_its_two_semesters(): void
    {
        $admin = $this->makeSuperAdmin();

        $response = $this->postJson('/api/admin/sessions', [
            'name' => '2027/2028', 'startDate' => '2027-09-13', 'endDate' => '2028-07-28',
        ], $this->bearer($admin))->assertCreated();

        $this->assertSame('2027/2028', $response->json('name'));
        $this->assertFalse($response->json('isCurrent'));
        $this->assertCount(2, $response->json('semesters'));

        [$first, $second] = $response->json('semesters');
        $this->assertSame('First', $first['name']);
        $this->assertSame('2027-09-13', $first['startDate']);
        $this->assertSame('2028-01-28', $first['endDate']);
        $this->assertSame('Second', $second['name']);
        $this->assertSame('2028-02-13', $second['startDate']);
        $this->assertSame('2028-07-28', $second['endDate']);
        $this->assertFalse($first['isCurrent'] || $second['isCurrent']);

        $log = $this->lastLog('RECORD_CREATED');
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame('Session 2027/2028', $log->entity);
        $this->assertSame(2, Semester::count());
    }

    public function test_session_validation_errors_use_camel_case_keys(): void
    {
        $headers = $this->admin();

        $this->postJson('/api/admin/sessions', [], $headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'startDate', 'endDate']);

        $this->postJson('/api/admin/sessions', ['name' => '2027-2028', 'startDate' => '2027-09-13', 'endDate' => '2028-07-28'], $headers)
            ->assertUnprocessable()
            ->assertJsonPath('errors.name.0', 'Use the format 2027/2028 for the session name.');

        $this->postJson('/api/admin/sessions', ['name' => '2027/2030', 'startDate' => '2027-09-13', 'endDate' => '2028-07-28'], $headers)
            ->assertUnprocessable()
            ->assertJsonPath('errors.name.0', 'The second year must follow the first, for example 2027/2028.');

        $this->postJson('/api/admin/sessions', ['name' => '2027/2028', 'startDate' => '13/09/2027', 'endDate' => '2028-07-28'], $headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['startDate']);

        $this->assertSame(0, AcademicSession::count());
    }

    public function test_session_must_be_long_enough_and_end_after_it_starts(): void
    {
        $headers = $this->admin();

        $this->postJson('/api/admin/sessions', ['name' => '2027/2028', 'startDate' => '2028-07-28', 'endDate' => '2027-09-13'], $headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['endDate']);

        $this->postJson('/api/admin/sessions', ['name' => '2027/2028', 'startDate' => '2027-09-13', 'endDate' => '2027-12-01'], $headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['endDate']);

        $this->assertSame(0, AcademicSession::count());
    }

    public function test_duplicate_session_name_is_rejected(): void
    {
        $this->makeSession('2026/2027');

        $this->postJson('/api/admin/sessions', ['name' => '2026/2027', 'startDate' => '2026-09-14', 'endDate' => '2027-07-30'], $this->admin())
            ->assertUnprocessable()
            ->assertJsonPath('errors.name.0', 'This session already exists.');

        $this->assertSame(1, AcademicSession::count());
    }

    // ---------- update ----------

    public function test_session_can_be_renamed_and_dates_changed_within_its_semesters(): void
    {
        $session = $this->makeSession('2026/2027');

        $this->putJson("/api/admin/sessions/{$session->id}", ['name' => '2026/2027', 'startDate' => '2026-09-01', 'endDate' => '2027-08-15'], $this->admin())
            ->assertOk()
            ->assertJsonPath('startDate', '2026-09-01')
            ->assertJsonPath('endDate', '2027-08-15');

        $this->assertNotNull($this->lastLog('RECORD_UPDATED'));
    }

    public function test_session_dates_cannot_shrink_past_its_semesters(): void
    {
        $session = $this->makeSession('2026/2027');

        $this->putJson("/api/admin/sessions/{$session->id}", ['name' => '2026/2027', 'startDate' => '2026-09-14', 'endDate' => '2027-04-01'], $this->admin())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['startDate']);

        $this->assertSame('2027-07-30', $session->fresh()->end_date->toDateString());
    }

    public function test_session_update_cannot_change_the_current_flag(): void
    {
        $session = $this->makeSession('2026/2027');

        $this->putJson("/api/admin/sessions/{$session->id}", ['name' => '2026/2027', 'startDate' => '2026-09-14', 'endDate' => '2027-07-30', 'isCurrent' => true], $this->admin())
            ->assertOk()
            ->assertJsonPath('isCurrent', false);

        $this->assertFalse($session->fresh()->is_current);
    }

    // ---------- activate session ----------

    public function test_activating_a_session_makes_it_and_its_first_semester_the_only_current_ones(): void
    {
        $old = $this->makeSession('2025/2026', true);
        $this->semester($old, 'Second')->update(['is_current' => true]); // old session was in its Second semester
        $new = $this->makeSession('2026/2027');
        $admin = $this->makeSuperAdmin();

        $response = $this->postJson("/api/admin/sessions/{$new->id}/activate", [], $this->bearer($admin))
            ->assertOk()
            ->assertJsonPath('name', '2026/2027')
            ->assertJsonPath('isCurrent', true);

        $this->assertSame(['First', 'Second'], array_column($response->json('semesters'), 'name'));
        $this->assertSame([
            'sessions' => ['2026/2027'],
            'semesters' => ['2026/2027 First'],
        ], $this->currentFlags());

        $log = $this->lastLog('SESSION_ACTIVATED');
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame('Session 2026/2027', $log->entity);
        $this->assertSame('2025/2026', $log->metadata['previousSession']);
    }

    public function test_activating_the_first_session_ever_works(): void
    {
        $session = $this->makeSession('2026/2027');

        $this->postJson("/api/admin/sessions/{$session->id}/activate", [], $this->admin())->assertOk();

        $this->assertSame(['sessions' => ['2026/2027'], 'semesters' => ['2026/2027 First']], $this->currentFlags());
    }

    public function test_activating_the_current_session_again_is_a_422_and_changes_nothing(): void
    {
        $session = $this->makeSession('2026/2027', true);
        $this->semester($session, 'Second')->update(['is_current' => true]);

        $this->postJson("/api/admin/sessions/{$session->id}/activate", [], $this->admin())
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Session 2026/2027 is already the current session.');

        $this->assertSame(['2026/2027 Second'], $this->currentFlags()['semesters']); // not reset to First
        $this->assertNull($this->lastLog('SESSION_ACTIVATED'));
    }

    public function test_a_session_without_semesters_cannot_be_activated(): void
    {
        $session = AcademicSession::create(['name' => '2030/2031', 'start_date' => '2030-09-01', 'end_date' => '2031-07-30']);

        $this->postJson("/api/admin/sessions/{$session->id}/activate", [], $this->admin())->assertUnprocessable();

        $this->assertSame([], $this->currentFlags()['sessions']);
    }

    public function test_unknown_session_is_404(): void
    {
        $this->postJson('/api/admin/sessions/9999/activate', [], $this->admin())->assertNotFound();
        $this->getJson('/api/admin/sessions/9999', $this->admin())->assertNotFound();
    }

    // ---------- activate semester ----------

    public function test_activating_a_semester_of_the_current_session_switches_only_the_semester(): void
    {
        $session = $this->makeSession('2026/2027', true, true);
        $second = $this->semester($session, 'Second');
        $admin = $this->makeSuperAdmin();

        $this->postJson("/api/admin/semesters/{$second->id}/activate", [], $this->bearer($admin))
            ->assertOk()
            ->assertJsonPath('name', 'Second')
            ->assertJsonPath('isCurrent', true);

        $this->assertSame(['sessions' => ['2026/2027'], 'semesters' => ['2026/2027 Second']], $this->currentFlags());

        $log = $this->lastLog('SEMESTER_ACTIVATED');
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame('2026/2027 Second semester', $log->entity);
    }

    public function test_activating_a_semester_of_another_session_moves_the_session_too(): void
    {
        $this->makeSession('2025/2026', true, true);
        $other = $this->makeSession('2026/2027');

        $this->postJson('/api/admin/semesters/'.$this->semester($other, 'Second')->id.'/activate', [], $this->admin())->assertOk();

        $this->assertSame(['sessions' => ['2026/2027'], 'semesters' => ['2026/2027 Second']], $this->currentFlags());
    }

    public function test_activating_the_current_semester_again_is_a_422(): void
    {
        $session = $this->makeSession('2026/2027', true, true);

        $this->postJson('/api/admin/semesters/'.$this->semester($session, 'First')->id.'/activate', [], $this->admin())
            ->assertUnprocessable()
            ->assertJsonPath('message', 'The 2026/2027 First semester is already current.');
    }

    // ---------- delete session ----------

    public function test_the_current_session_cannot_be_deleted(): void
    {
        $session = $this->makeSession('2026/2027', true, true);

        $this->deleteJson("/api/admin/sessions/{$session->id}", [], $this->admin())
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Cannot delete "2026/2027": it is the current session. Set another session as current first.');

        $this->assertNotNull(AcademicSession::find($session->id));
    }

    public function test_an_unused_session_is_deleted_with_its_semesters_and_audited(): void
    {
        $session = $this->makeSession('2024/2025');

        $this->deleteJson("/api/admin/sessions/{$session->id}", [], $this->admin())->assertNoContent();

        $this->assertNull(AcademicSession::find($session->id));
        $this->assertSame(0, Semester::count());
        $this->assertNotNull($this->lastLog('RECORD_DELETED'));
    }

    public function test_a_session_with_registrations_cannot_be_deleted(): void
    {
        $session = $this->makeSession('2025/2026');
        $student = $this->makeStudent()->student;
        Registration::create(['student_id' => $student->id, 'semester_id' => $this->semester($session, 'First')->id, 'registered_at' => now()]);

        $this->deleteJson("/api/admin/sessions/{$session->id}", [], $this->admin())
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Cannot delete "2025/2026": it still has 1 registration.');

        $this->assertSame(2, Semester::count());
    }

    public function test_a_session_with_course_assignments_or_submissions_cannot_be_deleted(): void
    {
        $session = $this->makeSession('2025/2026');
        $department = $this->makeDepartment();
        $program = Program::create(['department_id' => $department->id, 'name' => 'Computer Science', 'award' => 'B.Sc.', 'duration_years' => 4]);
        $course = Course::create(['code' => 'CSC101', 'title' => 'Intro', 'units' => 3, 'level' => 100, 'semester' => 'First', 'type' => 'core', 'department_id' => $department->id, 'program_id' => $program->id]);
        $lecturer = $this->makeLecturer($department)->lecturer;
        CourseAssignment::create(['course_id' => $course->id, 'lecturer_id' => $lecturer->id, 'academic_session_id' => $session->id]);
        ResultSubmission::create(['course_id' => $course->id, 'semester_id' => $this->semester($session, 'First')->id, 'lecturer_id' => $lecturer->id, 'status' => ResultStatus::Draft]);

        $this->deleteJson("/api/admin/sessions/{$session->id}", [], $this->admin())
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Cannot delete "2025/2026": it still has 1 result submission and 1 course assignment.');
    }

    // ---------- semesters CRUD ----------

    public function test_semester_list_and_session_filter(): void
    {
        $a = $this->makeSession('2025/2026');
        $this->makeSession('2026/2027');

        $this->getJson('/api/admin/semesters', $this->admin())->assertJsonPath('meta.total', 4);
        $this->getJson("/api/admin/semesters?sessionId={$a->id}", $this->admin())
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.sessionId', (string) $a->id);
    }

    public function test_a_missing_semester_can_be_added_back(): void
    {
        $session = $this->makeSession('2026/2027');
        $this->semester($session, 'Second')->delete();

        $this->postJson('/api/admin/semesters', [
            'sessionId' => (string) $session->id, 'name' => 'Second', 'startDate' => '2027-02-15', 'endDate' => '2027-07-30',
        ], $this->admin())
            ->assertCreated()
            ->assertJsonPath('name', 'Second')
            ->assertJsonPath('isCurrent', false);

        $this->assertSame(2, $session->semesters()->count());
    }

    public function test_semester_validation_rules(): void
    {
        $session = $this->makeSession('2026/2027');
        $headers = $this->admin();
        $base = ['sessionId' => $session->id, 'name' => 'Second', 'startDate' => '2027-02-15', 'endDate' => '2027-07-30'];

        $this->postJson('/api/admin/semesters', [], $headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sessionId', 'name', 'startDate', 'endDate']);

        $this->postJson('/api/admin/semesters', $base, $headers) // Second already exists
            ->assertUnprocessable()
            ->assertJsonPath('errors.name.0', 'This session already has a Second semester.');

        $this->semester($session, 'Second')->delete();

        $this->postJson('/api/admin/semesters', array_merge($base, ['name' => 'Third']), $headers)
            ->assertUnprocessable()->assertJsonValidationErrors(['name']);

        $this->postJson('/api/admin/semesters', array_merge($base, ['startDate' => '2027-08-01', 'endDate' => '2027-07-01']), $headers)
            ->assertUnprocessable()->assertJsonValidationErrors(['endDate']);

        $this->postJson('/api/admin/semesters', array_merge($base, ['endDate' => '2027-09-30']), $headers) // outside the session
            ->assertUnprocessable()->assertJsonValidationErrors(['startDate']);

        $this->postJson('/api/admin/semesters', array_merge($base, ['startDate' => '2027-01-20']), $headers) // overlaps First (ends 2027-01-29)
            ->assertUnprocessable()
            ->assertJsonPath('errors.startDate.0', 'These dates overlap another semester of the same session.');

        $this->assertSame(1, $session->semesters()->count());
    }

    public function test_semester_dates_can_be_updated_but_it_cannot_move_session(): void
    {
        $a = $this->makeSession('2025/2026');
        $b = $this->makeSession('2026/2027');
        $first = $this->semester($a, 'First');
        $headers = $this->admin();

        $this->putJson("/api/admin/semesters/{$first->id}", ['sessionId' => $a->id, 'name' => 'First', 'startDate' => '2025-09-15', 'endDate' => '2026-01-30'], $headers)
            ->assertOk()
            ->assertJsonPath('startDate', '2025-09-15')
            ->assertJsonPath('endDate', '2026-01-30');
        $this->assertNotNull($this->lastLog('RECORD_UPDATED'));

        $this->putJson("/api/admin/semesters/{$first->id}", ['sessionId' => $b->id, 'name' => 'First', 'startDate' => '2026-09-14', 'endDate' => '2027-01-29'], $headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sessionId']);
    }

    public function test_current_semester_cannot_be_deleted_but_others_can(): void
    {
        $session = $this->makeSession('2026/2027', true, true);
        $headers = $this->admin();

        $this->deleteJson('/api/admin/semesters/'.$this->semester($session, 'First')->id, [], $headers)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Cannot delete "2026/2027 First semester": it is the current semester. Set another semester as current first.');

        $this->deleteJson('/api/admin/semesters/'.$this->semester($session, 'Second')->id, [], $headers)->assertNoContent();

        $this->assertSame(1, $session->semesters()->count());
        $this->assertNotNull($this->lastLog('RECORD_DELETED'));
    }

    public function test_semester_with_registrations_cannot_be_deleted(): void
    {
        $session = $this->makeSession('2025/2026');
        $second = $this->semester($session, 'Second');
        $student = $this->makeStudent()->student;
        Registration::create(['student_id' => $student->id, 'semester_id' => $second->id, 'registered_at' => now()]);

        $this->deleteJson("/api/admin/semesters/{$second->id}", [], $this->admin())
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Cannot delete "2025/2026 Second semester": it still has 1 registration.');

        $this->assertNotNull(Semester::find($second->id));
    }
}
