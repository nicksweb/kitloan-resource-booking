<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            SettingsSeeder::class,
            MessageTemplateSeeder::class,
            AdminUserSeeder::class,
            SchedulePeriodSeeder::class,
        ]);

        if (! app()->environment('production')) {
            $this->call(DemoDataSeeder::class);
        }
    }
}
