<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class LocationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => 'R'.$this->faker->unique()->numberBetween(1, 9999),
            'name' => 'Room '.$this->faker->word(),
            'enabled' => true,
            'display_order' => 0,
        ];
    }
}
