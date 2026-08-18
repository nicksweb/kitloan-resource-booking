<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ResourcePoolFactory extends Factory
{
    public function definition(): array
    {
        $name = 'Exam Laptops '.Str::random(5);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => 'Test pool',
            'enabled' => true,
            'icon' => 'laptop',
            'display_order' => 1,
            'allocation_mode' => 'individual',
            'quantity_total' => null,
            'minimum_lead_time_minutes' => 0,
            'preparation_buffer_minutes' => 0,
            'return_buffer_minutes' => 0,
            'allow_weekends' => true,
            'allow_out_of_hours' => true,
            'requires_room' => false,
            'allows_student' => true,
            'requires_student' => false,
            'requires_booking_type' => false,
            'auto_approval_enabled' => true,
            'booking_reference_prefix' => 'EX',
        ];
    }

    public function quantityTracked(int $total = 10): static
    {
        return $this->state(fn () => [
            'allocation_mode' => 'quantity',
            'quantity_total' => $total,
        ]);
    }
}
