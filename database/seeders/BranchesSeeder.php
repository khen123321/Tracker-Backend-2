<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BranchesSeeder extends Seeder
{
    public function run(): void
    {
        // CLIMBS branches from Section 5.1 registration form dropdown
        $branches = [
            'Bulua Branch (Head Office)',
            'Tiano Office',
            'Luzon Branch',
            'Naga Branch',
            'Baguio Branch',
            'Cebu Branch',
        ];

        foreach ($branches as $name) {
            DB::table('branches')->updateOrInsert(
                ['name' => $name],
                [
                    'name'       => $name,
                    'is_active'  => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
