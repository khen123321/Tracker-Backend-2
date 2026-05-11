<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Intern;
use App\Models\AttendanceLog;
use App\Models\ActivityLog; 
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
        $view = $request->query('view', 'active'); 

        // 1. Base Query
        $query = User::where('role', 'intern')
            ->with(['intern.school', 'intern.department', 'intern.branch']);

        if ($view === 'archived') {
            $query->onlyTrashed(); 
        }

        $interns = $query->get();
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

                $userLog->time_in_am = $formatTime($userLog->time_in);
                $userLog->time_out_am = $formatTime($userLog->lunch_out);
                $userLog->time_in_pm = $formatTime($userLog->lunch_in);
                $userLog->time_out_pm = $formatTime($userLog->time_out);
            }

            // Explicitly force these attributes so they don't get hidden
            $user->setAttribute('name', $user->first_name . ' ' . $user->last_name);
            $user->setAttribute('attendance_logs', $userLog ? [$userLog] : []);
            $user->setAttribute('attendance_logs_sum_hours_rendered', $internId ? ($allTimeHours->get($internId) ?? 0) : 0);

            return $user;
        });

        return response()->json($interns);
    }

    /* =========================================================
       === CAMERA VERIFICATION METHODS === 
       ========================================================= */

    public function getVerificationLogs(Request $request)
    {
        $targetDate = $request->date ? Carbon::parse($request->date)->toDateString() : Carbon::today()->toDateString();
        $filter = $request->filter ?? 'all';

        $query = AttendanceLog::with('intern')
            ->whereDate('date', $targetDate);

        $logs = $query->get()->map(function ($log) {
            $user = User::withTrashed()->where('id', $log->intern->user_id)->first(); 

            return [
                'id' => $log->id,
                'intern_name' => $user ? $user->first_name . ' ' . $user->last_name : 'Unknown Intern',
                'department' => $user->assigned_department ?? 'N/A',
                'is_flagged' => $log->is_flagged,
                'flag_reason' => $log->notes, 
                'status' => $log->status,
                'time_in' => $log->time_in,
                'lunch_out' => $log->lunch_out,
                'lunch_in' => $log->lunch_in,
                'time_out' => $log->time_out,
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

    public function verifyAttendanceAction(Request $request, $id)
    {
        try {
            $log = AttendanceLog::findOrFail($id);
            
            if ($request->action === 'reject') {
                $log->is_flagged = 1;
                $log->notes = $request->reason ?? 'Rejected manually by HR Admin.';
                
                if ($log->time_out) {
                    $log->time_out = null;
                    $log->image_out = null;
                    $log->hours_rendered = 0; 
                } elseif ($log->lunch_in) {
                    $log->lunch_in = null;
                    $log->lunch_in_selfie = null;
                } elseif ($log->lunch_out) {
                    $log->lunch_out = null;
                    $log->lunch_out_selfie = null;
                } elseif ($log->time_in) {
                    $log->time_in = null;
                    $log->image_in = null;
                }
                
                $log->save();

                ActivityLog::create([
                    'user_id' => Auth::id(),
                    'action' => 'Rejected Attendance',
                    'description' => "Rejected attendance log #{$id}."
                ]);
            }
            
            return response()->json(['message' => 'Attendance flagged and time removed successfully']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Backend Error', 'error' => $e->getMessage()], 500);
        }
    }

    /* =========================================================
       === ADMINISTRATIVE & BULK METHODS === 
       ========================================================= */

    public function getActivityLogs(Request $request)
    {
        if ($request->user()->role !== 'superadmin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $logs = ActivityLog::with('user:id,first_name,last_name,role')
            ->orderBy('created_at', 'desc')
            ->take(100) 
            ->get();

        return response()->json($logs);
    }

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
            'last_name'  => 'required|string|max:255', 
            'email'      => 'required|email|unique:users,email', 
            'password'   => 'required|min:6', 
            'role'       => 'required|in:hr_intern,hr,superadmin',
            'branch_id'  => 'nullable|exists:branches,id'
        ]);

        $user = User::create([
            'first_name'  => $validated['first_name'],
            'last_name'   => $validated['last_name'],
            'email'       => $validated['email'],
            'password'    => Hash::make($validated['password']),
            'role'        => $validated['role'],
            'status'      => 'active',
            'permissions' => [],
            'branch_id'   => $validated['branch_id'] ?? null,
            'created_by'  => Auth::id() 
        ]);

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Created Staff Account',
            'description' => "Created a new {$validated['role']} account for {$validated['email']}."
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

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Updated User Status/Role',
            'description' => "Changed role to {$request->role} and status to {$request->status} for {$user->email}."
        ]);

        return response()->json(['message' => 'User updated successfully!', 'user' => $user]);
    }

    public function getRoleUsers()
    {
        $users = User::with(['branch', 'creator'])
            ->whereIn('role', ['hr', 'hr_intern', 'superadmin'])
            ->get()
            ->map(function ($user) {
                return [
                    'id'                => $user->id,
                    'name'              => $user->first_name . ' ' . $user->last_name,
                    'first_name'        => $user->first_name,
                    'last_name'         => $user->last_name,
                    'email'             => $user->email,
                    'role'              => $user->role,
                    
                    'status'            => $user->status,
                    'email_verified_at' => $user->email_verified_at ? $user->email_verified_at->format('M j, Y, g:i a') : null,
                    
                    'permissions'       => $user->permissions ?? [],
                    'branch_name'       => $user->branch ? $user->branch->name : 'All Branches (HQ)',
                    'created_by'        => $user->creator ? ($user->creator->first_name . ' ' . $user->creator->last_name) : 'System Default',
                    'created_at'        => $user->created_at ? $user->created_at->format('F j, Y, g:i a') : 'N/A',
                ];
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

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Modified Access Limits',
            'description' => "Updated panel permissions for {$user->email}."
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
            ->select('id', 'first_name', 'last_name', 'email', 'status', 'email_verified_at', 'role')
            ->get();
            
        $interns->transform(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->first_name . ' ' . $user->last_name,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'status' => $user->status,
                'email_verified_at' => $user->email_verified_at ? $user->email_verified_at->format('M j, Y, g:i a') : null,
                'role' => $user->role,
                'attendance_logs_sum_hours_rendered' => $user->attendance_logs_sum_hours_rendered,
                'intern' => $user->intern,
                // Explicitly pull the avatar_url out to the top level for easier access in React
                'avatar_url' => $user->intern?->avatar_url
            ];
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

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => 'Updated Intern Assignment',
            'description' => "Updated branch/department assignments for intern {$intern->email}."
        ]);

        return response()->json([
            'message' => 'Intern assignment updated successfully!',
            'intern' => $intern
        ]);
    }

    public function bulkArchive(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|integer|exists:users,id'
        ]);

        DB::beginTransaction();
        try {
            $userIds = $request->ids;
            
            User::whereIn('id', $userIds)->delete();
            
            ActivityLog::create([
                'user_id' => Auth::id(),
                'action' => 'Bulk Archived Interns',
                'description' => "Archived " . count($userIds) . " intern account(s)."
            ]);

            DB::commit();
            return response()->json(['message' => count($userIds) . ' intern(s) archived successfully.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to archive interns.', 'error' => $e->getMessage()], 500);
        }
    }

    public function bulkRestore(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|integer' 
        ]);

        DB::beginTransaction();
        try {
            $userIds = $request->ids;
            
            User::withTrashed()->whereIn('id', $userIds)->restore();
            
            ActivityLog::create([
                'user_id' => Auth::id(),
                'action' => 'Bulk Restored Interns',
                'description' => "Restored " . count($userIds) . " intern account(s) to active status."
            ]);

            DB::commit();
            return response()->json(['message' => count($userIds) . ' intern(s) restored successfully.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to restore interns.', 'error' => $e->getMessage()], 500);
        }
    }

    // ✨ THE NEW BULK ADD HOURS FUNCTION (With Late & AM/PM Half Days) ✨
    public function bulkAddHours(Request $request)
    {
        $request->validate([
            'intern_ids' => 'required|array',
            'date'       => 'required|date',
            'event_type' => 'nullable|string',
            'input_type' => 'required|in:time,full_day,half_day_am,half_day_pm',
            'time_in'    => 'nullable|required_if:input_type,time',
            'time_out'   => 'nullable|required_if:input_type,time',
        ]);

        DB::beginTransaction();
        try {
            foreach ($request->intern_ids as $userId) {
                $intern = Intern::where('user_id', $userId)->first();
                
                if ($intern) {
                    
                    if ($request->input_type === 'full_day') {
                        $timeIn = Carbon::parse($request->date . ' 08:00:00');
                        $timeOut = Carbon::parse($request->date . ' 17:00:00');
                        $newHours = 8;
                    } 
                    elseif ($request->input_type === 'half_day_am') {
                        $timeIn = Carbon::parse($request->date . ' 08:00:00');
                        $timeOut = Carbon::parse($request->date . ' 12:00:00');
                        $newHours = 4;
                    } 
                    elseif ($request->input_type === 'half_day_pm') {
                        $timeIn = Carbon::parse($request->date . ' 13:00:00');
                        $timeOut = Carbon::parse($request->date . ' 17:00:00');
                        $newHours = 4;
                    } 
                    else {
                        // Exact Time Input
                        $timeIn = Carbon::parse($request->date . ' ' . $request->time_in);
                        $timeOut = Carbon::parse($request->date . ' ' . $request->time_out);
                        
                        $newHours = round($timeIn->diffInMinutes($timeOut) / 60, 2);
                        
                        $noon = Carbon::parse($request->date . ' 12:00:00');
                        $onePM = Carbon::parse($request->date . ' 13:00:00');
                        
                        // Auto deduct lunch break if it overlaps
                        if ($timeIn->lessThanOrEqualTo($noon) && $timeOut->greaterThanOrEqualTo($onePM)) {
                            $newHours -= 1;
                        }
                    }
                    
                    $noteAddition = trim(($request->reason ?? '') . ' ' . ($request->notes ?? ''));

                    // Check if HR specifically marked them as Late or Half Day
                    $logStatus = 'Present';
                    if (in_array($request->event_type, ['Late', 'Half Day'])) {
                        $logStatus = $request->event_type;
                    }

                    $existingLog = AttendanceLog::where('intern_id', $intern->id)
                                                ->where('date', $request->date)
                                                ->first();

                    if ($existingLog) {
                        $existingLog->hours_rendered += $newHours;
                        if ($noteAddition) {
                            $existingLog->notes = $existingLog->notes ? $existingLog->notes . ' | Add: ' . $noteAddition : 'Add: ' . $noteAddition;
                        }
                        
                        // Update status to Late/Half Day if it was just Present
                        if ($existingLog->status === 'Present' && $logStatus !== 'Present') {
                            $existingLog->status = $logStatus;
                        }
                        
                        $existingLog->save();
                    } else {
                        AttendanceLog::create([
                            'intern_id'      => $intern->id,
                            'date'           => $request->date,
                            'time_in_am'     => $timeIn->toDateTimeString(),
                            'time_out_am'    => $timeOut->toDateTimeString(),
                            'hours_rendered' => $newHours,
                            'status'         => $logStatus, 
                            'notes'          => $noteAddition ?: null
                        ]);
                    }
                }
            }
            
            ActivityLog::create([
                'user_id' => Auth::id(),
                'action' => 'Bulk Added Hours',
                'description' => "Manually added hours for " . count($request->intern_ids) . " intern(s) on {$request->date}."
            ]);

            DB::commit();
            return response()->json(['message' => 'Hours added successfully to selected interns.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to add hours.', 'error' => $e->getMessage()], 500);
        }
    }

    /* =========================================================
       === BULLETPROOF EXPORT FUNCTION (DYNAMIC) === 
       ========================================================= */

    public function bulkExport(Request $request)
    {
        // Accept the payload from React
        $validated = $request->validate([
            'ids' => 'required|array',
            'type' => 'required|string', 
            'format' => 'required|string', 
            'date_range' => 'nullable|string'
        ]);

        $exportType = $validated['type']; // 'info', 'log', 'progress', or 'full'
        $dateRange = $validated['date_range'] ?? 'All Time';

        // Eager load relationships
        $interns = \App\Models\User::with(['intern.school', 'intern.department', 'school', 'department'])
            ->whereIn('id', $validated['ids'])
            ->get();

        $filename = "Bulk_Intern_" . ucfirst($exportType) . "_Export_" . date('Y-m-d') . ".csv";

        $headers = [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$filename",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $callback = function() use ($interns, $exportType, $dateRange) {
            $file = fopen('php://output', 'w');

            // ✨ Helper to apply Date Range filter to attendance queries ✨
            $applyDateRange = function($query) use ($dateRange) {
                if ($dateRange === 'This Month') {
                    $query->whereMonth('date', Carbon::now()->month)
                          ->whereYear('date', Carbon::now()->year);
                } elseif ($dateRange === 'Last Month') {
                    $query->whereMonth('date', Carbon::now()->subMonth()->month)
                          ->whereYear('date', Carbon::now()->subMonth()->year);
                }
                return $query;
            };

            // ========================================================
            // 1. EXPORT TYPE: INTERN INFO
            // ========================================================
            if ($exportType === 'info') {
                fputcsv($file, ['ID', 'Name', 'Email', 'School', 'Department', 'Status']);

                foreach ($interns as $intern) {
                    $schoolName = $this->getAggressiveSchoolName($intern);
                    $deptName = $this->getAggressiveDeptName($intern);

                    fputcsv($file, [
                        $intern->id,
                        trim($intern->first_name . ' ' . $intern->last_name),
                        $intern->email,
                        $schoolName,
                        $deptName,
                        $intern->status ?? 'Unknown'
                    ]);
                }
            } 
            // ========================================================
            // 2. EXPORT TYPE: ATTENDANCE LOG
            // ========================================================
            elseif ($exportType === 'log') {
                fputcsv($file, ['Name', 'Date', 'Time In', 'Time Out', 'Hours Rendered', 'Status']);

                foreach ($interns as $intern) {
                    $internIdForLogs = $intern->intern->id ?? $intern->id;
                    $fullName = trim($intern->first_name . ' ' . $intern->last_name);
                    
                    // Fetch logs and apply date filter
                    $logsQuery = \App\Models\AttendanceLog::where('intern_id', $internIdForLogs)->orderBy('date', 'desc');
                    $logsQuery = $applyDateRange($logsQuery);
                    $logs = $logsQuery->get();

                    if ($logs->isEmpty()) {
                        fputcsv($file, [$fullName, 'No records found for ' . $dateRange, '-', '-', '0', '-']);
                    } else {
                        foreach ($logs as $log) {
                            fputcsv($file, [
                                $fullName,
                                $log->date,
                                $log->time_in,
                                $log->time_out,
                                $log->hours_rendered,
                                $log->status ?? 'Present'
                            ]);
                        }
                    }
                }
            } 
            // ========================================================
            // 3. EXPORT TYPE: HOURS PROGRESS
            // ========================================================
            elseif ($exportType === 'progress') {
                fputcsv($file, ['Name', 'School', 'Required Hours', 'Rendered Hours (' . $dateRange . ')', 'Remaining Hours', 'Progress %']);

                foreach ($interns as $intern) {
                    $schoolName = $this->getAggressiveSchoolName($intern);
                    $internIdForLogs = $intern->intern->id ?? $intern->id;
                    
                    $req = $intern->intern->required_hours ?? 486;
                    
                    // Sum hours with date filter applied
                    $logsQuery = \App\Models\AttendanceLog::where('intern_id', $internIdForLogs);
                    $logsQuery = $applyDateRange($logsQuery);
                    $ren = $logsQuery->sum('hours_rendered');
                    
                    $rem = max($req - $ren, 0);
                    $prog = $req > 0 ? round(($ren / $req) * 100, 1) : 0;
                    if ($prog > 100) $prog = 100;

                    fputcsv($file, [
                        trim($intern->first_name . ' ' . $intern->last_name),
                        $schoolName,
                        $req,
                        $ren,
                        $rem,
                        $prog . '%'
                    ]);
                }
            } 
            // ========================================================
            // 4. EXPORT TYPE: FULL REPORT
            // ========================================================
            elseif ($exportType === 'full') {
                fputcsv($file, ['ID', 'Name', 'Email', 'School', 'Department', 'Required Hrs', 'Rendered Hrs (' . $dateRange . ')', 'Remaining', 'Progress %', 'Status']);

                foreach ($interns as $intern) {
                    $schoolName = $this->getAggressiveSchoolName($intern);
                    $deptName = $this->getAggressiveDeptName($intern);
                    
                    $internIdForLogs = $intern->intern->id ?? $intern->id;
                    $req = $intern->intern->required_hours ?? 486;
                    
                    // Sum hours with date filter applied
                    $logsQuery = \App\Models\AttendanceLog::where('intern_id', $internIdForLogs);
                    $logsQuery = $applyDateRange($logsQuery);
                    $ren = $logsQuery->sum('hours_rendered');
                    
                    $rem = max($req - $ren, 0);
                    $prog = $req > 0 ? round(($ren / $req) * 100, 1) : 0;
                    if ($prog > 100) $prog = 100;

                    fputcsv($file, [
                        $intern->id,
                        trim($intern->first_name . ' ' . $intern->last_name),
                        $intern->email,
                        $schoolName,
                        $deptName,
                        $req,
                        $ren,
                        $rem,
                        $prog . '%',
                        $intern->status ?? 'Unknown'
                    ]);
                }
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    // ✨ HELPER FUNCTIONS FOR BULK EXPORT ✨
    private function getAggressiveSchoolName($intern) {
        $schoolName = $intern->intern?->school?->name ?? $intern->school?->name ?? null;
        if (!$schoolName) {
            $schoolId = $intern->intern->school_id ?? $intern->school_id ?? null;
            if ($schoolId) {
                $schoolName = \Illuminate\Support\Facades\DB::table('schools')->where('id', $schoolId)->value('name');
            }
        }
        return $schoolName ?? 'Unassigned';
    }

    private function getAggressiveDeptName($intern) {
        $deptName = $intern->intern?->department?->name ?? $intern->department?->name ?? null;
        if (!$deptName) {
            $deptId = $intern->intern->department_id ?? $intern->department_id ?? null;
            if ($deptId) {
                $deptName = \Illuminate\Support\Facades\DB::table('departments')->where('id', $deptId)->value('name');
            }
        }
        return $deptName ?? $intern->assigned_department ?? 'Unassigned';
    }
}