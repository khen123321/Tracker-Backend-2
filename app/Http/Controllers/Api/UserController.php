<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Http\Requests\StoreUserRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Illuminate\Auth\Events\Registered; // ✨ 1. ADDED THIS IMPORT!

class UserController extends Controller
{
    /**
     * Store a newly created user in storage.
     */
    public function store(StoreUserRequest $request)
    {
        try {
            // 1. Get the perfectly validated data from your Request file
            $validated = $request->validated();

            // 2. Hash the password so it is secure in the database
            $validated['password'] = Hash::make($validated['password']);

            // 3. Create the user
            $user = User::create($validated);

            // ✨ 4. THE MAGIC LINE: This tells Laravel to send the Mailtrap email!
            event(new Registered($user)); 

            // 5. Send success back to your React frontend!
            return response()->json([
                'message' => 'User created successfully',
                'user' => $user
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Overwrites a forgotten password for a user (Superadmin Feature)
     */
    public function forceResetPassword(Request $request, $id)
    {
        try {
            // 1. Validate the incoming new password
            $request->validate([
                'password' => 'required|string|min:6',
            ]);

            // 2. Find the user by ID
            $user = User::findOrFail($id);
            
            // 3. Update the user with the new hashed password
            $user->update([
                'password' => Hash::make($request->password) 
            ]);

            // 4. Return success to React
            return response()->json([
                'message' => 'Password reset successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to reset password',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}