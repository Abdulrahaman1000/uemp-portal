<?php

use App\Http\Controllers\Api\Admin\CourseController;
use App\Http\Controllers\Api\Admin\DepartmentController;
use App\Http\Controllers\Api\Admin\FacultyController;
use App\Http\Controllers\Api\Admin\OverviewController;
use App\Http\Controllers\Api\Admin\OversightController;
use App\Http\Controllers\Api\Admin\ProgramController;
use App\Http\Controllers\Api\Admin\SemesterController;
use App\Http\Controllers\Api\Admin\SessionController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DeptAdmin\DepartmentDashboardController;
use App\Http\Controllers\Api\DeptAdmin\DepartmentListController;
use App\Http\Controllers\Api\DeptAdmin\DepartmentReportController;
use App\Http\Controllers\Api\DeptAdmin\DepartmentSubmissionController;
use App\Http\Controllers\Api\Lecturer\LecturerCourseController;
use App\Http\Controllers\Api\Lecturer\LecturerDashboardController;
use App\Http\Controllers\Api\Lecturer\LecturerResultController;
use App\Http\Controllers\Api\Student\StudentProfileController;
use App\Http\Controllers\Api\Student\StudentRegistrationController;
use App\Http\Controllers\Api\Student\StudentResultController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| UEMP API (all paths are prefixed with /api)
|--------------------------------------------------------------------------
| Authentication uses Sanctum bearer tokens only (no cookies, no CSRF).
| Every protected route needs a valid token ("auth:sanctum") and an active
| account ("active"). Role groups are added with the role middleware, e.g.
| middleware('role:super_admin').
*/

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // ---------- Super Admin (T2.5) ----------
    Route::prefix('admin')->middleware('role:super_admin')->group(function () {
        // T2.5a: overview
        Route::get('/dashboard', [OverviewController::class, 'dashboard']);
        Route::get('/grading-scale', [OverviewController::class, 'gradingScale']);
        Route::get('/roles', [OverviewController::class, 'roles']);
        Route::get('/reports/{type}', [OverviewController::class, 'report'])
            ->whereIn('type', ['students', 'registrations', 'results']);

        // T2.5b: faculties, departments, programs, sessions and semesters
        Route::apiResource('faculties', FacultyController::class)->parameters(['faculties' => 'faculty']);
        Route::apiResource('departments', DepartmentController::class);
        Route::apiResource('programs', ProgramController::class);
        Route::apiResource('sessions', SessionController::class);
        Route::post('/sessions/{session}/activate', [SessionController::class, 'activate']);
        Route::apiResource('semesters', SemesterController::class);
        Route::post('/semesters/{semester}/activate', [SemesterController::class, 'activate']);
        // T2.5c: courses and users
        Route::get('/courses', [CourseController::class, 'index']);
        Route::post('/courses', [CourseController::class, 'store']);
        Route::put('/courses/{course}', [CourseController::class, 'update']);
        Route::delete('/courses/{course}', [CourseController::class, 'destroy']);
        Route::get('/users', [UserController::class, 'index']);
        Route::patch('/users/{user}/status', [UserController::class, 'updateStatus']);
        Route::patch('/users/{user}/role', [UserController::class, 'updateRole']);
        // T2.5d: read-only lists
        Route::get('/students', [OversightController::class, 'students']);
        Route::get('/lecturers', [OversightController::class, 'lecturers']);
        Route::get('/registrations', [OversightController::class, 'registrations']);
        Route::get('/submissions', [OversightController::class, 'submissions']);
        Route::get('/activity-logs', [OversightController::class, 'activityLogs']);
    });

    // ---------- Department Admin (T2.6): every query is limited to the admin's own department ----------
    Route::prefix('dept')->middleware('role:dept_admin')->group(function () {
        // T2.6a: dashboard and the result approval workflow
        Route::get('/dashboard', [DepartmentDashboardController::class, 'dashboard']);
        Route::get('/submissions', [DepartmentSubmissionController::class, 'index']);
        Route::get('/submissions/{submission}', [DepartmentSubmissionController::class, 'show']);
        Route::get('/submissions/{submission}/results', [DepartmentSubmissionController::class, 'results']);
        Route::post('/submissions/{submission}/approve', [DepartmentSubmissionController::class, 'approve']);
        Route::post('/submissions/{submission}/reject', [DepartmentSubmissionController::class, 'reject']);
        Route::post('/submissions/{submission}/reopen', [DepartmentSubmissionController::class, 'reopen']);
        // T2.6b: read-only lists and reports
        Route::get('/students', [DepartmentListController::class, 'students']);
        Route::get('/lecturers', [DepartmentListController::class, 'lecturers']);
        Route::get('/courses', [DepartmentListController::class, 'courses']);
        Route::get('/registrations', [DepartmentListController::class, 'registrations']);
        Route::get('/reports/{type}', [DepartmentReportController::class, 'show'])
            ->whereIn('type', ['students', 'registrations', 'results']);
        // T2.6c (later): course create/update/assign, student and lecturer edits
    });

    // ---------- Lecturer (T2.7): only courses assigned to the lecturer in the current session ----------
    Route::prefix('lecturer')->middleware('role:lecturer')->group(function () {
        Route::get('/dashboard', [LecturerDashboardController::class, 'dashboard']);
        Route::get('/courses', [LecturerCourseController::class, 'index']);
        Route::get('/courses/{course}/students', [LecturerCourseController::class, 'students']);
        Route::get('/courses/{course}/summary', [LecturerCourseController::class, 'summary']);
        Route::put('/courses/{course}/results', [LecturerResultController::class, 'save']);
        Route::post('/courses/{course}/results/submit', [LecturerResultController::class, 'submit']);
        Route::get('/submissions', [LecturerResultController::class, 'submissions']);
    });

    // ---------- Student (T2.8): every query starts from the signed-in student's own profile ----------
    Route::prefix('student')->middleware('role:student')->group(function () {
        Route::get('/profile', [StudentProfileController::class, 'show']);
        Route::put('/profile', [StudentProfileController::class, 'update']);
        Route::get('/available-courses', [StudentRegistrationController::class, 'available']);
        Route::post('/registrations', [StudentRegistrationController::class, 'store']);
        Route::get('/registrations', [StudentRegistrationController::class, 'index']);
        Route::get('/results', [StudentResultController::class, 'index']);
        Route::get('/academic-history', [StudentResultController::class, 'history']);
    });
});
