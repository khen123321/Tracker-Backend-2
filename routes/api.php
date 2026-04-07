<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AttendanceController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\HrController;
use App\Http\Controllers\Api\EventController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

// 1. PUBLIC ROUTES
Route::group(['prefix' => 'auth'], function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login',    [AuthController::class, 'login']);
});

// 2. PROTECTED ROUTES
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me',      [AuthController::class, 'me']);
    Route::get('/hr/interns',   [HrController::class, 'getInternList']);
    Route::get('/events',       [EventController::class, 'index']);
    Route::post('/events',      [EventController::class, 'store']);
    Route::get('/hr/all-users', [HrController::class, 'getAllUsers']);
    Route::post('/hr/sub-users', [HrController::class, 'storeSubUser']);
    Route::post('/hr/update-permissions/{id}', [HrController::class, 'updatePermissions']);
    Route::post('/attendance/log', [AttendanceController::class, 'logAttendance']);
});

// 3. SECRET ADMIN CREATOR (Run this once, then delete it)
Route::get('/create-admin', function () {
    try {
        $email = 'khenjoshua.verson@1.ustp.edu.ph';
        User::updateOrCreate(
            ['email' => $email],
            [
                'first_name' => 'Khen Joshua',
                'last_name'  => 'Verson',
                'password'   => Hash::make('admin123'),
                'role'       => 'hr', 
                'status'     => 'active',
            ]
        );
        return "<h1>Success!</h1><p>Admin account created for $email.</p>";
    } catch (\Exception $e) {
        return "Error: " . $e->getMessage();
    }
});