<?php

namespace Database\Seeders;

use App\Models\SchedulePeriod;
use Illuminate\Database\Seeder;

/**
 * Seeded from the school's actual timetable (not demo data — this runs in
 * every environment). The Senior School list the periods were provided from
 * had one internally-inconsistent entry ("12:35 Pastoral Care", which falls
 * before the immediately preceding "12:50" and overlaps Period 3) — that one
 * was deliberately left out rather than guessed. Everything here is a normal
 * editable row under Administration -> Periods, so it's a quick fix either
 * way.
 */
class SchedulePeriodSeeder extends Seeder
{
    public function run(): void
    {
        $junior = [
            ['name' => 'Pastoral Care', 'start_time' => '08:35', 'end_time' => '08:45'],
            ['name' => 'Periods 1-3', 'start_time' => '08:45', 'end_time' => '10:30'],
            ['name' => 'Morning Break', 'start_time' => '10:30', 'end_time' => '11:05'],
            ['name' => 'Periods 4-6', 'start_time' => '11:05', 'end_time' => '12:50'],
            ['name' => 'Second Break', 'start_time' => '12:50', 'end_time' => '13:25'],
            ['name' => 'Periods 7-9', 'start_time' => '13:25', 'end_time' => '15:00'],
        ];

        $senior = [
            ['name' => 'Pastoral Care', 'start_time' => '08:37', 'end_time' => '08:45'],
            ['name' => 'Period 1', 'start_time' => '08:45', 'end_time' => '09:58'],
            ['name' => 'Period 2', 'start_time' => '09:58', 'end_time' => '11:08'],
            ['name' => 'Break', 'start_time' => '11:08', 'end_time' => '11:40'],
            ['name' => 'Period 3', 'start_time' => '11:40', 'end_time' => '12:50'],
            ['name' => 'Lunch', 'start_time' => '12:50', 'end_time' => '14:00'],
            ['name' => 'Period 4', 'start_time' => '14:00', 'end_time' => '15:10'],
        ];

        foreach (['Junior School' => $junior, 'Senior School' => $senior] as $group => $periods) {
            foreach ($periods as $i => $period) {
                SchedulePeriod::firstOrCreate(
                    ['group_name' => $group, 'name' => $period['name']],
                    ['start_time' => $period['start_time'], 'end_time' => $period['end_time'], 'display_order' => $i]
                );
            }
        }
    }
}
