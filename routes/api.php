<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AttendanceController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\HrController;
use App\Http\Controllers\Api\EventController;
use Illuminate\Support\Facades\Artisan; // Added this

// PUBLIC ROUTES (No Token Needed)
Route::group(['prefix' => 'auth'], function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login',    [AuthController::class, 'login']);
});

// PROTECTED ROUTES (Token Required)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me',      [AuthController::class, 'me']);
    Route::get('/hr/interns', [HrController::class, 'getInternList']);
    Route::get('/events', [EventController::class, 'index']);
    Route::post('/events', [EventController::class, 'store']);
    Route::get('/hr/all-users', [HrController::class, 'getAllUsers']);
    Route::post('/hr/sub-users', [HrController::class, 'storeSubUser']);
    Route::post('/hr/update-permissions/{id}', [HrController::class, 'updatePermissions']);

    // Attendance
    Route::post('/attendance/log', [AttendanceController::class, 'logAttendance']);
});

// SECRET REMOTE MIGRATION ROUTE (Temporary)
Route::get('/run-migration', function () {
    try {
        // This runs 'php artisan migrate --force' inside Render
        Artisan::call('migrate', ['--force' => true]);
        return "<h1>Migration Success!</h1><pre>" . Artisan::output() . "</pre>";
    } catch (\Exception $e) {
        return "<h1>Migration Failed</h1><p>" . $e->getMessage() . "</p>";
    }
});