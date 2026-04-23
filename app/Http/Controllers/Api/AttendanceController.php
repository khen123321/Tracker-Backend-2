<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AttendanceLog;
use App\Models\Intern;
use App\Models\Branch;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    /**
     * 1. GET HISTORY (DTR Format for "My Logs" Page)
     */
    public function getHistory(Request $request)
    {
        try {
            $user = $request->user();
            $intern = Intern::where('user_id', $user->id)->first();

            if (!$intern) {
                return response()->json(['message' => 'Intern profile not found.'], 404);
            }

            $logs = AttendanceLog::where('intern_id', $intern->id)
                ->orderBy('date', 'desc')
                ->get()
                ->map(function($log) {
                    return [
                        'id' => $log->id,
                        'formatted_date' => $log->date ? Carbon::parse($log->date)->format('F j, Y') : 'N/A',
                        'time_in_am'  => $log->time_in ? Carbon::parse($log->time_in)->format('g:i A') : '-',
                        'time_out_am' => $log->lunch_out ? Carbon::parse($log->lunch_out)->format('g:i A') : '-',
                        'time_in_pm'  => $log->lunch_in ? Carbon::parse($log->lunch_in)->format('g:i A') : '-',
                        'time_out_pm' => $log->time_out ? Carbon::parse($log->time_out)->format('g:i A') : '-',
                        'hours_rendered' => $log->hours_rendered ?? 0,
                        'status' => Str::title($log->status ?? 'pending'),
                    ];
                });

            return response()->json($logs, 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Internal Server Error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 2. LOG ATTENDANCE (Clock In/Out with Dynamic Geofencing & HR Time Rules)
     */
    public function logAttendance(Request $request)
    {
        $request->validate([
            'type'  => 'required|in:time_in,lunch_out,lunch_in,time_out',
            'lat'   => 'required|numeric',
            'lng'   => 'required|numeric',
            'image' => 'required' 
        ]);

        $user = $request->user();
        
        // DYNAMIC FETCH: Get Intern with their Assigned Branch
        $intern = Intern::with('branch')->where('user_id', $user->id)->first();

        if (!$intern || !$intern->branch) {
            return response()->json(['message' => 'Intern profile or assigned branch not found.'], 404);
        }

        $branch = $intern->branch;

        // Check if Branch has coordinates set
        if (is_null($branch->latitude) || is_null($branch->longitude)) {
            return response()->json(['message' => 'Branch location not configured by HR.'], 422);
        }

        // Check Distance against the specific Branch coordinates
        $distance = $this->calculateDistance(
            $request->lat, 
            $request->lng, 
            $branch->latitude, 
            $branch->longitude
        );
        
        // Use branch-specific radius, or default to 100m if not set
        $allowedRadius = $branch->radius ?? 100;

        if ($distance > $allowedRadius) {
            return response()->json([
                'message' => "Out of bounds! You must be at {$branch->name} to record attendance.",
                'debug' => "Your distance: " . round($distance) . " meters. Allowed: {$allowedRadius}m."
            ], 403);
        }

        // Handle Image Storage
        $image = $request->image;
        $image = str_replace('data:image/jpeg;base64,', '', $image);
        $image = str_replace(' ', '+', $image);
        $imageName = 'attendance/' . $user->id . '_' . time() . '.jpg';
        Storage::disk('public')->put($imageName, base64_decode($image));

        $today = Carbon::today()->toDateString();
        $now = Carbon::now();
        $customMessage = null; // Used to override the default success toast

        $log = AttendanceLog::firstOrNew([
            'intern_id' => $intern->id,
            'date'      => $today
        ]);

        switch ($request->type) {
            case 'time_in':
                if ($log->time_in) return response()->json(['message' => 'Already timed in for today.'], 400);
                
                $log->time_in = $now->toDateTimeString();
                $log->image_in = $imageName;

                // 🕰️ RULE: 8:30 AM Official Start Time
                $officialStartTime = Carbon::today()->setTime(8, 30, 0);

                if ($now->lessThanOrEqualTo($officialStartTime)) {
                    $log->status = 'present';
                    $customMessage = "✅ You've clocked in at " . $now->format('g:i A') . ". Your official hours will be counted starting at 8:30 AM.";
                } else {
                    $log->status = 'late';
                    $customMessage = "⚠️ Late arrival recorded at " . $now->format('g:i A') . ".";
                }
                break;

            case 'lunch_out':
                $log->lunch_out = $now->toDateTimeString(); 
                $log->lunch_out_selfie = $imageName; 
                break;

            case 'lunch_in':
                $log->lunch_in = $now->toDateTimeString(); 
                $log->lunch_in_selfie = $imageName; 
                break;

            case 'time_out':
                if (!$log->time_in) return response()->json(['message' => 'You must time in first.'], 400);
                $log->time_out = $now->toDateTimeString(); 
                $log->image_out = $imageName;
                
                // 🕰️ HOURS CALCULATION RULES
                $actualTimeIn = Carbon::parse($log->time_in);
                $officialStartTime = Carbon::parse($log->date)->setTime(8, 30, 0);
                
                // Rule: Cap the start time at 8:30 AM for early birds
                $effectiveTimeIn = $actualTimeIn->lessThan($officialStartTime) ? $officialStartTime : $actualTimeIn;
                $end = $now;
                
                $totalMinutes = $effectiveTimeIn->diffInMinutes($end);
                
                // Rule: Auto-deduct 1 hour (60 mins) for lunch if they worked over 5 hours (300 mins)
                if ($totalMinutes > 300) {
                    $totalMinutes -= 60;
                }
                
                $hours = $totalMinutes / 60;
                $log->hours_rendered = round($hours, 2);
                break;
        }

        $log->save();

        // Return custom UI message if defined, otherwise generic success message
        return response()->json([
            'message' => $customMessage ?? Str::title(str_replace('_', ' ', $request->type)) . ' recorded successfully!',
            'log' => $log
        ], 200);
    }

    /**
     * 3. DISTANCE HELPER (Haversine Formula)
     */
    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371000; // meters
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return round($earthRadius * $c);
    }

    /**
     * 4. GET HR INTERN ATTENDANCE MODAL DATA
     */
    public function getInternAttendanceForHR($id)
    {
        $intern = Intern::where('user_id', $id)->first();

        if (!$intern) {
            return response()->json([
                'logs' => [],
                'stats' => ['hours' => 0, 'days' => 0, 'avgIn' => '--:--', 'rate' => '0%']
            ], 200);
        }

        $logs = AttendanceLog::where('intern_id', $intern->id)
            ->orderBy('date', 'desc')
            ->get();

        $totalHours = $logs->sum('hours_rendered');
        $daysPresent = $logs->count();
        
        $avgIn = '--:--';
        $timeInLogs = $logs->filter(function($log) { return !is_null($log->time_in); });
        
        if ($timeInLogs->count() > 0) {
            $totalMinutes = 0;
            foreach ($timeInLogs as $log) {
                $time = Carbon::parse($log->time_in);
                $totalMinutes += ($time->hour * 60) + $time->minute;
            }
            $avgMinutes = $totalMinutes / $timeInLogs->count();
            $avgIn = Carbon::today()->addMinutes($avgMinutes)->format('h:i A');
        }

        // Assuming 486 standard OJT hours
        $completionRate = round(($totalHours / 486) * 100, 1);

        return response()->json([
            'logs' => $logs->map(function($log) {
                return [
                    'id' => $log->id,
                    'date' => Carbon::parse($log->date)->format('F j, Y'),
                    'am_in' => $log->time_in ? Carbon::parse($log->time_in)->format('H:i:s') : null,
                    'am_out' => $log->lunch_out ? Carbon::parse($log->lunch_out)->format('H:i:s') : null,
                    'pm_in' => $log->lunch_in ? Carbon::parse($log->lunch_in)->format('H:i:s') : null,
                    'pm_out' => $log->time_out ? Carbon::parse($log->time_out)->format('H:i:s') : null,
                    'total_hours' => $log->hours_rendered ?? 0,
                    'status' => Str::title($log->status ?? 'Present')
                ];
            }),
            'stats' => [
                'hours' => round($totalHours, 2),
                'days' => $daysPresent,
                'avgIn' => $avgIn,
                'rate' => $completionRate . '%'
            ]
        ], 200);
    }

    /**
     * 5. GET CAMERA VERIFICATION LOGS (HR)
     */
    public function getVerificationLogs(Request $request) 
    {
        $query = AttendanceLog::with('intern.user')
            ->where(function($q) {
                $q->whereNotNull('image_in')->orWhereNotNull('image_out');
            });

        $logs = $query->get()->map(function($log) {
            $status = 'verified';
            if (($log->image_in && $log->time_in_selfie_approved === null) || 
                ($log->image_out && $log->time_out_selfie_approved === null)) {
                $status = 'pending_review';
            }

            $firstName = $log->intern && $log->intern->user ? $log->intern->user->first_name : 'Unknown';
            $lastName = $log->intern && $log->intern->user ? $log->intern->user->last_name : 'Intern';

            return [
                'id' => $log->id,
                'intern_name' => $firstName . ' ' . $lastName,
                'department' => $log->intern->assigned_department ?? 'N/A', 
                'date' => $log->date,
                'time_in' => $log->time_in ? Carbon::parse($log->time_in)->format('h:i A') : null,
                'time_out' => $log->time_out ? Carbon::parse($log->time_out)->format('h:i A') : null,
                'image_in' => $log->image_in,
                'image_out' => $log->image_out,
                'is_flagged' => $log->is_flagged,
                'flag_reason' => $log->notes ?? 'Location mismatch or system flag',
                'status' => $status
            ];
        });

        return response()->json($logs);
    }

    /**
     * 6. APPROVE/REJECT SELFIE LOG (HR)
     */
    public function verifyLog(Request $request, $id) 
    {
        $request->validate([
            'action' => 'required|in:approve,reject'
        ]);

        $log = AttendanceLog::findOrFail($id);

        if ($request->action === 'approve') {
            if ($log->image_in) $log->time_in_selfie_approved = 1;
            if ($log->image_out) $log->time_out_selfie_approved = 1;
            
            $log->is_flagged = 0; 
            $log->save();

            return response()->json(['message' => 'Attendance verified successfully']);
        } 
        
        if ($request->action === 'reject') {
            if ($log->image_in) $log->time_in_selfie_approved = 0;
            if ($log->image_out) $log->time_out_selfie_approved = 0;
            
            $log->status = 'invalid'; 
            $log->save();

            return response()->json(['message' => 'Attendance rejected']);
        }
    }

    /**
     * 7. GET NOTIFICATIONS (Intern)
     * Get unread or flagged notifications for the intern
     */
    public function getNotifications(Request $request)
    {
        $user = $request->user();
        $intern = Intern::where('user_id', $user->id)->first();

        if (!$intern) return response()->json([]);

        // Fetch logs that are flagged and have a note from HR
        $notifications = AttendanceLog::where('intern_id', $intern->id)
            ->where('is_flagged', 1)
            ->whereNotNull('notes')
            ->orderBy('date', 'desc')
            ->get()
            ->map(function($log) {
                return [
                    'id' => $log->id,
                    'type' => 'rejection',
                    'title' => 'Attendance Rejected',
                    'message' => $log->notes,
                    'date' => Carbon::parse($log->date)->format('M d, Y'),
                    'created_at' => $log->updated_at->diffForHumans()
                ];
            });

        return response()->json($notifications);
    }
}