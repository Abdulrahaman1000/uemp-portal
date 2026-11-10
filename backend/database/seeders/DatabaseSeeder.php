<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Demo data for UEMP. Safe to run more than once (records are matched by their unique keys).
     * For a clean start use: php artisan migrate:fresh --seed
     */
    public function run(): void
    {
        DB::transaction(function () {
            $this->call([
                AcademicStructureSeeder::class,
                UserSeeder::class,
                CourseSeeder::class,
                RegistrationSeeder::class,
                ResultSeeder::class,
                AuditLogSeeder::class,
            ]);
        });

        $this->command?->info('Demo accounts (password for all: '.DemoSeed::password().')');
        $this->command?->table(['Role', 'Email'], [
            ['Super admin', 'superadmin@northstar.edu'],
            ['Dept admin (Computer Science)', 'deptadmin@northstar.edu'],
            ['Dept admin (Economics)', 'h.cooper@northstar.edu'],
            ['Lecturer', 'lecturer@northstar.edu'],
            ['Student', 'student@northstar.edu'],
        ]);
    }
}
