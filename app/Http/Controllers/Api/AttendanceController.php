<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AttendanceLog;
use App\Models\Intern;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    // =======================================================================
    // ─── PART 1: CORE ATTENDANCE & HISTORY ─────────────────────────────────
    // =======================================================================

    public function getHistory(Request $request)
    {
        try {
            $user   = $request->user();
            $intern = Intern::where('user_id', $user->id)->first();

            if (!$intern) {
                return response()->json(['message' => 'Intern profile not found.'], 404);
            }

            $logs = AttendanceLog::where('intern_id', $intern->id)
                ->orderBy('date', 'desc')
                ->get()
                ->map(function ($log) {
                    return [
                        'id'             => $log->id,

                        'raw_date'       => $log->date ? Carbon::parse($log->date)->format('Y-m-d') : null,
                        'formatted_date' => $log->date ? Carbon::parse($log->date)->format('F j, Y') : 'N/A',

                        'time_in_am'  => $log->time_in   ? Carbon::parse($log->time_in)->format('g:i A')   : '-',
                        'time_out_am' => $log->lunch_out  ? Carbon::parse($log->lunch_out)->format('g:i A') : '-',
                        'time_in_pm'  => $log->lunch_in   ? Carbon::parse($log->lunch_in)->format('g:i A')  : '-',
                        'time_out_pm' => $log->time_out   ? Carbon::parse($log->time_out)->format('g:i A')  : '-',

                        'hours_rendered' => $log->hours_rendered ?? 0,
                        'status'         => Str::title(str_replace('_', ' ', $log->status ?? 'pending')),

                        'am_in_status'     => $log->am_in_status,
                        'lunch_out_status' => $log->lunch_out_status,
                        'lunch_in_status'  => $log->lunch_in_status,
                        'pm_out_status'    => $log->pm_out_status,

                        'is_flagged' => $log->is_flagged,
                        'notes'      => $log->notes,

                        'appeal_status' => $log->appeal_status, 
                    ];
                });

            return response()->json($logs, 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Internal Server Error',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function logAttendance(Request $request)
    {
        $request->validate([
            'type'  => 'required|in:time_in,lunch_out,lunch_in,time_out',
            'lat'   => 'required|numeric',
            'lng'   => 'required|numeric',
            'image' => 'required',
        ]);

        $user   = $request->user();
        $intern = Intern::with('branch')->where('user_id', $user->id)->first();

        if (!$intern || !$intern->branch) {
            return response()->json(['message' => 'Intern profile or assigned branch not found.'], 404);
        }

        $branch = $intern->branch;

        if (is_null($branch->latitude) || is_null($branch->longitude)) {
            return response()->json(['message' => 'Branch location not configured by HR.'], 422);
        }

        $distance      = $this->calculateDistance($request->lat, $request->lng, $branch->latitude, $branch->longitude);
        $allowedRadius = $branch->radius ?? 100;

        if ($distance > $allowedRadius) {
            return response()->json([
                'message' => "Out of bounds! You must be at {$branch->name} to record attendance.",
                'debug'   => "Your distance: " . round($distance) . "m. Allowed: {$allowedRadius}m."
            ], 403);
        }

        // Force the server to generate time strictly in Asia/Manila 
        $now           = Carbon::now('Asia/Manila');
        $today         = $now->toDateString();
        $customMessage = null;

        $log = AttendanceLog::firstOrNew([
            'intern_id' => $intern->id,
            'date'      => $today,
        ]);

        $map = [
            'time_in'   => ['status' => 'am_in_status',     'img' => 'image_in'],
            'lunch_out' => ['status' => 'lunch_out_status', 'img' => 'lunch_out_selfie'],
            'lunch_in'  => ['status' => 'lunch_in_status',  'img' => 'lunch_in_selfie'],
            'time_out'  => ['status' => 'pm_out_status',    'img' => 'image_out'],
        ];
        $fields = $map[$request->type];

        if ($log->{$fields['status']} === 'rejected' || $log->{$fields['status']} === 'failed') {
            return response()->json([
                'message' => 'Photo was rejected. You cannot retake it. Please go to My Logs to file a formal appeal.'
            ], 403);
        }

        $image     = str_replace(['data:image/jpeg;base64,', ' '], ['', '+'], $request->image);
        $imageName = 'attendance/' . $user->id . '_' . time() . '.jpg';
        Storage::disk('public')->put($imageName, base64_decode($image));

        $log->{$fields['img']} = $imageName;
        $log->{$fields['status']} = 'pending';
        $log->is_flagged = 0;
        $log->notes      = null;

        switch ($request->type) {
            case 'time_in':
                if (!$log->time_in) {
                    $log->time_in = $now->toDateTimeString();
                    // Explicitly bind the official start to Manila time as well
                    $officialStart = $now->copy()->setTime(8, 30, 0);

                    if ($now->lessThanOrEqualTo($officialStart)) {
                        $log->status   = 'present';
                        $customMessage = "✅ Clocked in at " . $now->format('g:i A') . ". Hours counted from 8:30 AM.";
                    } else {
                        $log->status   = 'late';
                        $customMessage = "⚠️ Late arrival recorded at " . $now->format('g:i A') . ".";
                    }
                }
                break;

            case 'lunch_out':
                if (!$log->lunch_out) $log->lunch_out = $now->toDateTimeString();
                break;

            case 'lunch_in':
                if (!$log->lunch_in) $log->lunch_in = $now->toDateTimeString();
                break;

            case 'time_out':
                if (!$log->time_in) return response()->json(['message' => 'You must time in first.'], 400);

                if (!$log->time_out) {
                    $log->time_out = $now->toDateTimeString();

                    $actualIn       = Carbon::parse($log->time_in, 'Asia/Manila');
                    $officialStart  = Carbon::parse($log->date, 'Asia/Manila')->setTime(8, 30, 0);
                    $effectiveIn    = $actualIn->lessThan($officialStart) ? $officialStart : $actualIn;

                    $totalMinutes = $effectiveIn->diffInMinutes($now);
                    if ($totalMinutes > 300) {
                        $totalMinutes -= 60; 
                    }
                    $log->hours_rendered = round($totalMinutes / 60, 2);
                }
                break;
        }

        $log->save();

        // ✨ TRIGGER SYNC ✨ Updates their master profile instantly!
        $this->syncInternHours($intern->id);

        return response()->json([
            'message' => $customMessage ?? Str::title(str_replace('_', ' ', $request->type)) . ' recorded successfully!',
            'log'     => $log,
        ], 200);
    }

    // =======================================================================
    // ─── PART 2: HR VERIFICATION & DASHBOARDS ──────────────────────────────
    // =======================================================================

    public function verifyLog(Request $request, $id)
    {
        $request->validate([
            'action'     => 'required|in:approve,reject',
            'image_slot' => 'required|in:am_in,lunch_out,lunch_in,pm_out',
            'reason'     => 'nullable|string|max:500',
        ]);

        $log  = AttendanceLog::findOrFail($id);
        $slot = $request->image_slot;
        $statusField  = "{$slot}_status";

        if ($request->action === 'reject') {
            $slotName  = Str::title(str_replace('_', ' ', $slot));
            $reason    = $request->reason ?? 'No reason provided.';
            
            $log->notes      = "{$slotName} Photo Rejected: {$reason}";
            $log->is_flagged = 1;
            $log->{$statusField} = 'rejected';
        } else {
            $log->{$statusField} = 'accepted';
            $log->is_flagged     = 0;
            $log->notes          = null;
        }

        $log->save();

        // ✨ TRIGGER SYNC ✨ In case a rejection affects their hours
        $this->syncInternHours($log->intern_id);

        return response()->json([
            'message' => $request->action === 'approve' ? 'Photo approved successfully.' : 'Photo rejected. Intern must file an appeal.',
            'status'  => $log->{$statusField},
        ]);
    }

    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a    = sin($dLat / 2) * sin($dLat / 2) +
                cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
                sin($dLon / 2) * sin($dLon / 2);
        $c    = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return round($earthRadius * $c);
    }

    public function getInternAttendanceForHR($id)
    {
        $intern = Intern::where('user_id', $id)->first();

        if (!$intern) {
            return response()->json([
                'logs'  => [],
                'stats' => ['hours' => 0, 'days' => 0, 'avgIn' => '--:--', 'rate' => '0%']
            ], 200);
        }

        $logs       = AttendanceLog::where('intern_id', $intern->id)->orderBy('date', 'desc')->get();
        $totalHours = $logs->sum('hours_rendered');
        $daysPresent = $logs->count();

        $avgIn      = '--:--';
        $timeInLogs = $logs->filter(fn($log) => !is_null($log->time_in));

        if ($timeInLogs->count() > 0) {
            $totalMinutes = 0;
            foreach ($timeInLogs as $log) {
                $time = Carbon::parse($log->time_in);
                $totalMinutes += ($time->hour * 60) + $time->minute;
            }
            $avgMinutes = $totalMinutes / $timeInLogs->count();
            // Assign explicitly
            $avgIn      = Carbon::today('Asia/Manila')->addMinutes($avgMinutes)->format('h:i A');
        }

        $completionRate = $totalHours > 0 ? min(100, round(($totalHours / 486) * 100, 1)) : 0;

        return response()->json([
            'logs'  => $logs->map(fn($log) => [
                'id'          => $log->id,
                'date'        => Carbon::parse($log->date)->format('Y-m-d'),
                'am_in'       => $log->time_in   ? Carbon::parse($log->time_in)->format('H:i:s')   : null,
                'am_out'      => $log->lunch_out  ? Carbon::parse($log->lunch_out)->format('H:i:s') : null,
                'pm_in'       => $log->lunch_in   ? Carbon::parse($log->lunch_in)->format('H:i:s')  : null,
                'pm_out'      => $log->time_out   ? Carbon::parse($log->time_out)->format('H:i:s')  : null,
                'total_hours' => $log->hours_rendered ?? 0,
                'status'      => Str::title(str_replace('_', ' ', $log->status ?? 'Present')),
            ]),
            'stats' => [
                'hours' => round($totalHours, 2),
                'days'  => $daysPresent,
                'avgIn' => $avgIn,
                'rate'  => $completionRate . '%',
            ],
        ]);
    }

    public function getVerificationLogs(Request $request)
    {
        try {
            $logs = AttendanceLog::with('intern.user')
                ->where(function ($q) {
                    $q->whereNotNull('image_in')
                      ->orWhereNotNull('lunch_out_selfie')
                      ->orWhereNotNull('lunch_in_selfie')
                      ->orWhereNotNull('image_out');
                })
                ->get()
                ->map(function ($log) {
                    $firstName  = ($log->intern && $log->intern->user) ? $log->intern->user->first_name : 'Unknown';
                    $lastName   = ($log->intern && $log->intern->user) ? $log->intern->user->last_name  : 'Intern';
                    $department = $log->intern ? $log->intern->assigned_department : 'N/A';

                    return [
                        'id'          => $log->id,
                        'intern_name' => $firstName . ' ' . $lastName,
                        'department'  => $department,
                        'date'        => $log->date,

                        'image_in'           => $log->image_in,
                        'am_in_status'       => $log->am_in_status,
                        'lunch_out_selfie'   => $log->lunch_out_selfie,
                        'lunch_out_status'   => $log->lunch_out_status,
                        'lunch_in_selfie'    => $log->lunch_in_selfie,
                        'lunch_in_status'    => $log->lunch_in_status,
                        'image_out'          => $log->image_out,
                        'pm_out_status'      => $log->pm_out_status,

                        'is_flagged'  => $log->is_flagged,
                        'flag_reason' => $log->notes,
                    ];
                });

            return response()->json($logs);
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Internal Server Error',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function getNotifications(Request $request)
    {
        $user   = $request->user();
        $intern = Intern::where('user_id', $user->id)->first();

        if (!$intern) return response()->json([]);

        $notifications = AttendanceLog::where('intern_id', $intern->id)
            ->where('is_flagged', 1)
            ->whereNotNull('notes')
            ->orderBy('date', 'desc')
            ->get()
            ->map(fn($log) => [
                'id'         => $log->id,
                'type'       => 'rejection',
                'title'      => 'Photo Rejected - Action Required',
                'message'    => $log->notes . ' Please go to your Logs page to file an appeal.',
                'date'       => Carbon::parse($log->date)->format('M d, Y'),
                'created_at' => $log->updated_at->diffForHumans(),
            ]);

        return response()->json($notifications);
    }

    // =======================================================================
    // ─── PART 3: APPEALS MANAGEMENT SYSTEM (WITH BULLETPROOF ERROR CATCHING)
    // =======================================================================

    public function submitAppeal(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'appeal_text' => 'required|string|max:1000',
                'appeal_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120', // 5MB max
            ]);

            $attendanceLog = AttendanceLog::findOrFail($id);
            $intern = Intern::where('user_id', auth()->id())->first();

            if (!$intern || $attendanceLog->intern_id !== $intern->id) {
                return response()->json(['success' => false, 'message' => 'Unauthorized action.'], 403);
            }

            $appealFilePath = null;
            if ($request->hasFile('appeal_file')) {
                $file = $request->file('appeal_file');
                $appealFilePath = $file->store('appeals', 'public');
            }

            $attendanceLog->appeal_text = $validated['appeal_text'];
            $attendanceLog->appeal_file_path = $appealFilePath;
            $attendanceLog->appeal_status = 'pending';
            $attendanceLog->appeal_submitted_at = now('Asia/Manila');
            $attendanceLog->save();

            return response()->json(['success' => true, 'message' => 'Appeal submitted successfully.'], 201);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Crash: ' . $e->getMessage()], 500);
        }
    }

    public function getAppeals(Request $request)
    {
        try {
            if (!in_array(auth()->user()->role, ['hr', 'superadmin'])) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $status = $request->query('status');

            $query = AttendanceLog::whereNotNull('appeal_status')
                ->orWhereNotNull('appeal_text');

            if ($status && in_array($status, ['pending', 'approved', 'rejected'])) {
                $query->where('appeal_status', $status);
            }

            $appeals = $query
                ->with('intern.user') 
                ->orderBy('appeal_submitted_at', 'desc')
                ->paginate(15);

            return response()->json([
                'success' => true,
                'data'    => $appeals,
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Crash: ' . $e->getMessage()], 500);
        }
    }

    public function getAppealStats()
    {
        try {
            if (!in_array(auth()->user()->role, ['hr', 'superadmin'])) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $stats = [
                'pending'  => AttendanceLog::where('appeal_status', 'pending')->count(),
                'approved' => AttendanceLog::where('appeal_status', 'approved')->count(),
                'rejected' => AttendanceLog::where('appeal_status', 'rejected')->count(),
                'total'    => AttendanceLog::whereNotNull('appeal_status')->count(),
            ];

            return response()->json(['success' => true, 'data' => $stats]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Crash: ' . $e->getMessage()], 500);
        }
    }

    public function approveAppeal($id)
    {
        try {
            if (!in_array(auth()->user()->role, ['hr', 'superadmin'])) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $attendanceLog = AttendanceLog::findOrFail($id);

            if ($attendanceLog->appeal_status !== 'pending') {
                return response()->json(['success' => false, 'message' => 'This appeal has already been processed.'], 422);
            }

            $attendanceLog->appeal_status = 'approved';
            $attendanceLog->appeal_responded_at = now('Asia/Manila');
            $attendanceLog->notes = null;
            $attendanceLog->is_flagged = 0;
            $attendanceLog->status = 'present'; 
            $attendanceLog->save();

            // ✨ TRIGGER SYNC ✨ Instantly updates their master hours!
            $this->syncInternHours($attendanceLog->intern_id);

            return response()->json(['success' => true, 'message' => 'Appeal approved successfully.']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Crash: ' . $e->getMessage()], 500);
        }
    }

    public function rejectAppeal(Request $request, $id)
    {
        try {
            if (!in_array(auth()->user()->role, ['hr', 'superadmin'])) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            $validated = $request->validate([
                'rejection_reason' => 'required|string|max:500',
            ]);

            $attendanceLog = AttendanceLog::findOrFail($id);

            if ($attendanceLog->appeal_status !== 'pending') {
                return response()->json(['success' => false, 'message' => 'This appeal has already been processed.'], 422);
            }

            $attendanceLog->appeal_status = 'rejected';
            $attendanceLog->appeal_rejection_reason = $validated['rejection_reason'];
            $attendanceLog->appeal_responded_at = now('Asia/Manila');
            $attendanceLog->save();

            return response()->json(['success' => true, 'message' => 'Appeal rejected successfully.']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Crash: ' . $e->getMessage()], 500);
        }
    }

    public function downloadAppealFile($id)
    {
        try {
            $attendanceLog = AttendanceLog::findOrFail($id);
            $intern = Intern::where('user_id', auth()->id())->first();

            $isOwner = $intern && $attendanceLog->intern_id === $intern->id;
            
            if (!$isOwner && !in_array(auth()->user()->role, ['hr', 'superadmin'])) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 403);
            }

            if (!$attendanceLog->appeal_file_path) {
                return response()->json(['success' => false, 'message' => 'No file found for this appeal.'], 404);
            }

            return Storage::disk('public')->download($attendanceLog->appeal_file_path);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Crash: ' . $e->getMessage()], 500);
        }
    }

    // =======================================================================
    // ─── PART 4: DATA SYNCHRONIZATION ENGINE ───────────────────────────────
    // =======================================================================

    /**
     * ✨ NEW: The primary Sync Engine.
     * Recalculates total hours based strictly on valid, unflagged daily logs.
     */
    public function syncInternHours($internId)
    {
        // Sum up hours ONLY from valid logs (Present, Late, or Approved Appeals)
        // We explicitly ignore logs that are actively flagged by HR
        $totalHours = AttendanceLog::where('intern_id', $internId)
            ->where(function ($query) {
                $query->whereIn('status', ['present', 'late', 'Present', 'Late'])
                      ->orWhere('appeal_status', 'approved'); 
            })
            ->where('is_flagged', 0) 
            ->sum('hours_rendered');

        // Find the Intern profile and update their grand total
        $intern = Intern::find($internId);
        if ($intern) {
            $intern->hours_rendered = $totalHours;
            $intern->save();
        }

        return $totalHours;
    }

    /**
     * ✨ NEW: Bulk sync function for HR
     * Recalculates hours for every single intern in the system.
     */
    public function syncAllInternHours(Request $request)
    {
        try {
            if (!in_array($request->user()->role, ['hr', 'superadmin'])) {
                return response()->json(['message' => 'Unauthorized access.'], 403);
            }

            $interns = Intern::all();
            $updatedCount = 0;

            foreach ($interns as $intern) {
                $this->syncInternHours($intern->id);
                $updatedCount++;
            }

            return response()->json([
                'message' => "Successfully synchronized hours for {$updatedCount} interns!"
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to bulk sync hours',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}