# UEMP Technical Documentation

University Education Management Portal for Lead Web Praxis Media Limited.

## 1. Architecture

```
Browser (React SPA on Vercel)
   |  HTTPS, JSON, Authorization: Bearer <token>
   v
Laravel 13 API (Docker on Render, PHP 8.3)  -- /api/*
   |  PDO pgsql, SSL
   v
PostgreSQL (Neon)
```

- **Frontend:** Vite + React + TypeScript + Tailwind SPA (`frontend/`). One shell, four role areas (student, lecturer, dept admin, super admin). It only calls the API; it never decides permissions.
- **Backend:** Laravel API (`backend/`), stateless, Sanctum personal access tokens (12 hour expiry). No cookies or CSRF.
- **Database:** PostgreSQL on Neon (direct host for migrations). Tests run on in-memory SQLite and refuse to run on any other connection, so tests can never touch live data.
- **Layers in the backend:** routes (role groups) -> middleware (`auth:sanctum`, `active`, `role:`) -> controllers -> Form Requests (validation, camelCase error keys) -> services (`AuditLogger`, `GradingService`) -> API Resources (camelCase JSON, string ids).
- **Hosting:** API on Render (`render.yaml`, `backend/Dockerfile`, container start runs migrations); frontend on Vercel (`frontend/vercel.json` SPA rewrite).

## 2. Database design

ER diagram: `docs/er-diagram.png` (source `docs/er-diagram.mmd`, DBML in `docs/database-schema.dbml`).

Key decisions:

| Decision | Reason |
|---|---|
| One `users` table with `role` and `status`; `students` and `lecturers` are 1:1 profile tables | One login path; profile data stays separate |
| `users.department_id` is set only for dept admins | It is their scope; students and lecturers keep their department on their own profile |
| Student status is derived: `graduated` if `graduated_at` is set, else `users.status` | One source of truth |
| `registrations` unique per student + semester, `registration_items` unique per registration + course | Duplicate registration is impossible at database level |
| `course_assignments` unique per course + academic session | One lecturer per course per session |
| `result_submissions` unique per course + semester, with `results` per student | One workflow record per course; scores per student |
| Partial unique indexes on `is_current` for sessions and semesters | The database allows only one current session and one current semester |
| `audit_logs` append-only (model throws on update or delete), stores user name and role snapshot, polymorphic link to the record | Trail stays readable and cannot be edited |
| `restrictOnDelete` foreign keys on academic records | Parents with children cannot be deleted; controllers return a clear 422 first |

## 3. Roles and permission logic

Permissions are enforced on the backend in three layers; the frontend menus only mirror them.

1. **Authentication:** every route except `POST /api/login` needs a valid Sanctum token.
2. **Account status (`active` middleware):** inactive or suspended accounts get 401 and their token is deleted, so a suspension takes effect on the next request.
3. **Role (`role:` middleware):** `admin/*` needs `super_admin`, `dept/*` needs `dept_admin`, `lecturer/*` needs `lecturer`, `student/*` needs `student`. A denied request returns 403 and is written to the audit log (`ACCESS_DENIED`).
4. **Record scope (inside controllers):**
   - Dept admin: `DepartmentScope::apply()` on every list and `DepartmentScope::authorize()` on every single record (another department -> 403). A dept admin without a department sees nothing (fails closed). Courses created by a dept admin always use their own department, whatever the body says.
   - Lecturer: a course is theirs only if a `course_assignments` row exists for them in the current session, otherwise 403.
   - Student: no student id is accepted in any URL or body; every query starts from the logged-in student.

| Capability | Student | Lecturer | Dept Admin | Super Admin |
|---|:-:|:-:|:-:|:-:|
| Login, own profile (phone, address, date of birth only) | yes | | | |
| View available courses, register, view registrations | yes | | | |
| View **approved** results, history, CGPA | yes | | | |
| Class lists and score entry for assigned courses | | yes | | |
| Save draft, submit for review | | yes | | |
| Approve, reject (feedback required), reopen | | | own dept | |
| Manage courses, assign lecturers, students, lecturers | | | own dept | all |
| Faculties, departments, programs, sessions, semesters | | | | yes |
| Users: status, role | | | | yes |
| Registrations, results oversight, reports, activity log | | | own dept | all |

## 4. Business rules and where they are enforced

