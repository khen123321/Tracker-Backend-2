<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UsersSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            // --- THE SUPER ADMIN (YOU) ---
            [
                'last_name'   => 'Verson',
                'first_name'  => 'Khen Joshua',
                'middle_name' => 'G.',
                // FIXED: Removed the trailing spaces
                'email'       => 'testadmin123@gmail.com',
                'password'    => Hash::make('testadmin123'), 
                'role'        => 'superadmin', // Full system access
                'status'      => 'active',
            ],
            // --- SYSTEM ADMINISTRATOR ---
            [
                'last_name'   => 'Administrator',
                'first_name'  => 'System',
                'middle_name' => null,
                'email'       => 'admin@climbs.com.ph',
                'password'    => Hash::make('Admin@CLIMBS2024!'),
                'role'        => 'admin',
                'status'      => 'active',
            ],
            // --- HR STAFF ---
            [
                'last_name'   => 'Staff',
                'first_name'  => 'HR',
                'middle_name' => null,
                'email'       => 'hr@climbs.com.ph',
                'password'    => Hash::make('HR@CLIMBS2024!'),
                'role'        => 'hr',
                'status'      => 'active',
            ],
        ];

        foreach ($users as $user) {
            DB::table('users')->updateOrInsert(
                ['email' => $user['email']],
                array_merge($user, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}