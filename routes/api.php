<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage; // ✨ REQUIRED FOR FILE PROXY
use App\Models\User;
use App\Models\School;
use App\Models\Department;
use App\Models\Branch;               
use App\Models\RequirementSetting;

// Include ALL Controllers
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\HrController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\InternDashboardController;
use App\Http\Controllers\Api\FormRequestController;
use App\Http\Controllers\HR\DashboardController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\InternController;

use Exception;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// ==========================================
// Public Routes (No Token Required)
// ==========================================
Route::group(['prefix' => 'auth'], function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login',    [AuthController::class, 'login']);
});

// HR Public Stats for Landing/Login
Route::get('/hr/dashboard-stats', [DashboardController::class, 'getStats']);

Route::prefix('public')->group(function () {
    // Schools for Signup Dropdown
    Route::get('/schools', function() {
        return response()->json(School::orderBy('name', 'asc')->get());
    });

    // Courses for Signup Dropdown (Filtered by School)
    Route::get('/courses/{school_id}', function($school_id) {
        $courses = RequirementSetting::where('school_id', $school_id)
                        ->select('course_name')
                        ->distinct()
                        ->get();
        return response()->json($courses);
    });

    // Public Branches Route
    Route::get('/branches', function() {
        return response()->json(Branch::orderBy('name', 'asc')->get());
    });

    // Departments Route for Signup
    Route::get('/departments', function() {
        return response()->json(Department::orderBy('name', 'asc')->get());
    });
});

// ✨ THE CORS BYPASS: Serves local images from storage/app/public ✨
Route::get('/get-avatar', function (Request $request) {
    // This will be "avatars/filename.jpg"
    $path = $request->query('path');

    // Look specifically inside the 'public' disk
    if (Storage::disk('public')->exists($path)) {
        $file = Storage::disk('public')->get($path);
        $type = Storage::disk('public')->mimeType($path);
        
        // Return the raw image, but wrapped in Laravel's CORS middleware!
        return response($file, 200)->header('Content-Type', $type);
    }

    return response()->json(['error' => 'File not found'], 404);
});


// ==========================================
// Protected Routes (Requires Sanctum Token)
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

    // ✨ THE MISSING NOTIFICATION READ ROUTE (Fixes the 404 error) ✨
    Route::post('/notifications/{id}/read', function (Request $request, $id) {
        $notification = $request->user()->notifications()->find($id);
        if ($notification) {
            $notification->markAsRead();
            return response()->json(['message' => 'Notification marked as read!']);
        }
        return response()->json(['message' => 'Notification not found.'], 404);
    });

    Route::get('/intern/notifications', [AttendanceController::class, 'getNotifications']);

    // --- HR Management ---
    Route::get('/hr/interns',    [HrController::class, 'getInternList']);
    Route::get('/hr/all-users',  [HrController::class, 'getAllUsers']);
    Route::post('/hr/sub-users', [HrController::class, 'storeSubUser']);
    Route::post('/hr/update-permissions/{id}', [HrController::class, 'updatePermissions']);
    Route::get('/hr/users-roles', [HrController::class, 'getRoleUsers']);
    Route::put('/hr/users-roles/{id}', [HrController::class, 'updateUserAccess']);
    Route::post('/hr/users', [HrController::class, 'storeSubUser']); 
    Route::get('/hr/interns/{id}/document/{type}', [InternController::class, 'viewDocument']);
    
    // ✨ HR Dashboard Chart Data ✨
    Route::get('/hr/dashboard/schools', [DashboardController::class, 'getSchools']);
    
    // ✨ HR Dashboard Pending Requests Processing ✨
    Route::get('/hr/requests/{id}', [FormRequestController::class, 'show']); 
    Route::post('/hr/requests/{id}/process', [FormRequestController::class, 'processRequest']);
    
    // --- Events & Intern Attendance ---
    Route::get('/events',   [EventController::class, 'index']);
    Route::post('/events',  [EventController::class, 'store']);
    Route::get('/hr/interns/{id}/attendance', [AttendanceController::class, 'getInternAttendanceForHR']);
    
    // Camera Verification Routes
    Route::get('/hr/attendance/verification', [HrController::class, 'getVerificationLogs']);
    Route::post('/hr/attendance/{id}/verify', [HrController::class, 'verifyAttendanceAction']);
    
    // --- Intern Dashboard & Forms ---
    Route::get('/intern/dashboard-stats', [InternDashboardController::class, 'getStats']);
    Route::post('/intern/forms/submit',   [FormRequestController::class, 'store']);
    Route::get('/event-filters', [EventController::class, 'getFilters']);

    // --- HR Settings (Requirements & Schools) ---
    Route::get('/hr/settings/requirements', [SettingsController::class, 'getRequirements']);
    Route::post('/hr/settings/requirements', [SettingsController::class, 'storeRequirement']);
    Route::delete('/hr/settings/requirements/{id}', [SettingsController::class, 'deleteRequirement']);
    Route::get('/hr/settings/schools', [SettingsController::class, 'getSchools']);

    // --- HR Settings (Manage Departments) ---
    Route::get('/hr/settings/departments', function () {
        return response()->json(Department::orderBy('name', 'asc')->get());
    });

    Route::post('/hr/settings/departments', function (Request $request) {
        $request->validate([
            'name' => 'required|string|max:255',
            'supervisor_name' => 'required|string|max:255',
        ]);
        $dept = Department::create($request->all());
        return response()->json($dept, 201);
    });

    Route::delete('/hr/settings/departments/{id}', function ($id) {
        Department::destroy($id);
        return response()->json(['message' => 'Deleted successfully']);
    });

    // --- HR Settings (Manage Branches & Geofencing) ---
    Route::get('/hr/settings/branches', function () {
        return response()->json(Branch::orderBy('name', 'asc')->get());
    });

    Route::post('/hr/settings/branches', function (Request $request) {
        $request->validate([
            'name' => 'required|string|max:255',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'radius' => 'nullable|integer'
        ]);
        $branch = Branch::create($request->all());
        return response()->json($branch, 201);
    });

    Route::delete('/hr/settings/branches/{id}', function ($id) {
        Branch::destroy($id);
        return response()->json(['message' => 'Branch deleted successfully']);
    });

    // --- Intern Profiles & Uploads ---
    
    // Standard Profile Fetch (Handles /hr/interns/me and standard ID fetching)
    Route::get('/hr/interns/{id}', [InternController::class, 'show']);
    
    // Dedicated HR Endpoint for specific relationships to prevent conflicts
    Route::get('/hr/intern-data/{id}', [InternController::class, 'showForHr']);
    
    // File Upload Routes
    Route::post('/intern/upload-avatar', [InternController::class, 'uploadAvatar']);
    Route::post('/intern/upload-document', [InternController::class, 'uploadDocument']);
    
    // Bulk Actions
    Route::post('/hr/interns/bulk-remove', [HrController::class, 'bulkRemove']);
    Route::post('/hr/interns/bulk-export', [HrController::class, 'bulkExport']);
    Route::post('/hr/interns/bulk-add-hours', [HrController::class, 'bulkAddHours']);

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