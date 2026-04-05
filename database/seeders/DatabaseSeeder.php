<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Order matters — foreign keys must exist before referencing tables are seeded
        $this->call([
            SchoolsSeeder::class,      // No dependencies
            BranchesSeeder::class,     // No dependencies
            DepartmentsSeeder::class,  // No dependencies
            UsersSeeder::class,        // No dependencies
            SettingsSeeder::class,     // No dependencies
        ]);
    }
}
