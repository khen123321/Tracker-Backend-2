<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Intern;
use App\Models\InternRequest; 
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function getStats(Request $request)
    {
        $today = Carbon::today()->toDateString();

        // 1. THE FILTER WALL
        $validUserIds = User::whereIn(DB::raw('LOWER(role)'), ['intern', 'hr intern'])->pluck('id');
        $totalInterns = count($validUserIds);
        $validInternIds = Intern::whereIn('user_id', $validUserIds)->pluck('id');

        // 2. FETCH ATTENDANCE LOGS
        $todayLogs = DB::table('attendance_logs')
            ->whereDate('date', $today)
            ->whereIn('intern_id', $validInternIds) 
            ->get();

        // 3. STRICT CATEGORIES
        $presentToday = $todayLogs->filter(fn($log) => strtolower(trim($log->status)) === 'present')->count();
        $lateToday = $todayLogs->filter(fn($log) => strtolower(trim($log->status)) === 'late')->count();
        $excusedToday = $todayLogs->filter(fn($log) => strtolower(trim($log->status)) === 'excused')->count();
        $absentToday = max(0, $totalInterns - ($presentToday + $lateToday + $excusedToday));

        // 4. METRICS & MATH
        $totalInBuilding = $presentToday + $lateToday;
        $attendanceRate = $totalInterns > 0 ? round(($totalInBuilding / $totalInterns) * 100) : 0;
        $onTimePercentage = $totalInBuilding > 0 ? round(($presentToday / $totalInBuilding) * 100) : 0;
        
        $totalHours = DB::table('attendance_logs')
            ->whereIn('intern_id', $validInternIds)
            ->sum('hours_rendered') ?? 0;

        // 5. DASHBOARD UI AGGREGATIONS
        $colors = ['#0B1EAE', '#4F63F1', '#8A98E8', '#C2CBF5', '#64748B', '#94A3B8'];

        $courseDistribution = DB::table('interns')
            ->whereIn('id', $validInternIds)
            ->whereNotNull('course')
            ->where('course', '!=', '')
            ->select('course as name', DB::raw('count(*) as value'))
            ->groupBy('course')
            ->get();

        $departments = DB::table('interns')
            ->join('departments', 'interns.department_id', '=', 'departments.id')
            ->whereIn('interns.id', $validInternIds)
            ->select('departments.name as name', DB::raw('count(interns.id) as count'))
            ->groupBy('departments.name')
            ->orderByDesc('count')
            ->get()
            ->map(function($dept, $index) use ($totalInterns, $colors) {
                $dept->total = $totalInterns;
                $dept->color = $colors[$index % count($colors)];
                return $dept;
            });

        $branches = DB::table('interns')
            ->join('branches', 'interns.branch_id', '=', 'branches.id')
            ->whereIn('interns.id', $validInternIds)
            ->select('branches.name as name', DB::raw('count(interns.id) as count'))
            ->groupBy('branches.name')
            ->orderByDesc('count')
            ->get()
            ->map(function($branch) {
                $branch->isHQ = in_array(strtolower($branch->name), ['main', 'bulua', 'head office']);
                $branch->sub = 'Branch Office';
                return $branch;
            });

        // ✨ FIXED: Now joining the 'schools' table via the 'interns' school_id
        $schools = DB::table('interns')
            ->join('schools', 'interns.school_id', '=', 'schools.id')
            ->whereIn('interns.id', $validInternIds)
            ->select('schools.name as name', DB::raw('count(interns.id) as count'))
            ->groupBy('schools.name')
            ->orderByDesc('count')
            ->get()
            ->map(function($school, $index) use ($colors) {
                $school->color = $colors[$index % count($colors)];
                $school->sub = 'University';
                return $school;
            });

        // ==========================================
        // 6. FETCH PENDING REQUESTS
        // ==========================================
        $rawRequests = InternRequest::with('user')
            ->whereRaw('LOWER(status) = ?', ['pending'])
            ->orderBy('created_at', 'asc')
            ->get();

        $pendingRequests = [
            'absent' => [],
            'halfDay' => [],
            'overtime' => []
        ];

        foreach ($rawRequests as $req) {
            $internName = $req->user 
                ? $req->user->first_name . ' ' . $req->user->last_name 
                : 'Unknown Intern';

            $formattedReq = [
                'id' => $req->id,
                'intern_name' => $internName,
                'date' => \Carbon\Carbon::parse($req->date_of_absence)->format('M d, Y'),
                'reason' => $req->reason ?? 'No reason provided',
                'hours' => null 
            ];

            // Group them based on the type they submitted
            $typeStr = strtolower($req->type);
            
            if (str_contains($typeStr, 'absent') || str_contains($typeStr, 'leave')) {
                $pendingRequests['absent'][] = $formattedReq;
            } elseif (str_contains($typeStr, 'half')) {
                $pendingRequests['halfDay'][] = $formattedReq;
            } elseif (str_contains($typeStr, 'overtime') || str_contains($typeStr, 'extra')) {
                $pendingRequests['overtime'][] = $formattedReq;
            } else {
                $pendingRequests['absent'][] = $formattedReq; 
            }
        }

        // ==========================================
        // 7. RETURN DATA TO REACT
        // ==========================================
        return response()->json([
            'total_interns' => $totalInterns,
            'clocked_in_today' => $totalInBuilding,
            'attendance_rate' => $attendanceRate,
            'total_hours' => round($totalHours, 1),
            'on_time_percentage' => max(0, $onTimePercentage),
            'today' => [
                'present' => $presentToday,
                'absent' => $absentToday,
                'excused' => $excusedToday,
                'late' => $lateToday,
                'unexcused' => $absentToday
            ],
            'pending_forms' => count($rawRequests), 
            'course_distribution' => $courseDistribution,
            'departments' => $departments,
            'branches' => $branches,
            'schools' => $schools,
            'pending_requests' => $pendingRequests
        ], 200);
    }

    public function getSchools()
    {
        $validUserIds = User::whereIn(DB::raw('LOWER(role)'), ['intern', 'hr intern'])->pluck('id');
        $validInternIds = Intern::whereIn('user_id', $validUserIds)->pluck('id');
        
        $colors = ['#0B1EAE', '#4F63F1', '#8A98E8', '#C2CBF5', '#64748B', '#94A3B8'];

        // ✨ FIXED: Now joining the 'schools' table to get real chart data
        $schools = DB::table('interns')
            ->join('schools', 'interns.school_id', '=', 'schools.id')
            ->whereIn('interns.id', $validInternIds)
            ->select('schools.name as name', DB::raw('count(interns.id) as value'))
            ->groupBy('schools.name')
            ->orderByDesc('value')
            ->get()
            ->map(function($school, $index) use ($colors) {
                $school->color = $colors[$index % count($colors)];
                return $school;
            });

        return response()->json($schools, 200);
    }
}