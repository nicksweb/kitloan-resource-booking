<?php

namespace Database\Seeders;

use App\Models\BookingType;
use App\Models\Location;
use App\Models\Resource;
use App\Models\ResourcePool;
use Illuminate\Database\Seeder;

/**
 * Development/demo data only. Never called automatically in production —
 * see DatabaseSeeder.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $laptops = ResourcePool::firstOrCreate(
            ['slug' => 'exam-laptops'],
            [
                'name' => 'Exam Laptops',
                'description' => 'Laptops prepared for student examinations.',
                'icon' => 'laptop',
                'display_order' => 1,
                'allocation_mode' => 'individual',
                'minimum_lead_time_minutes' => 30,
                'preparation_buffer_minutes' => 15,
                'return_buffer_minutes' => 15,
                'allow_weekends' => false,
                'allow_out_of_hours' => false,
                'requires_room' => true,
                'allows_student' => true,
                'requires_student' => false,
                'requires_booking_type' => true,
                'auto_approval_enabled' => true,
                'booking_reference_prefix' => 'EX',
            ]
        );

        for ($i = 1; $i <= 20; $i++) {
            Resource::firstOrCreate(
                ['resource_pool_id' => $laptops->id, 'name' => sprintf('Exam Laptop %02d', $i)],
                ['status' => 'available', 'source' => 'manual', 'display_order' => $i]
            );
        }

        $chargers = ResourcePool::firstOrCreate(
            ['slug' => 'chargers'],
            [
                'name' => 'Chargers',
                'description' => 'USB-C chargers — untracked, quantity only.',
                'icon' => 'bolt',
                'display_order' => 2,
                'allocation_mode' => 'quantity',
                'quantity_total' => 30,
                'minimum_lead_time_minutes' => 0,
                'preparation_buffer_minutes' => 0,
                'return_buffer_minutes' => 0,
                'allow_weekends' => true,
                'allow_out_of_hours' => true,
                'requires_room' => false,
                'allows_student' => false,
                'requires_student' => false,
                'requires_booking_type' => false,
                'auto_approval_enabled' => true,
                'booking_reference_prefix' => 'CH',
            ]
        );

        foreach ([
            ['code' => 'B12', 'name' => 'B Block Room 12', 'campus' => 'Main Campus', 'building' => 'B Block'],
            ['code' => 'B14', 'name' => 'B Block Room 14', 'campus' => 'Main Campus', 'building' => 'B Block'],
            ['code' => 'C05', 'name' => 'C Block Room 05', 'campus' => 'Main Campus', 'building' => 'C Block'],
            ['code' => 'C07', 'name' => 'C Block Room 07', 'campus' => 'Main Campus', 'building' => 'C Block'],
            ['code' => 'LIB-1', 'name' => 'Library Room 1', 'campus' => 'Main Campus', 'building' => 'Library'],
        ] as $i => $location) {
            Location::firstOrCreate(['code' => $location['code']], $location + ['display_order' => $i]);
        }

        foreach ([
            ['name' => 'PDF', 'requires_approval' => false],
            ['name' => 'Microsoft Word', 'requires_approval' => false],
            ['name' => 'Safe Exam Browser', 'requires_approval' => false, 'instructions_for_it' => 'Ensure SEB is installed and validate the required configuration before deployment.'],
            ['name' => 'Locked Down', 'requires_approval' => true],
            ['name' => 'Online Assessment', 'requires_approval' => false],
            ['name' => 'Web Browser', 'requires_approval' => false],
            ['name' => 'Other', 'requires_approval' => true],
        ] as $i => $type) {
            BookingType::firstOrCreate(['name' => $type['name']], $type + ['display_order' => $i]);
        }
    }
}
