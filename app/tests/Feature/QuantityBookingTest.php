<?php

namespace Tests\Feature;

use App\Exceptions\BookingConflictException;
use App\Models\ResourcePool;
use App\Models\User;
use App\Services\Booking\AvailabilityService;
use App\Services\Booking\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuantityBookingTest extends TestCase
{
    use RefreshDatabase;

    private function bookQuantity(BookingService $service, ResourcePool $pool, User $user, int $quantity)
    {
        return $service->create([
            'resource_pool_id' => $pool->id,
            'location_id' => null,
            'booking_type_id' => null,
            'start_at' => now()->addDay()->setTime(10, 0),
            'end_at' => now()->addDay()->setTime(11, 0),
            'notes' => null,
            'students' => [],
            'items' => [['resource_pool_id' => $pool->id, 'quantity' => $quantity, 'resource_ids' => null]],
        ], $user, $user);
    }

    public function test_available_quantity_is_calculated_correctly(): void
    {
        $pool = ResourcePool::factory()->quantityTracked(30)->create();
        $user = User::factory()->create();
        $service = app(BookingService::class);

        $this->bookQuantity($service, $pool, $user, 12);

        $remaining = app(AvailabilityService::class)->availableQuantity(
            $pool, now()->addDay()->setTime(10, 0), now()->addDay()->setTime(11, 0)
        );

        $this->assertSame(18, $remaining);
    }

    public function test_allocation_does_not_exceed_availability(): void
    {
        $pool = ResourcePool::factory()->quantityTracked(10)->create();
        $user = User::factory()->create();
        $service = app(BookingService::class);

        $this->bookQuantity($service, $pool, $user, 8);

        $this->expectException(BookingConflictException::class);
        $this->bookQuantity($service, $pool, $user, 3); // only 2 remain
    }

    public function test_non_overlapping_quantity_bookings_do_not_compete(): void
    {
        $pool = ResourcePool::factory()->quantityTracked(10)->create();
        $user = User::factory()->create();
        $service = app(BookingService::class);

        $this->bookQuantity($service, $pool, $user, 8);

        $remainingNextDay = app(AvailabilityService::class)->availableQuantity(
            $pool, now()->addDays(2)->setTime(10, 0), now()->addDays(2)->setTime(11, 0)
        );

        $this->assertSame(10, $remainingNextDay);
    }
}
