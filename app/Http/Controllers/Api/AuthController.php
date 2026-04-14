<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Intern; // 👈 Added Intern Model
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Exception;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'role' => 'required|string' 
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid email or password.'], 401);
        }

        // --- ROLE VALIDATION ---
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

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'user' => $user,
            'role' => $user->role
        ]);
    }

    public function register(Request $request)
    {
        // Added validation for the new fields as optional so it doesn't break old frontend requests
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'course_program' => 'nullable|string', 
            'school_university' => 'nullable|string',
            'course' => 'nullable|string', // ✅ NEW
            'school' => 'nullable|string', // ✅ NEW
        ]);

        // 1. Create the base User account
        $user = User::create([
            'first_name' => $request->first_name,
            'middle_name' => $request->middle_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'intern',
            'status' => 'active',
            
            // ✨ THE MAGIC TRICK: Save to both the old and new columns.
            // If the frontend sends 'course', it fills both 'course' and 'course_program'.
            'course_program' => $request->course_program ?? $request->course,
            'school_university' => $request->school_university ?? $request->school,
            'course' => $request->course ?? $request->course_program, // ✅ For the Events Calendar
            'school' => $request->school ?? $request->school_university, // ✅ For the Events Calendar
            
            'assigned_branch' => $request->assigned_branch,
            'assigned_department' => $request->assigned_department,
            'date_started' => $request->date_started,
        ]);

        // 2. ✨ Create the Intern Profile for the Graph & Attendance ✨
        // We map the course_program/course from the form directly into the Intern model
       // 2. ✨ Create the Intern Profile for the Graph & Attendance ✨
        Intern::create([
            'user_id' => $user->id,
            'course' => $request->course_program ?? $request->course, 
            
            // Replace the '1's with the actual data from the form request!
            'school_id' => $request->school_id,
            'branch_id' => $request->branch_id,
            'department_id' => $request->department_id,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'User registered successfully',
            'access_token' => $token,
            'user' => $user
        ], 201);
    }

    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    public function logout(Request $request)
    {
        if ($request->user() && $request->user()->currentAccessToken()) {
            $request->user()->tokens()->where('id', $request->user()->currentAccessToken()->id)->delete();
        }
        return response()->json(['message' => 'Logged out successfully']);
    }
}