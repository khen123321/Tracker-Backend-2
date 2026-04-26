<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Intern;
use App\Models\AttendanceLog;
use Carbon\Carbon;

class MarkAbsentInterns extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'interns:mark-absent';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scans active interns and marks them as Absent if they did not clock in today.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $today = Carbon::now();

        // 1. Do not run on weekends (Saturday & Sunday)
        // ✨ NOTE: Add '//' to the start of the next 4 lines if you want to test this on a Sunday!
        if ($today->isWeekend()) {
            $this->info('Today is a weekend. Skipping absent check.');
            return Command::SUCCESS;
        }

        // 2. Get all interns whose status is currently 'active'
        $interns = Intern::where('status', 'active')->get();
        $absentCount = 0;

        $this->info('Scanning ' . $interns->count() . ' active interns for attendance...');

        foreach ($interns as $intern) {
            // 3. Check if this intern already has an attendance log for today
            $hasLog = AttendanceLog::where('intern_id', $intern->id)
                ->whereDate('date', $today->toDateString())
                ->exists();

            // 4. If no log exists, insert the Absent record using YOUR EXACT DB COLUMNS
            if (!$hasLog) {
                AttendanceLog::create([
                    'intern_id'      => $intern->id,
                    'date'           => $today->toDateString(),
                    'status'         => 'absent', // Match your lowercase DB convention
                    'time_in'        => null,
                    'lunch_out'      => null,
                    'lunch_in'       => null,
                    'time_out'       => null,
                    'hours_rendered' => 0.00,
                    'is_late'        => 0, // Good practice to default these
                    'is_flagged'     => 0,
                    'overtime_hours' => 0.00
                ]);
                
                $absentCount++;
            }
        }

        // 5. Output a success message to the terminal/logs
        $this->info("Complete! {$absentCount} interns were marked as Absent for {$today->toDateString()}.");
        
        return Command::SUCCESS;
    }
}