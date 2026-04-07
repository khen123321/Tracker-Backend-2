<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class HrController extends Controller
{
    /**
     * Get list of interns for the main dashboard (Attendance focus)
     */
    public function getInternList(Request $request)
    {
        $today = Carbon::today()->toDateString();

        $interns = User::where('role', 'intern')
            ->with(['attendance_logs' => function($query) use ($today) {
                $query->whereDate('date', $today);
            }])
            ->get();

        return response()->json($interns);
    }

    /**
     * Get Administrative Users (Superadmin, HR & HR Interns)
     */
    public function getAllUsers()
    {
        $users = User::whereIn('role', ['hr', 'hr_intern', 'superadmin'])
            ->orderBy('role', 'asc')
            ->get();

        return response()->json($users);
    }

    /**
     * Create a new HR account
     */
    public function storeSubUser(Request $request)
    {
        // Check for superadmin role instead of a specific email
        if (Auth::user()->role !== 'superadmin') {
            return response()->json(['message' => 'Unauthorized. Only Superadmins can create accounts.'], 403);
        }

        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name'  => 'required|string|max:255',
            'email'      => 'required|email|unique:users,email',
            'password'   => 'required|min:6',
            'role'       => 'required|in:hr_intern,hr,superadmin', 
        ]);

        $user = User::create([
            'first_name' => $validated['first_name'],
            'last_name'  => $validated['last_name'],
            'email'      => $validated['email'],
            'password'   => Hash::make($validated['password']),
            'role'       => $validated['role'],
            'status'     => 'active',
            'permissions' => [] 
        ]);

        return response()->json(['message' => 'Account created successfully!'], 201);
    }

    /**
     * Update roles and status for a specific user
     */
    public function updatePermissions(Request $request, $id)
    {
        if (Auth::user()->role !== 'superadmin') {
            return response()->json(['message' => 'Access Denied. Superadmin required.'], 403);
        }

        $request->validate([
            'role' => 'required|string|in:hr_intern,hr,superadmin',
            'status' => 'required|string|in:active,inactive'
        ]);

        $user = User::findOrFail($id);
        
        // Safety: Prevent the master superadmin from being locked out or demoted
        if ($user->email === 'testadmin123@gmail.com' && $request->role !== 'superadmin') {
            return response()->json(['message' => 'Cannot demote the primary master account.'], 400);
        }

        $user->role = $request->role;
        $user->status = $request->status;
        $user->save();

        return response()->json(['message' => 'User updated successfully!', 'user' => $user]);
    }
}