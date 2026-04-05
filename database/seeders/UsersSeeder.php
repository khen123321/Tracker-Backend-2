<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UsersSeeder extends Seeder
{
    public function run(): void
    {
        // Default system accounts
        // IMPORTANT: Change these passwords immediately after first login!
        // HR accounts are pre-created by Admin only — no public sign-up for HR (Section 6.1)

        $users = [
            [
                'last_name'  => 'Administrator',
                'first_name' => 'System',
                'middle_name'=> null,
                'email'      => 'admin@climbs.com.ph',
                'password'   => Hash::make('Admin@CLIMBS2024!'),
                'role'       => 'admin',
                'is_active'  => true,
            ],
            [
                'last_name'  => 'Staff',
                'first_name' => 'HR',
                'middle_name'=> null,
                'email'      => 'hr@climbs.com.ph',
                'password'   => Hash::make('HR@CLIMBS2024!'),
                'role'       => 'hr',
                'is_active'  => true,
            ],
        ];

        foreach ($users as $user) {
            DB::table('users')->updateOrInsert(
                ['email' => $user['email']],
                array_merge($user, [
                    'profile_photo' => null,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ])
            );
        }
    }
}
