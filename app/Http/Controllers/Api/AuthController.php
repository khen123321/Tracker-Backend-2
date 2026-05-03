<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Intern;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB; 

class AuthController extends Controller
{
    public function login(Request $request)
    {
        // Combined validation rules
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'role' => 'required|string' 
        ]);

        $user = User::where('email', $request->email)->first();

        // 1. Check if user exists and password is correct
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid email or password.'], 401);
        }

        // 2. ✨ THE BOUNCER (With VIP Pass)
        // We only block the user if they are NOT a superadmin AND have no verification stamp.
        if ($user->role !== 'superadmin' && !$user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Your email address is not verified.',
                'not_verified' => true 
            ], 403); // 403 means "Forbidden"
        }

        // 3. Check if account is active
        if ($user->status !== 'active') {
            return response()->json(['message' => 'Your account is currently inactive.'], 403);
        }

        // 4. --- ROLE VALIDATION ---
        if ($request->role === 'hr') {
            if ($user->role !== 'hr' && $user->role !== 'hr_intern' && $user->role !== 'superadmin') {
                return response()->json(['message' => 'Access denied. Privileged account required.'], 403);
            }
        } 
        else if ($request->role === 'intern') {
            if ($user->role !== 'intern') {
                return response()->json(['message' => 'Access denied. This is not an Intern account.'], 403);
            }
        }

        // If everything is fine, issue the token
        $token = $user->createToken('auth_token')->plainTextToken;

        // 🛡️ SAFETY NET: Ensure permissions is always an array
        $user->permissions = $user->permissions ?? [];

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
            'role' => $user->role
        ]);
    }

    public function register(Request $request)
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        DB::beginTransaction();

        try {
            // 1. Create the base User account
            $user = User::create([
                'first_name' => $request->first_name,
                'middle_name' => $request->middle_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 'intern',
                // ✨ FIX: Set to inactive until they verify!
                'status' => 'inactive', 
                'permissions' => [], 
            ]);

            // Using `$request->filled()` ensures we catch empty strings ("") from React!
            $actualSchoolId = $request->filled('school_id') ? $request->school_id : ($request->school ?: 1);
            $actualDeptId = $request->filled('department_id') ? $request->department_id : ($request->assigned_department ?: 1);
            $actualBranchId = $request->filled('branch_id') ? $request->branch_id : ($request->assigned_branch ?: 1);
            $actualCourse = $request->filled('course_program') ? $request->course_program : ($request->course ?: 'N/A');

            // Fetch required hours based on School and Course
            $setting = \App\Models\RequirementSetting::where('school_id', $actualSchoolId)
                        ->where('course_name', $actualCourse)
                        ->first();

            $hoursNeeded = $setting ? $setting->required_hours : 0; 

            // 2. Create the Intern Profile
            Intern::create([
                'user_id' => $user->id,
                
                'course' => $actualCourse, 
                'school' => $request->school_university ?? $request->school ?? 'N/A',
                
                'school_id' => $actualSchoolId,
                'branch_id' => $actualBranchId,
                'department_id' => $actualDeptId,
                
                'required_hours' => $hoursNeeded,
                'rendered_hours' => 0, 

                'date_started' => $request->date_started ?? now(),

                'emergency_name' => $request->emergency_name,
                'emergency_number' => $request->emergency_number,
                'emergency_address' => $request->emergency_address,
            ]);

            DB::commit();

            // ✨ THE FIX: Force the email to send directly bypassing invisible events! ✨
            $user->sendEmailVerificationNotification();

            // ✨ We return requires_verification: true so React shows the correct screen ✨
            return response()->json([
                'message' => 'Registration successful! Please check your email to verify your account.',
                'requires_verification' => true
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack(); 
            return response()->json([
                'message' => 'Registration failed. Check your database columns.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function me(Request $request)
    {
        $user = clone $request->user();
        
        // 🛡️ THE GEOFENCING FIX: Eager load the branch data so the Intern Attendance page knows where the intern should be!
        if ($user->role === 'intern') {
            $user->load(['intern.branch', 'intern.department', 'intern.school']);
        }
        
        $user->permissions = $user->permissions ?? [];
        
        return response()->json($user);
    }

    public function logout(Request $request)
    {
        if ($request->user() && $request->user()->currentAccessToken()) {
            $request->user()->tokens()->where('id', $request->user()->currentAccessToken()->id)->delete();
        }
        return response()->json(['message' => 'Logged out successfully']);
    }
}