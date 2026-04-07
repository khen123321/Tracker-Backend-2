<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    // --- 1. LOGIN ---
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'role' => 'required|string' // 'hr' or 'intern' from your frontend toggle
        ]);

        // Find the user by email first
        $user = User::where('email', $request->email)->first();

        // Check if user exists and password is correct
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid email or password.'], 401);
        }

        // --- ROLE VALIDATION LOGIC ---
        // If they selected "HR" on the frontend, they must be 'hr' OR 'hr_intern'
        if ($request->role === 'hr') {
            if ($user->role !== 'hr' && $user->role !== 'hr_intern') {
                return response()->json(['message' => 'Access denied. This is not an HR account.'], 403);
            }
        } 
        // If they selected "Intern", they must be an 'intern'
        else if ($request->role === 'intern') {
            if ($user->role !== 'intern') {
                return response()->json(['message' => 'Access denied. This is not an Intern account.'], 403);
            }
        }

        // Generate Token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'user' => $user,
            'role' => $user->role
        ]);
    }

    // --- 2. REGISTER (For Interns) ---
    public function register(Request $request)
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'first_name' => $request->first_name,
            'middle_name' => $request->middle_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'intern',
            'status' => 'active',
            // School Details
            'course_program' => $request->course_program,
            'school_university' => $request->school_university,
            'assigned_branch' => $request->assigned_branch,
            'assigned_department' => $request->assigned_department,
            'date_started' => $request->date_started,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'User registered successfully',
            'access_token' => $token,
            'user' => $user
        ], 201);
    }

    // --- 3. GET CURRENT USER ---
    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    // --- 4. LOGOUT ---
    public function logout(Request $request)
    {
        if ($request->user() && $request->user()->currentAccessToken()) {
        $request->user()->tokens()->where('id', $request->user()->currentAccessToken()->id)->delete();
    }

        return response()->json([
        'message' => 'Logged out successfully']);
    }
}