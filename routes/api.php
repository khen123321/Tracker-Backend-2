<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

// Include ALL Controllers
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\HrController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\InternDashboardController;
use App\Http\Controllers\Api\FormRequestController;
use App\Http\Controllers\HR\DashboardController;
use Exception;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// ==========================================
// Public Routes
// ==========================================
Route::group(['prefix' => 'auth'], function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login',    [AuthController::class, 'login']);
});

// HR Public Stats
Route::get('/hr/dashboard-stats', [DashboardController::class, 'getStats']);

// ==========================================
// Protected Routes (Requires Login Token)
// ==========================================
Route::middleware('auth:sanctum')->group(function () {
    
    // --- Auth ---
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me',      [AuthController::class, 'me']);
    
    // --- Attendance System ---
    Route::post('/attendance/log', [AttendanceController::class, 'logAttendance']);
    Route::get('/attendance/history', [AttendanceController::class, 'getHistory']);

    // --- Notifications ---
    Route::get('/notifications', function (Request $request) {
        return $request->user()->notifications()->orderBy('created_at', 'desc')->get();
    });
    
    Route::post('/notifications/mark-as-read', function (Request $request) {
        $request->user()->unreadNotifications->markAsRead();
        return response()->json(['message' => 'Notifications marked as read']);
    });

    // --- HR Management ---
    Route::get('/hr/interns',    [HrController::class, 'getInternList']);
    Route::get('/hr/all-users',  [HrController::class, 'getAllUsers']);
    Route::post('/hr/sub-users', [HrController::class, 'storeSubUser']);
    Route::post('/hr/update-permissions/{id}', [HrController::class, 'updatePermissions']);
    
    // --- Events ---
    Route::get('/events',   [EventController::class, 'index']);
    Route::post('/events',  [EventController::class, 'store']);
    
    // --- Intern Dashboard & Forms ---
    Route::get('/intern/dashboard-stats', [InternDashboardController::class, 'getStats']);
    Route::post('/intern/forms/submit',   [FormRequestController::class, 'store']);
    Route::get('/event-filters', [EventController::class, 'getFilters']);
});

// ==========================================
// Setup Utility
// ==========================================
Route::get('/create-admin', function () {
    try {
        $user = User::updateOrCreate(
            ['email' => 'testadmin123@gmail.com'],
            [
                'first_name' => 'Khen Joshua',
                'last_name'  => 'Verson',
                'password'   => Hash::make('testadmin123'),
                'role'       => 'superadmin', 
                'status'     => 'active',
            ]
        );
        return "<h1>Success!</h1><p>Superadmin account created for testadmin123@gmail.com.</p>";
    } catch (Exception $e) {
        return "Error: " . $e->getMessage();
    }
});