<?php

namespace Tests\Feature;

use App\Models\ResourcePool;
use App\Models\User;
use App\Services\Booking\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PublicBookingViewTest extends TestCase
{
    use RefreshDatabase;

    private function makeBooking()
    {
        $pool = ResourcePool::factory()->quantityTracked(5)->create(['minimum_lead_time_minutes' => 0]);
        $owner = User::factory()->create();

        return app(BookingService::class)->create([
            'resource_pool_id' => $pool->id, 'location_id' => null, 'booking_type_id' => null,
            'start_at' => now()->addDay(), 'end_at' => now()->addDay()->addHour(),
            'notes' => null, 'students' => [],
            'items' => [['resource_pool_id' => $pool->id, 'quantity' => 1, 'resource_ids' => null]],
        ], $owner, $owner);
    }

    public function test_a_valid_signed_link_shows_the_booking_without_authentication(): void
    {
        $booking = $this->makeBooking();
        $url = URL::temporarySignedRoute('bookings.public-view', now()->addDays(30), ['booking' => $booking->reference]);

        $this->get($url)->assertOk()->assertSee($booking->reference);
        $this->assertGuest();
    }

    public function test_an_unsigned_url_to_the_same_path_is_rejected(): void
    {
        $booking = $this->makeBooking();

        $this->get(route('bookings.public-view', $booking))->assertForbidden();
    }

    public function test_an_expired_signed_link_is_rejected(): void
    {
        $booking = $this->makeBooking();
        $url = URL::temporarySignedRoute('bookings.public-view', now()->subMinute(), ['booking' => $booking->reference]);

        $this->get($url)->assertForbidden();
    }

    public function test_tampering_with_the_booking_reference_invalidates_the_signature(): void
    {
        $booking = $this->makeBooking();
        $otherBooking = $this->makeBooking();

        $url = URL::temporarySignedRoute('bookings.public-view', now()->addDays(30), ['booking' => $booking->reference]);
        $tamperedUrl = str_replace($booking->reference, $otherBooking->reference, $url);

        $this->get($tamperedUrl)->assertForbidden();
    }
}
