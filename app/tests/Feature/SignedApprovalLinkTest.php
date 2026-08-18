<?php

namespace Tests\Feature;

use App\Models\ResourcePool;
use App\Models\User;
use App\Services\Booking\BookingService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class SignedApprovalLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function pendingBooking(): \App\Models\Booking
    {
        $pool = ResourcePool::factory()->quantityTracked(5)->create(['minimum_lead_time_minutes' => 0]);
        $owner = User::factory()->create();

        return app(BookingService::class)->create([
            'resource_pool_id' => $pool->id, 'location_id' => null, 'booking_type_id' => null,
            'start_at' => now()->addHour(), 'end_at' => now()->addHours(2),
            'notes' => null, 'students' => [],
            'items' => [['resource_pool_id' => $pool->id, 'quantity' => 1, 'resource_ids' => null]],
        ], $owner, $owner);
    }

    public function test_an_expired_signed_approval_link_is_rejected(): void
    {
        $booking = $this->pendingBooking();
        $operator = User::factory()->create();
        $operator->assignRole('it_operator');

        $expiredUrl = URL::temporarySignedRoute('bookings.approve', now()->subMinute(), ['booking' => $booking->reference]);

        $this->actingAs($operator)->get($expiredUrl)->assertForbidden();
        $this->assertSame('pending', $booking->fresh()->approval_status);
    }

    public function test_a_valid_signed_link_still_requires_the_visitor_to_be_authenticated_as_it_staff(): void
    {
        $booking = $this->pendingBooking();
        $regularUser = User::factory()->create();
        $regularUser->assignRole('user');

        $validUrl = URL::temporarySignedRoute('bookings.approve', now()->addDay(), ['booking' => $booking->reference]);

        // Signature is valid, but this visitor isn't IT/admin — must still be refused.
        $this->actingAs($regularUser)->get($validUrl)->assertForbidden();
        $this->assertSame('pending', $booking->fresh()->approval_status);
    }

    public function test_a_valid_signed_link_used_by_it_staff_approves_the_booking(): void
    {
        $booking = $this->pendingBooking();
        $operator = User::factory()->create();
        $operator->assignRole('it_operator');

        $validUrl = URL::temporarySignedRoute('bookings.approve', now()->addDay(), ['booking' => $booking->reference]);

        $this->actingAs($operator)->get($validUrl)->assertRedirect();
        $this->assertSame('approved', $booking->fresh()->approval_status);
    }
}
