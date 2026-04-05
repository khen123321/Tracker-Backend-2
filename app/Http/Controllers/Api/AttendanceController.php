<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AttendanceLog;
use Carbon\Carbon;
use App\Models\Intern; 

class AttendanceController extends Controller
{
    // 1. Handle Timing In
    public function timeIn(Request $request)
    {
        $user = $request->user();
        $today = Carbon::today()->toDateString();

        // STEP 1: Find the Intern profile linked to this User
        $intern = Intern::where('user_id', $user->id)->first();

        // If this user isn't an intern (or their profile is missing), stop them.
        if (!$intern) {
            return response()->json(['message' => 'Error: No intern profile linked to this account.'], 403);
        }

        // STEP 2: Use $intern->id (NOT $user->id)
        $existingLog = AttendanceLog::where('intern_id', $intern->id)
                                    ->where('date', $today)
                                    ->first();

        if ($existingLog) {
            return response()->json(['message' => 'You have already timed in today!'], 400);
        }

        // STEP 3: Save using $intern->id
        AttendanceLog::create([
            'intern_id' => $intern->id,
            'date' => $today,
            'time_in' => Carbon::now(),
            'status' => 'present' 
        ]);

        return response()->json(['message' => 'Successfully Timed In!']);
    }

    // 2. Handle Timing Out
    public function timeOut(Request $request)
    {
        $user = $request->user();
        $today = Carbon::today()->toDateString();

        // Find the Intern
        $intern = Intern::where('user_id', $user->id)->first();

        if (!$intern) {
            return response()->json(['message' => 'Error: No intern profile linked to this account.'], 403);
        }

        // Search using $intern->id
        $log = AttendanceLog::where('intern_id', $intern->id)
                            ->where('date', $today)
                            ->whereNull('time_out')
                            ->first();

        if (!$log) {
            return response()->json(['message' => 'No active time-in record found for today.'], 404);
        }

        $log->update([
            'time_out' => Carbon::now()
        ]);

        return response()->json(['message' => 'Successfully Timed Out. Great work today!']);
    }

    // 3. Check current status
    public function checkStatus(Request $request)
    {
        $user = $request->user();
        $today = Carbon::today()->toDateString();

        // Find the Intern
        $intern = Intern::where('user_id', $user->id)->first();

        if (!$intern) {
            return response()->json(['isCheckedIn' => false]);
        }

        // Search using $intern->id
        $log = AttendanceLog::where('intern_id', $intern->id)
                            ->where('date', $today)
                            ->first();

        if (!$log) {
            return response()->json(['isCheckedIn' => false]);
        }

        $isActive = $log->time_in !== null && $log->time_out === null;

        return response()->json([
            'isCheckedIn' => $isActive,
            'timeInRecord' => Carbon::parse($log->time_in)->format('h:i A') 
        ]);
    }
    }
