<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Intern;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function getStats(Request $request)
    {
        $today = Carbon::today()->toDateString();

        // ==========================================
        // 1. THE FILTER WALL (Valid Interns Only)
        // ==========================================
        // Lowercase check to catch 'intern' or 'hr intern' regardless of formatting
        $validUserIds = User::whereIn(DB::raw('LOWER(role)'), ['intern', 'hr intern'])->pluck('id');
        $totalInterns = count($validUserIds);
        
        // Get the specific Intern Profile IDs for these valid users
        $validInternIds = Intern::whereIn('user_id', $validUserIds)->pluck('id');

        // ==========================================
        // 2. FETCH ATTENDANCE LOGS (Filtered)
        // ==========================================
        $todayLogs = DB::table('attendance_logs')
            ->whereDate('date', $today)
            ->whereIn('intern_id', $validInternIds) // 🛡️ Completely ignores SuperAdmins/HR Heads
            ->get();

        // ==========================================
        // 3. STRICT CATEGORIES (No Overlap)
        // ==========================================
        $presentToday = $todayLogs->filter(function($log) {
            return strtolower(trim($log->status)) === 'present';
        })->count();

        $lateToday = $todayLogs->filter(function($log) {
            return strtolower(trim($log->status)) === 'late';
        })->count();

        $excusedToday = $todayLogs->filter(function($log) {
            return strtolower(trim($log->status)) === 'excused';
        })->count();

        // Absent = Total Interns minus everyone who has a log today (Present + Late + Excused)
        $absentToday = max(0, $totalInterns - ($presentToday + $lateToday + $excusedToday));

        // ==========================================
        // 4. METRICS & MATH
        // ==========================================
        $totalInBuilding = $presentToday + $lateToday;
        $attendanceRate = $totalInterns > 0 ? round(($totalInBuilding / $totalInterns) * 100) : 0;
        $onTimePercentage = $totalInBuilding > 0 ? round(($presentToday / $totalInBuilding) * 100) : 0;
        
        // Total Hours: Summed up ONLY for valid interns
        $totalHours = DB::table('attendance_logs')
            ->whereIn('intern_id', $validInternIds)
            ->sum('hours_rendered') ?? 0;

        // ==========================================
        // 5. DASHBOARD UI AGGREGATIONS (With JOINs)
        // ==========================================
        $colors = ['#0B1EAE', '#4F63F1', '#8A98E8', '#C2CBF5', '#64748B', '#94A3B8'];

        // Course Distribution (Course is a direct column in interns table)
        $courseDistribution = DB::table('interns')
            ->whereIn('id', $validInternIds)
            ->whereNotNull('course')
            ->where('course', '!=', '')
            ->select('course as name', DB::raw('count(*) as value'))
            ->groupBy('course')
            ->get();

        // Departments (JOIN with departments table via department_id)
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

        // Branches (JOIN with branches table via branch_id)
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

        // Schools (JOIN with schools table via school_id)
        $schools = DB::table('users')
            ->whereIn('id', $validUserIds)
            ->whereNotNull('school') // Assuming the column in users table is named 'school'
            ->where('school', '!=', '')
            ->select('school as name', DB::raw('count(*) as count'))
            ->groupBy('school')
            ->orderByDesc('count')
            ->get()
            ->map(function($school, $index) use ($colors) {
                $school->color = $colors[$index % count($colors)];
                $school->sub = 'University';
                return $school;
            });

        // Pending Forms Placeholder (Can update later when building forms)
        $pendingForms = 0;

        // ==========================================
        // 6. RETURN DATA TO REACT
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
            'pending_forms' => $pendingForms,
            'course_distribution' => $courseDistribution,
            'departments' => $departments,
            'branches' => $branches,
            'schools' => $schools
        ], 200);
    }
}