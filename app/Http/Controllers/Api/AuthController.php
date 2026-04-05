<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    // --- 1. LOGIN ---
    public function login(Request $request)
{
   $request->validate([
        'email' => 'required|email',
        'password' => 'required',
        'role' => 'required|string' 
    ]);

    // 2. The Magic Lock: Attempt to find a user that matches ALL THREE fields
    if (!Auth::attempt([
        'email' => $request->email,
        'password' => $request->password,
        'role' => $request->role // <-- This strictly prevents cross-logging
    ])) {
        // If the email/password is wrong, OR if they selected the wrong toggle
        return response()->json([
            'message' => 'Invalid credentials or incorrect role selected.'
        ], 401);
    }

    // 3. If it passes, generate the token
    $user = Auth::user();
    $token = $user->createToken('auth_token')->plainTextToken;

    return response()->json([
        'access_token' => $token,
        'user' => $user,
        'role' => $user->role
    ]);

    // Attempt to find the user
    if (!Auth::attempt($request->only('email', 'password'))) {
        return response()->json(['message' => 'Invalid login credentials.'], 401);
    }

    $user = Auth::user();
    $token = $user->createToken('auth_token')->plainTextToken;

    return response()->json([
        'access_token' => $token,
        'user' => $user,
        'role' => $user->role // Make sure this is sent back!
    ]);
    }

    // --- 2. REGISTER ---
public function register(Request $request)
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed', // 'confirmed' looks for password_confirmation
        ]);

        $user = User::create([
            // Personal Info
            'first_name' => $request->first_name,
            'middle_name' => $request->middle_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'intern',
            'status' => 'active',

            // Emergency Contact
            'emergency_contact_name' => $request->emergency_contact_name,
            'emergency_contact_phone' => $request->emergency_contact_phone,
            'emergency_contact_address' => $request->emergency_contact_address,
            'emergency_relationship' => $request->emergency_relationship,

            // School Details
            'course_program' => $request->course_program,
            'school_university' => $request->school_university,
            'assigned_branch' => $request->assigned_branch,
            'assigned_department' => $request->assigned_department,
            'date_started' => $request->date_started,

            // Documents
            'has_moa' => $request->has_moa ? 1 : 0,
            'has_endorsement' => $request->has_endorsement ? 1 : 0,
            'has_pledge' => $request->has_pledge ? 1 : 0,
            'has_nda' => $request->has_nda ? 1 : 0,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'User registered successfully',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user
        ], 201);
    }

    // --- 3. GET CURRENT USER (ME) ---
    public function me(Request $request)
    {
        // Returns the currently authenticated user's details
        return response()->json($request->user());
    }

// --- 4. LOGOUT ---
    public function logout(Request $request)
    {
        // Check if user has a current token before attempting to delete
        if ($request->user()->currentAccessToken()) {
            /** @var \Laravel\Sanctum\PersonalAccessToken $token */
            $token = $request->user()->currentAccessToken();
            $token->delete();
        }

        return response()->json([
            'message' => 'Logged out successfully'
        ]);
    }
}