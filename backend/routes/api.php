<?php

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

    // T2.5: Route::prefix('admin')->middleware('role:super_admin')->group(...)
    // T2.6: Route::prefix('dept')->middleware('role:dept_admin')->group(...)
    // T2.7: Route::prefix('lecturer')->middleware('role:lecturer')->group(...)
    // T2.8: Route::prefix('student')->middleware('role:student')->group(...)
});
