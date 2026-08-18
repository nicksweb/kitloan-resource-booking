<?php

namespace Database\Factories;

use App\Models\ResourcePool;
use Illuminate\Database\Eloquent\Factories\Factory;

class ResourceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'resource_pool_id' => ResourcePool::factory(),
            'name' => 'Laptop '.$this->faker->unique()->numberBetween(1, 9999),
            'status' => 'available',
            'source' => 'manual',
            'display_order' => 0,
        ];
    }
}
