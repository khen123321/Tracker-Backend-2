<?php
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AttendanceController;
use Illuminate\Support\Facades\Route;

// PUBLIC ROUTES (No Token Needed)
Route::group(['prefix' => 'auth'], function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login',    [AuthController::class, 'login']);
});

// PROTECTED ROUTES (Token Required)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me',      [AuthController::class, 'me']);

    // Attendance
    Route::post('/attendance/time-in', [AttendanceController::class, 'timeIn']);
    Route::post('/attendance/time-out', [AttendanceController::class, 'timeOut']);
    Route::get('/attendance/status', [AttendanceController::class, 'checkStatus']); 
});