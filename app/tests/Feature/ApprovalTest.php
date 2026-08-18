<?php

namespace Tests\Feature;

use App\Models\BookingType;
use App\Models\ResourcePool;
use App\Models\User;
use App\Services\Booking\BookingService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class ApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function book(BookingService $service, ResourcePool $pool, User $user, $start, $end, ?BookingType $type = null)
    {
        return $service->create([
            'resource_pool_id' => $pool->id,
            'location_id' => null,
            'booking_type_id' => $type?->id,
            'start_at' => $start,
            'end_at' => $end,
            'notes' => null,
            'students' => [],
            'items' => [['resource_pool_id' => $pool->id, 'quantity' => 1, 'resource_ids' => null]],
        ], $user, $user);
    }

    public function test_booking_at_or_beyond_the_lead_time_auto_approves(): void
    {
        // Freeze to a weekday morning so the +7h booking lands on the same
        // weekday, inside school hours — isolating this test from whatever
        // wall-clock time it happens to run at.
        Carbon::setTestNow(Carbon::parse('next Monday 08:00'));

        $pool = ResourcePool::factory()->quantityTracked(5)->create();
        $user = User::factory()->create();

        $booking = $this->book(app(BookingService::class), $pool, $user, now()->addHours(7), now()->addHours(8));

        $this->assertSame('approved', $booking->approval_status);
        $this->assertTrue($booking->auto_approved);
    }

    public function test_booking_below_the_lead_time_requires_approval(): void
    {
        $pool = ResourcePool::factory()->quantityTracked(5)->create(['minimum_lead_time_minutes' => 0]);
        $user = User::factory()->create();

        $booking = $this->book(app(BookingService::class), $pool, $user, now()->addHours(2), now()->addHours(3));

        $this->assertSame('pending', $booking->approval_status);
        $this->assertFalse($booking->auto_approved);
    }

    public function test_booking_type_that_always_requires_approval_overrides_lead_time(): void
    {
        $pool = ResourcePool::factory()->quantityTracked(5)->create();
        $type = BookingType::factory()->create(['requires_approval' => true]);
        $user = User::factory()->create();

        $booking = $this->book(app(BookingService::class), $pool, $user, now()->addDays(3), now()->addDays(3)->addHour(), $type);

        $this->assertSame('pending', $booking->approval_status);
    }

    public function test_it_operator_can_manually_approve_a_pending_booking(): void
    {
        $pool = ResourcePool::factory()->quantityTracked(5)->create(['minimum_lead_time_minutes' => 0]);
        $owner = User::factory()->create();
        $owner->assignRole('user');
        $operator = User::factory()->create();
        $operator->assignRole('it_operator');

        $booking = $this->book(app(BookingService::class), $pool, $owner, now()->addHour(), now()->addHours(2));
        $this->assertSame('pending', $booking->approval_status);

        $this->actingAs($operator);
        Livewire::test(\App\Livewire\BookingDetail::class, ['booking' => $booking])
            ->call('approve');

        $this->assertSame('approved', $booking->fresh()->approval_status);
    }

    public function test_rejection_requires_a_reason(): void
    {
        $pool = ResourcePool::factory()->quantityTracked(5)->create(['minimum_lead_time_minutes' => 0]);
        $owner = User::factory()->create();
        $operator = User::factory()->create();
        $operator->assignRole('it_operator');

        $booking = $this->book(app(BookingService::class), $pool, $owner, now()->addHour(), now()->addHours(2));

        $this->actingAs($operator);
        Livewire::test(\App\Livewire\BookingDetail::class, ['booking' => $booking])
            ->call('reject', '')
            ->assertHasErrors('reason');

        $this->assertSame('pending', $booking->fresh()->approval_status);
    }
}
