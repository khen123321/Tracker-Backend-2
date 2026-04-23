<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Intern;
use App\Models\AttendanceLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class HrController extends Controller
{
    /**
     * DASHBOARD: Get list of interns with unique, synced attendance logs
     */
    public function getInternList(Request $request)
    {
        $targetDate = $request->date ? Carbon::parse($request->date)->toDateString() : Carbon::today()->toDateString();

        // 1. Get all interns
        $interns = User::where('role', 'intern')
            ->with(['intern.school', 'intern.department', 'intern.branch'])
            ->get();

        $internIds = $interns->pluck('intern.id')->filter()->toArray();

        // 2. Get today's logs and map them by Intern ID to prevent data duplication
        $todayLogs = AttendanceLog::whereIn('intern_id', $internIds)
            ->whereDate('date', $targetDate)
            ->get()
            ->keyBy('intern_id');

        // 3. Get total rendered hours for the progress bar
        $allTimeHours = AttendanceLog::whereIn('intern_id', $internIds)
            ->selectRaw('intern_id, SUM(hours_rendered) as total_hours')
            ->groupBy('intern_id')
            ->pluck('total_hours', 'intern_id');

        // 4. Attach formatted data to each user
        $interns->transform(function ($user) use ($todayLogs, $allTimeHours) {
            $internId = $user->intern ? $user->intern->id : null;
            $userLog = null;

            if ($internId && $todayLogs->has($internId)) {
                $userLog = clone $todayLogs->get($internId); 

                $formatTime = function($time) {
                    try {
                        return $time ? Carbon::parse($time)->format('h:i A') : '-----';
                    } catch (\Exception $e) {
                        return $time;
                    }
                };

                // Map DB columns to keys expected by the React Main Table
                $userLog->time_in_am = $formatTime($userLog->time_in);
                $userLog->time_out_am = $formatTime($userLog->lunch_out);
                $userLog->time_in_pm = $formatTime($userLog->lunch_in);
                $userLog->time_out_pm = $formatTime($userLog->time_out);
            }

            $user->setAttribute('attendance_logs', $userLog ? [$userLog] : []);
            $user->setAttribute('attendance_logs_sum_hours_rendered', $internId ? ($allTimeHours->get($internId) ?? 0) : 0);

            return $user;
        });

        return response()->json($interns);
    }

    /* =========================================================
       === CAMERA VERIFICATION METHODS === 
       ========================================================= */

    /**
     * Get logs specifically formatted for the Camera Verification Grid
     */
    public function getVerificationLogs(Request $request)
    {
        $targetDate = $request->date ? Carbon::parse($request->date)->toDateString() : Carbon::today()->toDateString();
        $filter = $request->filter ?? 'all';

        $query = AttendanceLog::with('intern')
            ->whereDate('date', $targetDate);

        $logs = $query->get()->map(function ($log) {
            $user = User::where('id', $log->intern->user_id)->first();

            return [
                'id' => $log->id,
                'intern_name' => $user ? $user->first_name . ' ' . $user->last_name : 'Unknown Intern',
                'department' => $user->assigned_department ?? 'N/A',
                'is_flagged' => $log->is_flagged,
                
                // ✨ PULL CUSTOM HR REASON FROM THE DATABASE NOTES COLUMN ✨
                'flag_reason' => $log->notes, 
                
                'status' => $log->status,
                
                // RAW TIMES
                'time_in' => $log->time_in,
                'lunch_out' => $log->lunch_out,
                'lunch_in' => $log->lunch_in,
                'time_out' => $log->time_out,

                // 📸 IMAGE MAPPING: These match your database column names exactly
                'image_in' => $log->image_in ?? $log->time_in_selfie,
                'lunch_out_selfie' => $log->lunch_out_selfie,
                'lunch_in_selfie' => $log->lunch_in_selfie,
                'image_out' => $log->image_out ?? $log->time_out_selfie,
            ];
        });

        if ($filter === 'flagged') {
            $logs = $logs->where('is_flagged', 1)->values();
        }

        return response()->json($logs);
    }

    /**
     * Verify or Reject a specific attendance log
     */
    public function verifyAttendanceAction(Request $request, $id)
    {
        try {
            $log = AttendanceLog::findOrFail($id);
            
            if ($request->action === 'reject') {
                $log->is_flagged = 1;
                
                // Save the reason so the intern gets the notification
                $log->notes = $request->reason ?? 'Rejected manually by HR Admin.';
                
                // ✨ STRICT RULE: "Time will not be recorded"
                if ($log->time_out) {
                    $log->time_out = null;
                    $log->image_out = null;
                    $log->hours_rendered = 0; // Wipe the calculated hours
                } elseif ($log->lunch_in) {
                    $log->lunch_in = null;
                    $log->lunch_in_selfie = null;
                } elseif ($log->lunch_out) {
                    $log->lunch_out = null;
                    $log->lunch_out_selfie = null;
                } elseif ($log->time_in) {
                    $log->time_in = null;
                    $log->image_in = null;
                    // 🛑 We removed the $log->status = 'pending'; line here to prevent the MySQL crash!
                }
                
                $log->save();
            }
            
            return response()->json(['message' => 'Attendance flagged and time removed successfully']);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Backend Error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /* =========================================================
       === ADMINISTRATIVE & BULK METHODS === 
       ========================================================= */

    public function getAllUsers()
    {
        return response()->json(User::whereIn('role', ['hr', 'hr_intern', 'superadmin'])->orderBy('role', 'asc')->get());
    }

    public function storeSubUser(Request $request)
    {
        if (Auth::user()->role !== 'superadmin') {
            return response()->json(['message' => 'Unauthorized. Only Superadmins can create accounts.'], 403);
        }
        
        $validated = $request->validate([
            'first_name' => 'required|string|max:255', 
            'last_name' => 'required|string|max:255', 
            'email' => 'required|email|unique:users,email', 
            'password' => 'required|min:6', 
            'role' => 'required|in:hr_intern,hr,superadmin'
        ]);

        $user = User::create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'status' => 'active',
            'permissions' => [] 
        ]);

        return response()->json(['message' => 'Account created successfully!'], 201);
    }

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
        
        if ($user->email === 'testadmin123@gmail.com' && $request->role !== 'superadmin') {
            return response()->json(['message' => 'Cannot demote the primary master account.'], 400);
        }

        $user->role = $request->role;
        $user->status = $request->status;
        $user->save();

        return response()->json(['message' => 'User updated successfully!', 'user' => $user]);
    }

    public function getRoleUsers()
    {
        $users = User::whereIn('role', ['hr', 'hr_intern', 'superadmin'])
            ->select('id', 'first_name', 'last_name', 'email', 'role', 'permissions', 'assigned_department')
            ->get();
            
        $users->transform(function ($user) {
            $user->name = $user->first_name . ' ' . $user->last_name;
            $user->department = $user->assigned_department ?? 'HR Department';
            
            if (!$user->permissions) {
                $user->permissions = [];
            }
            return $user;
        });

        return response()->json($users);
    }

    public function updateUserAccess(Request $request, $id)
    {
        $user = User::findOrFail($id);

        if ($user->role === 'superadmin' && $request->role !== 'superadmin') {
            return response()->json(['message' => 'Cannot modify the main Superadmin account.'], 403);
        }

        $user->update([
            'role' => $request->role ?? $user->role,
            'permissions' => $request->permissions ?? [],
        ]);

        return response()->json([
            'message' => 'Access updated successfully!',
            'user' => $user
        ]);
    }

    public function getInternsForManagement()
    {
        $interns = User::where('role', 'intern')
            ->with('intern') 
            ->withSum('attendance_logs', 'hours_rendered') 
            ->select('id', 'first_name', 'last_name', 'email', 'status')
            ->get();
            
        $interns->transform(function ($user) {
            $user->name = $user->first_name . ' ' . $user->last_name;
            return $user;
        });

        return response()->json($interns);
    }

    public function updateInternAssignment(Request $request, $id)
    {
        $intern = User::findOrFail($id);

        if ($intern->role !== 'intern') {
            return response()->json(['message' => 'You can only assign branches to Interns.'], 403);
        }

        $intern->update([
            'assigned_branch' => $request->assigned_branch,
            'assigned_department' => $request->assigned_department,
            'status' => $request->status,
            'has_moa' => $request->has_moa,
            'has_endorsement' => $request->has_endorsement,
            'has_pledge' => $request->has_pledge,
            'has_nda' => $request->has_nda,
        ]);

        return response()->json([
            'message' => 'Intern assignment updated successfully!',
            'intern' => $intern
        ]);
    }

    public function bulkRemove(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|integer|exists:users,id'
        ]);

        DB::beginTransaction();
        try {
            $userIds = $request->ids;
            Intern::whereIn('user_id', $userIds)->delete();
            User::whereIn('id', $userIds)->delete();
            DB::commit();
            return response()->json(['message' => count($userIds) . ' interns removed successfully.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to remove interns.', 'error' => $e->getMessage()], 500);
        }
    }

    public function bulkAddHours(Request $request)
    {
        $request->validate([
            'intern_ids' => 'required|array',
            'date' => 'required|date',
            'time_in' => 'required',
            'time_out' => 'required',
        ]);

        DB::beginTransaction();
        try {
            foreach ($request->intern_ids as $userId) {
                $intern = Intern::where('user_id', $userId)->first();
                
                if ($intern) {
                    $timeIn = Carbon::parse($request->date . ' ' . $request->time_in);
                    $timeOut = Carbon::parse($request->date . ' ' . $request->time_out);
                    
                    $newHours = round($timeIn->diffInMinutes($timeOut) / 60, 2);
                    $noteAddition = trim(($request->reason ?? '') . ' ' . ($request->notes ?? ''));

                    $existingLog = AttendanceLog::where('intern_id', $intern->id)
                                                ->where('date', $request->date)
                                                ->first();

                    if ($existingLog) {
                        $existingLog->hours_rendered += $newHours;
                        if ($noteAddition) {
                            $existingLog->notes = $existingLog->notes ? $existingLog->notes . ' | Add: ' . $noteAddition : 'Add: ' . $noteAddition;
                        }
                        $existingLog->save();
                    } else {
                        AttendanceLog::create([
                            'intern_id' => $intern->id,
                            'date' => $request->date,
                            'time_in_am' => $timeIn->toDateTimeString(),
                            'time_out_am' => $timeOut->toDateTimeString(),
                            'hours_rendered' => $newHours,
                            'status' => 'Present',
                            'notes' => $noteAddition ?: null
                        ]);
                    }
                }
            }
            
            DB::commit();
            return response()->json(['message' => 'Hours added successfully to selected interns.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to add hours.', 'error' => $e->getMessage()], 500);
        }
    }

    public function bulkExport(Request $request)
    {
        $request->validate([
            'ids' => 'required|array'
        ]);

        $users = User::with('intern')->whereIn('id', $request->ids)->get();
        $fileName = 'Interns_Export_' . date('Y-m-d') . '.csv';
        
        $headers = array(
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        );

        $columns = ['ID', 'First Name', 'Last Name', 'Email', 'Role', 'Status'];

        $callback = function() use($users, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);
            foreach ($users as $user) {
                fputcsv($file, [
                    $user->id,
                    $user->first_name,
                    $user->last_name,
                    $user->email,
                    $user->role,
                    $user->status
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}