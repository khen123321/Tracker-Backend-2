<?php

namespace App\Http\Controllers\Api; 

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Intern;
use App\Models\AttendanceLog;
use Carbon\Carbon;

class InternDashboardController extends Controller
{
    public function getStats(Request $request)
    {
        date_default_timezone_set('Asia/Manila');
        $user = $request->user();
        
        // 1. Get the Intern profile 
        $intern = Intern::where('user_id', $user->id)->first();

        if (!$intern) {
            return response()->json(['message' => 'Intern profile not found'], 404);
        }

        $today = Carbon::today()->toDateString();
        $startOfWeek = Carbon::now()->startOfWeek()->toDateString();
        $endOfWeek = Carbon::now()->endOfWeek()->toDateString();

        // 2. THE CRITICAL SUM (Uses $intern->id to perfectly match the attendance_logs table)
        $totalSavedHours = AttendanceLog::where('intern_id', $intern->id)->sum('hours_rendered') ?? 0;

        // 3. TODAY'S LOG & LIVE TRACKING
        $todayLog = AttendanceLog::where('intern_id', $intern->id)
            ->whereDate('date', $today)
            ->first();
        
        $todayStatus = 'Not Timed In';
        $todayClockIn = '--:--';
        $todayHours = 0;

        if ($todayLog) {
            $todayStatus = $todayLog->status ?? 'Timed In';
            $todayClockIn = $todayLog->time_in ? Carbon::parse($todayLog->time_in)->format('h:i A') : '--:--';
                
            if ($todayLog->time_in && !$todayLog->time_out) {
                $startTime = Carbon::parse($todayLog->time_in);
                $todayHours = round(Carbon::now()->diffInMinutes($startTime) / 60, 1);
            } else {
                $todayHours = round($todayLog->hours_rendered ?? 0, 1);
            }
        }

        // 4. CALCULATE FINAL TOTAL
        $isCurrentlyClockedIn = ($todayLog && $todayLog->time_in && !$todayLog->time_out);
        $actualProgressTotal = $totalSavedHours + ($isCurrentlyClockedIn ? $todayHours : 0);

        // 5. WEEKLY SUMMARY
        $weekLogs = AttendanceLog::where('intern_id', $intern->id)
            ->whereBetween('date', [$startOfWeek, $endOfWeek])
            ->get();

        $weekDaysPresent = $weekLogs->count(); 
        $weekHoursRendered = $weekLogs->sum('hours_rendered') + ($isCurrentlyClockedIn ? $todayHours : 0);

        // 6. PROGRESS, COMPLETION & ✨ TENTATIVE DAYS ✨
        $requiredHours = $intern->required_hours ?? 0;
        
        // Safe fallbacks
        $remainingHours = 0;
        $daysLeft = 0;

        if ($requiredHours > 0) {
            $remainingHours = max(0, $requiredHours - $actualProgressTotal);
            $daysLeft = ceil($remainingHours / 8); 
            $completionDate = ($actualProgressTotal > 0) 
                ? Carbon::now()->addWeekdays($daysLeft)->format('M. d, Y') 
                : 'TBD';
        } else {
            $completionDate = 'No hours required';
        }

        // 7. RETURN COMPLETE DATA PAYLOAD
        return response()->json([
            'totalHoursRequired' => (float)$requiredHours,
            'hoursRendered'      => (float)round($actualProgressTotal, 1),
            'remainingHours'     => (float)round($remainingHours, 1),
            'tentativeDays'      => (int)$daysLeft,
            'completionDate'     => $completionDate,
            'todayStatus'        => $todayStatus,
            'todayClockIn'       => $todayClockIn,
            'todayOfficial'      => '08:15 AM', 
            'todayHours'         => (float)$todayHours,
            'weekDaysPresent'    => $weekDaysPresent,
            'weekHoursRendered'  => (float)round($weekHoursRendered, 1),
        ], 200);
    }
}