| Rule | Enforcement |
|---|---|
| Student registers only courses for own department, program, level and semester | `StudentRegistrationController::store` (422 keyed `courseIds.N`) |
| No duplicate registration in the same semester | Controller check (friendly 422, whole request atomic) plus unique index |
| Lecturer enters results only for assigned courses | `ownCourse()` (403) |
| Scores editable only in draft, rejected, reopened | `ResultStatus::isEditableByLecturer()`; submitted or approved give 422 |
| Submit needs both scores for every registered student | `LecturerResultController::submit` |
| Only submitted results can be approved or rejected; only approved can be reopened | `DepartmentSubmissionController` (row lock, status re-checked in a transaction) |
| Rejection needs feedback | Form validation (3 to 1000 characters) |
| Only the department's admin approves | Department scope (403 otherwise) |
| Students see only approved results | Results query filters on `approved`; a reopened result disappears |
| Every submission, approval, rejection, reopen, registration and admin change is audited | `AuditLogger::record()` (user, role, IP, action, entity, details, record link) |
| Changing own status or role, or leaving a dept admin without a department, is refused | `UserController` (422) |

## 5. Grading scale

Defined once in `backend/config/grading.php` and read by `GradingService`, the seeders and the admin grading page.

- CA out of **30**, exam out of **70**, total = CA + exam.
- 70-100 **A** (5), 60-69 **B** (4), 50-59 **C** (3), 45-49 **D** (2), 40-44 **E** (1), 0-39 **F** (0). A band matches when `total >= min`, so 69.5 is a B.
- **GPA = sum(grade point x course units) / sum(course units)**, rounded to 2 places. **CGPA** is the same formula over every approved course.

## 6. Security

- Passwords hashed (bcrypt); login rate limited (5 per minute per email and IP); unknown email takes the same time and returns the same message as a wrong password.
- Bearer tokens expire after 12 hours; logout revokes the token; suspension revokes all tokens.
- Validation on every write (Form Requests). Mass assignment limited with `$fillable`. Eloquent bindings prevent SQL injection; LIKE search escapes `%` and `_`.
- CORS limited to configured origins (`CORS_ALLOWED_ORIGINS`), no credentials.
- Production: `APP_DEBUG=false`, errors logged to stderr, JSON error responses for `/api/*`, secrets only in the host dashboard (never in git).
- CSV exports in the frontend are protected against formula injection.
- Audit log is append-only and snapshots the actor.

## 7. API overview

Base path `/api`. Lists return `{data, meta:{current_page,last_page,total}}` and accept `page`, `per_page`, `search` (or `q`). Errors return `{message, errors?}` with 401, 403, 404, 422. JSON is camelCase with string ids (the `User` object uses `department_id` and `department_name`).

- **Auth:** `POST /login`, `POST /logout`, `GET /me`
- **Student:** `GET/PUT /student/profile`, `GET /student/available-courses`, `GET/POST /student/registrations`, `GET /student/results`, `GET /student/academic-history`
- **Lecturer:** `GET /lecturer/dashboard|courses|submissions`, `GET /lecturer/courses/{id}/students|summary`, `PUT /lecturer/courses/{id}/results`, `POST /lecturer/courses/{id}/results/submit`
- **Dept admin:** `GET /dept/dashboard|students|lecturers|courses|registrations|programs|submissions|reports/{type}`, `GET /dept/submissions/{id}[/results]`, `POST /dept/submissions/{id}/approve|reject|reopen`, course create, update, delete, `POST /dept/courses/{id}/assign`, student and lecturer create, update, status
- **Super admin:** `GET /admin/dashboard|grading-scale|roles|reports/{type}`, CRUD for `faculties`, `departments`, `programs`, `sessions`, `semesters`, `courses`, `POST /admin/sessions/{id}/activate`, `POST /admin/semesters/{id}/activate`, `GET /admin/users`, `PATCH /admin/users/{id}/status|role`, `GET /admin/students|lecturers|registrations|submissions|activity-logs`

## 8. Testing

`cd backend && php artisan test`: **175 tests** (feature tests on SQLite) covering authentication, role boundaries, department scoping, every business rule above, the full lecturer-to-dept-admin-to-student result workflow, and audit logging.

## 9. Known limits

- Render's free tier sleeps after about 15 minutes idle (first request can take about a minute); Neon also autosuspends.
- No maximum-units rule or registration deadline (not in the specification).
- Super admin user creation and password reset are not built; users come from seed data or are created by dept admins (students, lecturers).
