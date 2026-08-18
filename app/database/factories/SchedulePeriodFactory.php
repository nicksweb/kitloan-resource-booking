<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class SchedulePeriodFactory extends Factory
{
    public function definition(): array
    {
        return [
            'group_name' => 'Test School',
            'name' => 'Period '.$this->faker->unique()->numberBetween(1, 99),
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'enabled' => true,
            'display_order' => 0,
        ];
    }
}
