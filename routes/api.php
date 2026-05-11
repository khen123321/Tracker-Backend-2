<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail; 
use App\Models\User;
use App\Models\School;
use App\Models\Department;
use App\Models\Branch;
use App\Models\RequirementSetting;
use Carbon\Carbon; 

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\HrController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\InternDashboardController;
use App\Http\Controllers\Api\FormRequestController;
use App\Http\Controllers\HR\DashboardController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\InternController;
use App\Http\Controllers\Api\UserController; 
use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PasswordResetController;

use Exception;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// ==========================================
// Public Routes (No Token Required)
// ==========================================

// ✨ THE TIME SYNC API
Route::get('/server-time', function () {
    return response()->json([
        'timestamp' => Carbon::now('Asia/Manila')->timestamp * 1000 
    ]);
});

// ✨ THE UNIFIED VERIFICATION ROUTE (SPA OPTIMIZED) ✨
Route::get('/email/verify/{id}/{hash}', function (Request $request, $id, $hash) {
    // 1. Check if the URL was tampered with or expired
    if (!$request->hasValidSignature()) {
        return response()->json(['message' => 'Invalid or expired verification link.'], 401);
    }

    // 2. Find the user
    $user = User::findOrFail($id);

    // 3. Mark as verified if not already
    if (!$user->hasVerifiedEmail()) {
        $user->email_verified_at = now(); 
        $user->status = 'active';        
        $user->save();                    
    }

    return response()->json(['message' => 'Email verified successfully!']);
})->name('verification.verify'); 


// --- Standard Public Routes ---
Route::group(['prefix' => 'auth'], function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login',    [AuthController::class, 'login']);
});

// ✨ FORGOT & RESET PASSWORD ROUTES ✨
Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink']);
Route::post('/reset-password', [PasswordResetController::class, 'resetPassword'])->name('password.reset');


Route::get('/hr/dashboard-stats', [DashboardController::class, 'getStats']);

Route::prefix('public')->group(function () {
    Route::get('/schools', function () {
        return response()->json(School::orderBy('name', 'asc')->get());
    });

    Route::get('/courses/{school_id}', function ($school_id) {
        $courses = RequirementSetting::where('school_id', $school_id)
            ->select('course_name')
            ->distinct()
            ->get();
        return response()->json($courses);
    });

    Route::get('/branches', function () {
        return response()->json(Branch::orderBy('name', 'asc')->get());
    });

    Route::get('/departments', function () {
        return response()->json(Department::orderBy('name', 'asc')->get());
    });
});

// Image proxy — bypasses CORS for storage images
Route::get('/get-avatar', function (Request $request) {
    $path = $request->query('path');
    $path = str_replace('storage/', '', $path);

    if (!Storage::disk('public')->exists($path)) {
        return response()->json(['error' => 'File not found'], 404);
    }

    $file = Storage::disk('public')->get($path);

    // ← Replace mimeType() with this — no finfo needed
    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $mimeMap = [
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
    ];
    $type = $mimeMap[$extension] ?? 'application/octet-stream';

    return response($file, 200)
        ->header('Content-Type', $type)
        ->header('Access-Control-Allow-Origin', '*');
});

