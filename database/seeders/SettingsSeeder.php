<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        // Default system settings from Section 3.1 (Official Time) and Section 6.9 (Admin Settings)
        $settings = [

            // --- Official Time Schedule (Section 3.1) ---
            [
                'key'         => 'time_am_in',
                'value'       => '08:30',
                'description' => 'Official AM Time In — late if intern clocks in after this',
            ],
            [
                'key'         => 'time_lunch_out',
                'value'       => '12:00',
                'description' => 'Official Lunch Out time',
            ],
            [
                'key'         => 'time_lunch_in',
                'value'       => '13:00',
                'description' => 'Official Lunch In / Afternoon start time',
            ],
            [
                'key'         => 'time_pm_out',
                'value'       => '17:30',
                'description' => 'Official PM Time Out — overtime counted after this',
            ],

            // --- Geofencing (Section 3.9) ---
            [
                'key'         => 'geofence_radius_meters',
                'value'       => '100',
                'description' => 'Clock-in allowed radius from CLIMBS premises center (in meters)',
            ],
            [
                'key'         => 'geofence_lat',
                'value'       => '8.50407844555491',   // Replace with actual CLIMBS HQ coordinates
                'description' => 'CLIMBS premises latitude for geofence center',
            ],
            [
                'key'         => 'geofence_lng',
                'value'       => '124.61422259546289', // Replace with actual CLIMBS HQ coordinates
                'description' => 'CLIMBS premises longitude for geofence center',
            ],

            // --- Default OJT Hours (Section 6.9) ---
            [
                'key'         => 'default_required_hours',
                'value'       => '486',
                'description' => 'Default required OJT hours for new interns',
            ],

            // --- System Info ---
            [
                'key'         => 'system_name',
                'value'       => 'CLIMBS InternTracker',
                'description' => 'System display name',
            ],
            [
                'key'         => 'system_full_name',
                'value'       => 'CLIMBS Internship Monitoring System (CIMS)',
                'description' => 'System full name',
            ],
        ];

        foreach ($settings as $setting) {
            DB::table('settings')->updateOrInsert(
                ['key' => $setting['key']],
                array_merge($setting, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
