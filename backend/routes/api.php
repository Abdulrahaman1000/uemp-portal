<?php

use App\Http\Controllers\Api\Admin\DepartmentController;
use App\Http\Controllers\Api\Admin\FacultyController;
use App\Http\Controllers\Api\Admin\OverviewController;
use App\Http\Controllers\Api\Admin\ProgramController;
use App\Http\Controllers\Api\Admin\SemesterController;
use App\Http\Controllers\Api\Admin\SessionController;
use App\Http\Controllers\Api\AuthController;
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
        // T2.5d: read-only lists (students, lecturers, registrations, submissions, activity logs)
    });

    // T2.6: Route::prefix('dept')->middleware('role:dept_admin')->group(...)
    // T2.7: Route::prefix('lecturer')->middleware('role:lecturer')->group(...)
    // T2.8: Route::prefix('student')->middleware('role:student')->group(...)
});
