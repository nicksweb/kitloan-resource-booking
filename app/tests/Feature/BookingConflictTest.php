<?php

namespace Tests\Feature;

use App\Exceptions\BookingConflictException;
use App\Models\Resource;
use App\Models\ResourcePool;
use App\Models\User;
use App\Services\Booking\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BookingConflictTest extends TestCase
{
    use RefreshDatabase;

    private function makeBooking(BookingService $service, ResourcePool $pool, User $user, Carbon $start, Carbon $end, array $resourceIds)
    {
        return $service->create([
            'resource_pool_id' => $pool->id,
            'location_id' => null,
            'booking_type_id' => null,
            'start_at' => $start,
            'end_at' => $end,
            'notes' => null,
            'students' => [],
            'items' => [['resource_pool_id' => $pool->id, 'quantity' => count($resourceIds), 'resource_ids' => $resourceIds]],
        ], $user, $user);
    }

    public function test_the_same_asset_cannot_be_booked_twice_for_overlapping_times(): void
    {
        $pool = ResourcePool::factory()->create();
        $laptop = Resource::factory()->create(['resource_pool_id' => $pool->id]);
        $user = User::factory()->create();
        $service = app(BookingService::class);

        $this->makeBooking($service, $pool, $user, now()->addDay()->setTime(10, 0), now()->addDay()->setTime(11, 0), [$laptop->id]);

        $this->expectException(BookingConflictException::class);
        $this->makeBooking($service, $pool, $user, now()->addDay()->setTime(10, 30), now()->addDay()->setTime(11, 30), [$laptop->id]);
    }

    public function test_back_to_back_bookings_with_no_buffer_do_not_conflict(): void
    {
        $pool = ResourcePool::factory()->create(['preparation_buffer_minutes' => 0, 'return_buffer_minutes' => 0]);
        $laptop = Resource::factory()->create(['resource_pool_id' => $pool->id]);
        $user = User::factory()->create();
        $service = app(BookingService::class);

        $this->makeBooking($service, $pool, $user, now()->addDay()->setTime(10, 0), now()->addDay()->setTime(11, 0), [$laptop->id]);

        // Starts exactly when the previous one ends — must be allowed.
        $booking = $this->makeBooking($service, $pool, $user, now()->addDay()->setTime(11, 0), now()->addDay()->setTime(12, 0), [$laptop->id]);

        $this->assertNotNull($booking->id);
    }

    public function test_preparation_and_return_buffers_block_adjacent_bookings(): void
    {
        $pool = ResourcePool::factory()->create(['preparation_buffer_minutes' => 15, 'return_buffer_minutes' => 15]);
        $laptop = Resource::factory()->create(['resource_pool_id' => $pool->id]);
        $user = User::factory()->create();
        $service = app(BookingService::class);

        $this->makeBooking($service, $pool, $user, now()->addDay()->setTime(10, 0), now()->addDay()->setTime(11, 0), [$laptop->id]);

        // Would start only 10 minutes after the first ends — inside the 15 minute return buffer.
        $this->expectException(BookingConflictException::class);
        $this->makeBooking($service, $pool, $user, now()->addDay()->setTime(11, 10), now()->addDay()->setTime(12, 0), [$laptop->id]);
    }

    public function test_booking_outside_the_buffer_window_is_allowed(): void
    {
        $pool = ResourcePool::factory()->create(['preparation_buffer_minutes' => 15, 'return_buffer_minutes' => 15]);
        $laptop = Resource::factory()->create(['resource_pool_id' => $pool->id]);
        $user = User::factory()->create();
        $service = app(BookingService::class);

        $this->makeBooking($service, $pool, $user, now()->addDay()->setTime(10, 0), now()->addDay()->setTime(11, 0), [$laptop->id]);

        // Each booking carries its own prep+return buffer, so the free gap
        // between two bookings on the same resource is return + prep = 30min
        // (11:00 return buffer -> 11:15, then the next booking's own 15min
        // prep buffer pushes its earliest legal start to 11:30).
        $booking = $this->makeBooking($service, $pool, $user, now()->addDay()->setTime(11, 30), now()->addDay()->setTime(12, 0), [$laptop->id]);

        $this->assertNotNull($booking->id);
    }

    public function test_cancelling_a_booking_releases_its_resources(): void
    {
        $pool = ResourcePool::factory()->create();
        $laptop = Resource::factory()->create(['resource_pool_id' => $pool->id]);
        $user = User::factory()->create();
        $service = app(BookingService::class);

        $booking = $this->makeBooking($service, $pool, $user, now()->addDay()->setTime(10, 0), now()->addDay()->setTime(11, 0), [$laptop->id]);
        $service->cancel($booking, $user);

        // The same slot should now be bookable again.
        $rebooked = $this->makeBooking($service, $pool, $user, now()->addDay()->setTime(10, 0), now()->addDay()->setTime(11, 0), [$laptop->id]);

        $this->assertNotNull($rebooked->id);
    }
}
