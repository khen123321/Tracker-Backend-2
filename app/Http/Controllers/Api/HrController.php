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
     * Get ONLY Administrative Users (HR & HR Interns)
     * This hides regular interns from the Role Management table.
     */
    public function getAllUsers()
    {
        // We filter the query to only include administrative roles
        $users = User::whereIn('role', ['hr', 'hr_intern'])
            ->orderBy('role', 'asc')
            ->get();

        return response()->json($users);
    }

    /**
     * Create a new HR Intern account
     */
    public function storeSubUser(Request $request)
    {
        // Only the Super Admin email can create admin accounts
        if (Auth::user()->email !== 'admin@climbs.com.ph') {
            return response()->json(['message' => 'Unauthorized. Only Main HR can create accounts.'], 403);
        }

        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name'  => 'required|string|max:255',
            'email'      => 'required|email|unique:users,email',
            'password'   => 'required|min:6',
            'role'       => 'required|in:hr_intern', 
        ]);

        $user = User::create([
            'first_name' => $validated['first_name'],
            'last_name'  => $validated['last_name'],
            'email'      => $validated['email'],
            'password'   => Hash::make($validated['password']),
            'role'       => $validated['role'],
            'status'     => 'active',
            'permissions' => [] // New accounts start with no access
        ]);

        return response()->json(['message' => 'Account created successfully!'], 201);
    }

    /**
     * Update permissions for a specific sub-admin
     */
    public function updatePermissions(Request $request, $id)
    {
        if (Auth::user()->email !== 'admin@climbs.com.ph') {
            return response()->json(['message' => 'Access Denied.'], 403);
        }

        $request->validate([
            'permissions' => 'required|array'
        ]);

        $user = User::findOrFail($id);
        
        // Safety: Super Admin cannot be edited
        if ($user->email === 'admin@climbs.com.ph') {
            return response()->json(['message' => 'Cannot modify Super Admin.'], 400);
        }

        $user->permissions = $request->permissions;
        $user->save();

        return response()->json(['message' => 'Permissions updated!']);
    }
}