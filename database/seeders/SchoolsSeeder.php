<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SchoolsSeeder extends Seeder
{
    public function run(): void
    {
        // Schools list from Section 5.1 of CIMS documentation
        // default_required_hours can be edited per intern by HR later (Section 3.8)
        $schools = [
            ['name' => 'USTP - University of Science and Technology of Southern Philippines', 'default_required_hours' => 486],
            ['name' => 'PHINMA Cagayan de Oro College',                                       'default_required_hours' => 486],
            ['name' => 'Xavier University',                                                    'default_required_hours' => 486],
            ['name' => 'Capitol University',                                                   'default_required_hours' => 486],
            ['name' => 'Lourdes College',                                                      'default_required_hours' => 486],
            ['name' => 'Pilgrim Christian College',                                            'default_required_hours' => 486],
            ['name' => 'Initao College',                                                       'default_required_hours' => 486],
            ['name' => 'BUKSU - Baungon Campus',                                               'default_required_hours' => 486],
        ];

        foreach ($schools as $school) {
            DB::table('schools')->updateOrInsert(
                ['name' => $school['name']],
                array_merge($school, [
                    'is_active'  => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