// ==========================================
// Protected Routes (Requires Sanctum Token)
// ==========================================
Route::middleware('auth:sanctum')->group(function () {

    // ── Auth ──────────────────────────────────────────────────────────────────
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me',      [AuthController::class, 'me']);

    // ── Attendance ────────────────────────────────────────────────────────────
    Route::post('/attendance/log',     [AttendanceController::class, 'logAttendance']);
    Route::get('/attendance/history',  [AttendanceController::class, 'getHistory']);
    Route::get('/intern/notifications', [AttendanceController::class, 'getNotifications']);

    // ── Announcements ─────────────────────────────────────────────────────────
    Route::get('/announcements', [AnnouncementController::class, 'index']);
    Route::post('/announcements/{id}/read', [AnnouncementController::class, 'markAsRead']);
    Route::post('/announcements', [AnnouncementController::class, 'store']);
    Route::put('/announcements/{id}', [AnnouncementController::class, 'update']);
    Route::delete('/announcements/{id}', [AnnouncementController::class, 'destroy']);
    Route::put('/announcements/{id}/pin', [AnnouncementController::class, 'pin']);
    Route::put('/announcements/{id}/unpin', [AnnouncementController::class, 'unpin']);

    // ── Appeals System ────────────────────────────────────────────────────────
    Route::post('/attendance/logs/{id}/appeal', [AttendanceController::class, 'submitAppeal']);
    Route::get('/appeals/{id}/download', [AttendanceController::class, 'downloadAppealFile']);
    Route::get('/hr/appeals', [AttendanceController::class, 'getAppeals']);
    Route::get('/hr/appeals/stats', [AttendanceController::class, 'getAppealStats']);
    Route::post('/hr/appeals/{id}/approve', [AttendanceController::class, 'approveAppeal']);
    Route::post('/hr/appeals/{id}/reject', [AttendanceController::class, 'rejectAppeal']);

    // ── HR Management ─────────────────────────────────────────────────────────
    Route::get('/hr/interns',    [HrController::class, 'getInternList']);
    Route::get('/hr/all-users',  [HrController::class, 'getAllUsers']);
    Route::post('/hr/sub-users', [HrController::class, 'storeSubUser']);
    Route::post('/hr/update-permissions/{id}', [HrController::class, 'updatePermissions']);
    Route::get('/hr/users-roles',      [HrController::class, 'getRoleUsers']);
    Route::put('/hr/users-roles/{id}', [HrController::class, 'updateUserAccess']);
    Route::get('/hr/interns/{id}/document/{type}', [InternController::class, 'viewDocument']);
    Route::get('/hr/activity-logs',    [HrController::class, 'getActivityLogs']);
    Route::post('/hr/users',           [UserController::class, 'store']); 
    Route::put('/hr/users/{id}/force-reset-password', [UserController::class, 'forceResetPassword']);
    Route::get('/hr/dashboard/schools',        [DashboardController::class, 'getSchools']);
    
    // ✨ FIXED: Added the missing index route and updated prefixes for forms & requests
    Route::get('/hr/forms-requests',           [FormRequestController::class, 'index']);
    Route::get('/hr/forms-requests/{id}',      [FormRequestController::class, 'show']);
    Route::post('/hr/forms-requests/{id}/process', [FormRequestController::class, 'processRequest']);

    // ── Events ────────────────────────────────────────────────────────────────
    Route::get('/events',  [EventController::class, 'index']);
    Route::post('/events', [EventController::class, 'store']);
    Route::get('/event-filters', [EventController::class, 'getFilters']);
    Route::delete('/events/{id}', [EventController::class, 'destroy']); 
    Route::put('/events/{id}/pin', [EventController::class, 'pin']);
    Route::put('/events/{id}/unpin', [EventController::class, 'unpin']);

    // ── HR Attendance ─────────────────────────────────────────────────────────
    Route::get('/hr/interns/{id}/attendance', [AttendanceController::class, 'getInternAttendanceForHR']);
    Route::get('/hr/attendance/verification',    [HrController::class, 'getVerificationLogs']);
    Route::post('/hr/attendance/{id}/verify',    [AttendanceController::class, 'verifyLog']);

    // ── Intern Dashboard & Forms ──────────────────────────────────────────────
    Route::get('/intern/dashboard-stats',    [InternDashboardController::class, 'getStats']);
    Route::post('/intern/forms/submit',      [FormRequestController::class, 'store']);

    // ── HR Settings ───────────────────────────────────────────────────────────
    Route::get('/hr/settings/requirements',          [SettingsController::class, 'getRequirements']);
    Route::post('/hr/settings/requirements',         [SettingsController::class, 'storeRequirement']);
    Route::put('/hr/settings/requirements/{id}',     [SettingsController::class, 'updateRequirement']);
    Route::delete('/hr/settings/requirements/{id}',  [SettingsController::class, 'deleteRequirement']);
    Route::get('/hr/settings/schools',               [SettingsController::class, 'getSchools']);

    Route::get('/hr/settings/departments', function () {
        return response()->json(Department::orderBy('name', 'asc')->get());
    });
    Route::post('/hr/settings/departments', function (Request $request) {
        $request->validate(['name' => 'required|string|max:255', 'supervisor_name' => 'required|string|max:255']);
        return response()->json(Department::create($request->all()), 201);
    });
    Route::delete('/hr/settings/departments/{id}', function ($id) {
        Department::destroy($id);
        return response()->json(['message' => 'Deleted successfully']);
    });

    Route::get('/hr/settings/branches', function () {
        return response()->json(Branch::orderBy('name', 'asc')->get());
    });
    Route::post('/hr/settings/branches', function (Request $request) {
        $request->validate([
            'name'      => 'required|string|max:255',
            'latitude'  => 'required|numeric',
            'longitude' => 'required|numeric',
            'radius'    => 'nullable|integer',
        ]);
        return response()->json(Branch::create($request->all()), 201);
    });
    Route::delete('/hr/settings/branches/{id}', function ($id) {
        Branch::destroy($id);
        return response()->json(['message' => 'Branch deleted successfully']);
    });

    // ── Intern Profiles & Uploads ─────────────────────────────────────────────
    Route::get('/hr/interns/{id}',        [InternController::class, 'show']);
    Route::get('/hr/intern-data/{id}',    [InternController::class, 'showForHr']);
    Route::post('/intern/upload-avatar',  [InternController::class, 'uploadAvatar']);
    Route::post('/intern/upload-document',[InternController::class, 'uploadDocument']);

    // ── Bulk Actions ──────────────────────────────────────────────────────────
    Route::post('/hr/interns/bulk-archive',   [HrController::class, 'bulkArchive']);
    Route::post('/hr/interns/bulk-restore',   [HrController::class, 'bulkRestore']);
    Route::post('/hr/interns/bulk-export',    [HrController::class, 'bulkExport']);
    Route::post('/hr/interns/bulk-add-hours', [HrController::class, 'bulkAddHours']);
    Route::post('/hr/interns/sync-hours', [AttendanceController::class, 'syncAllInternHours']);
    Route::post('/hr/reports/export', [ReportController::class, 'export']);
    
    // ── Notification Routes (CONSOLIDATED) ────────────────────────────────────
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::put('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::put('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
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

// ==========================================
// ✨ Email Testing Route
// ==========================================
Route::get('/test-email', function () {
    try {
        Mail::raw('Success! Your Resend SMTP is working perfectly in production.', function ($message) {
            
            // 👇 This is explicitly set to the email you signed up to Resend with
            $message->to('khenjoshua.verson@1.ustp.edu.ph')
                    ->subject('InternTracker Email Test');
                    
        });
        
        return response()->json(['message' => 'Test email sent successfully! Check your inbox.']);
    } catch (\Exception $e) {
        return response()->json(['error' => 'Mail failed to send: ' . $e->getMessage()]);
    }
});