<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DepartmentsSeeder extends Seeder
{
    public function run(): void
    {
        // Departments from Section 5.1 registration form
        // supervisor_name can be filled in later via the HR settings panel
        $departments = [
            'Insurtech - Business Analyst & System Development Unit',
            'CARES',
            'EDP',
            'CESLA',
            'Finance',
            'HR',
        ];

        foreach ($departments as $name) {
            DB::table('departments')->updateOrInsert(
                ['name' => $name],
                [
                    'name'            => $name,
                    'supervisor_name' => null,
                    'is_active'       => true,
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]
            );
        }
    }
}
