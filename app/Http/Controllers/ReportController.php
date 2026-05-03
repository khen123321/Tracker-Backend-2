<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\AttendanceLog; 
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\DB; // ✨ THE FIX: We added DB so we can bypass the Models

class ReportController extends Controller
{
    public function export(Request $request)
    {
        $type = $request->input('report_type', 'ojt'); // 'ojt' or 'school'
        
        // Fetch interns
        $interns = User::where('role', 'intern')->with('intern')->get();

        // Fetch the live hours calculation
        $internIds = $interns->pluck('intern.id')->filter()->toArray();
        $allTimeHours = AttendanceLog::whereIn('intern_id', $internIds)
            ->selectRaw('intern_id, SUM(hours_rendered) as total_hours')
            ->groupBy('intern_id')
            ->pluck('total_hours', 'intern_id');

        $filename = "CLIMBS_" . strtoupper($type) . "_Report_" . date('Y-m-d') . ".csv";

        $headers = [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$filename",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $callback = function() use($interns, $type, $allTimeHours) {
            $file = fopen('php://output', 'w');

            if ($type === 'ojt') {
                fputcsv($file, ['Intern Name', 'Email', 'School', 'Department', 'Required Hours', 'Rendered Hours', 'Progress %', 'Status']);

                foreach ($interns as $intern) {
                    $internId = $intern->intern->id ?? null;
                    $req = $intern->intern->required_hours ?? 486;
                    
                    $ren = $internId ? (float)($allTimeHours->get($internId) ?? 0) : 0;
                    
                    $percent = $req > 0 ? round(($ren / $req) * 100, 1) : 0;
                    if ($percent > 100) $percent = 100; 
                    
                    $status = $percent >= 100 ? 'Completed' : 'On-going';

                    // ✨ THE NUCLEAR OPTION: Direct Database Lookup ✨
                    // This forces Laravel to look straight at the DB, ignoring broken Models
                    $schoolName = 'Unassigned';
                    $schoolId = $intern->intern->school_id ?? null;
                    if ($schoolId) {
                        $schoolName = DB::table('schools')->where('id', $schoolId)->value('name') ?? 'Unassigned';
                    }

                    $deptName = 'Unassigned';
                    $deptId = $intern->intern->department_id ?? null;
                    if ($deptId) {
                        $deptName = DB::table('departments')->where('id', $deptId)->value('name') ?? 'Unassigned';
                    }

                    fputcsv($file, [
                        $intern->first_name . ' ' . $intern->last_name,
                        $intern->email, 
                        $schoolName, // ✨ Prints raw DB School Name
                        $deptName,   // ✨ Prints raw DB Department Name
                        $req,
                        $ren,
                        $percent . '%',
                        $status
                    ]);
                }
            } else {
                fputcsv($file, ['School / University', 'Total Interns', 'Active Interns', 'Completed Interns', 'Average Completion %']);

                $schoolData = [];

                foreach ($interns as $intern) {
                    
                    // ✨ THE NUCLEAR OPTION ✨
                    $schoolName = 'Unassigned';
                    $schoolId = $intern->intern->school_id ?? null;
                    if ($schoolId) {
                        $schoolName = DB::table('schools')->where('id', $schoolId)->value('name') ?? 'Unassigned';
                    }

                    if (!isset($schoolData[$schoolName])) {
                        $schoolData[$schoolName] = ['total' => 0, 'req' => 0, 'ren' => 0, 'completed' => 0];
                    }

                    $schoolData[$schoolName]['total']++;
                    
                    $internId = $intern->intern->id ?? null;
                    $req = $intern->intern->required_hours ?? 486;
                    
                    $ren = $internId ? (float)($allTimeHours->get($internId) ?? 0) : 0;
                    
                    $schoolData[$schoolName]['req'] += $req;
                    $schoolData[$schoolName]['ren'] += $ren;

                    if ($ren >= $req && $req > 0) {
                        $schoolData[$schoolName]['completed']++;
                    }
                }

                foreach ($schoolData as $schoolName => $data) {
                    $active = $data['total'] - $data['completed'];
                    $avgProgress = $data['req'] > 0 ? round(($data['ren'] / $data['req']) * 100, 1) : 0;
                    if ($avgProgress > 100) $avgProgress = 100; 

                    fputcsv($file, [
                        $schoolName,
                        $data['total'],
                        $active,
                        $data['completed'],
                        $avgProgress . '%'
                    ]);
                }
            }

            fclose($file);
        };

        return Response::stream($callback, 200, $headers);
    }
}