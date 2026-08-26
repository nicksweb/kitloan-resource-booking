<?php

namespace Tests\Feature;

use App\Exceptions\BookingConflictException;
use App\Mail\BookingAmendedMail;
use App\Mail\BookingApprovalRequestMail;
use App\Mail\BookingNotificationMail;
use App\Models\Location;
use App\Models\Resource;
use App\Models\ResourcePool;
use App\Models\User;
use App\Services\Booking\BookingService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BookingAmendmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function book(BookingService $service, ResourcePool $pool, User $user, array $resourceIds, $start = null, $end = null)
    {
        return $service->create([
            'resource_pool_id' => $pool->id, 'location_id' => null, 'booking_type_id' => null,
            'start_at' => $start ?? now()->addDay()->setTime(10, 0),
            'end_at' => $end ?? now()->addDay()->setTime(11, 0),
            'notes' => null, 'students' => [],
            'items' => [['resource_pool_id' => $pool->id, 'quantity' => count($resourceIds), 'resource_ids' => $resourceIds]],
        ], $user, $user);
    }

    public function test_amending_without_changing_anything_does_not_falsely_conflict_with_itself(): void
    {
        $pool = ResourcePool::factory()->create();
        $laptop = Resource::factory()->create(['resource_pool_id' => $pool->id]);
        $owner = User::factory()->create();
        $service = app(BookingService::class);

        $booking = $this->book($service, $pool, $owner, [$laptop->id]);

        $updated = $service->update($booking, [
            'resource_pool_id' => $pool->id, 'location_id' => null, 'booking_type_id' => null,
            'start_at' => $booking->start_at, 'end_at' => $booking->end_at,
            'notes' => null, 'students' => [],
            'items' => [['resource_pool_id' => $pool->id, 'quantity' => 1, 'resource_ids' => [$laptop->id]]],
        ], $owner);

        $this->assertSame($booking->id, $updated->id);
        $this->assertSame($laptop->id, $updated->items->first()->allocations->first()->resource_id);
    }

    public function test_amending_releases_old_allocations_and_creates_new_ones(): void
    {
        $pool = ResourcePool::factory()->create();
        $laptopA = Resource::factory()->create(['resource_pool_id' => $pool->id]);
        $laptopB = Resource::factory()->create(['resource_pool_id' => $pool->id]);
        $owner = User::factory()->create();
        $service = app(BookingService::class);

        $booking = $this->book($service, $pool, $owner, [$laptopA->id]);

        $updated = $service->update($booking, [
            'resource_pool_id' => $pool->id, 'location_id' => null, 'booking_type_id' => null,
            'start_at' => $booking->start_at, 'end_at' => $booking->end_at,
            'notes' => null, 'students' => [],
            'items' => [['resource_pool_id' => $pool->id, 'quantity' => 1, 'resource_ids' => [$laptopB->id]]],
        ], $owner);

        $activeAllocations = $updated->items->first()->allocations->whereNull('released_at');
        $this->assertCount(1, $activeAllocations);
        $this->assertSame($laptopB->id, $activeAllocations->first()->resource_id);

        // laptopA must now be free again for someone else at that same time.
        $freeAgain = app(\App\Services\Booking\AvailabilityService::class)
            ->isResourceAvailable($laptopA, $pool, $booking->start_at, $booking->end_at);
        $this->assertTrue($freeAgain);
    }

    public function test_amending_into_a_genuinely_conflicting_slot_still_fails(): void
    {
        $pool = ResourcePool::factory()->create();
        $laptop = Resource::factory()->create(['resource_pool_id' => $pool->id]);
        $owner = User::factory()->create();
        $service = app(BookingService::class);

        // A different, unrelated booking occupies 14:00-15:00 on the laptop.
        $blocker = $this->book($service, $pool, $owner, [$laptop->id], now()->addDay()->setTime(14, 0), now()->addDay()->setTime(15, 0));

        // This booking starts at 10:00 on a *different* laptop initially...
        $otherLaptop = Resource::factory()->create(['resource_pool_id' => $pool->id]);
        $booking = $this->book($service, $pool, $owner, [$otherLaptop->id]);

        // ...then tries to move onto the already-blocked laptop at the blocked time.
        $this->expectException(BookingConflictException::class);
        $service->update($booking, [
            'resource_pool_id' => $pool->id, 'location_id' => null, 'booking_type_id' => null,
            'start_at' => $blocker->start_at, 'end_at' => $blocker->end_at,
            'notes' => null, 'students' => [],
            'items' => [['resource_pool_id' => $pool->id, 'quantity' => 1, 'resource_ids' => [$laptop->id]]],
        ], $owner);
    }

    public function test_it_operator_amendment_is_auto_approved_regardless_of_lead_time(): void
    {
        $pool = ResourcePool::factory()->create(['minimum_lead_time_minutes' => 0]);
        $laptop = Resource::factory()->create(['resource_pool_id' => $pool->id]);
        $owner = User::factory()->create();
        $operator = User::factory()->create();
        $operator->assignRole('it_operator');
        $service = app(BookingService::class);

        $booking = $this->book($service, $pool, $owner, [$laptop->id]);

        // Move it to just 30 minutes from now — well under the normal auto-approval threshold.
        $updated = $service->update($booking, [
            'resource_pool_id' => $pool->id, 'location_id' => null, 'booking_type_id' => null,
            'start_at' => now()->addMinutes(30), 'end_at' => now()->addMinutes(90),
            'notes' => null, 'students' => [],
            'items' => [['resource_pool_id' => $pool->id, 'quantity' => 1, 'resource_ids' => [$laptop->id]]],
        ], $operator);

        $this->assertSame('approved', $updated->approval_status);
    }

    public function test_owner_amendment_that_moves_inside_the_lead_time_reverts_to_pending(): void
    {
        $pool = ResourcePool::factory()->create(['minimum_lead_time_minutes' => 0]);
        $laptop = Resource::factory()->create(['resource_pool_id' => $pool->id]);
        $owner = User::factory()->create();
        $service = app(BookingService::class);

        $booking = $this->book($service, $pool, $owner, [$laptop->id]);
        $this->assertSame('approved', $booking->approval_status);

        $updated = $service->update($booking, [
            'resource_pool_id' => $pool->id, 'location_id' => null, 'booking_type_id' => null,
            'start_at' => now()->addMinutes(30), 'end_at' => now()->addMinutes(90),
            'notes' => null, 'students' => [],
            'items' => [['resource_pool_id' => $pool->id, 'quantity' => 1, 'resource_ids' => [$laptop->id]]],
        ], $owner);

        $this->assertSame('pending', $updated->approval_status);
    }

    public function test_room_and_exam_type_can_be_changed(): void
    {
        $pool = ResourcePool::factory()->create();
        $laptop = Resource::factory()->create(['resource_pool_id' => $pool->id]);
        $owner = User::factory()->create();
        $newRoom = Location::factory()->create();
        $service = app(BookingService::class);

        $booking = $this->book($service, $pool, $owner, [$laptop->id]);

        $updated = $service->update($booking, [
            'resource_pool_id' => $pool->id, 'location_id' => $newRoom->id, 'booking_type_id' => null,
            'start_at' => $booking->start_at, 'end_at' => $booking->end_at,
            'notes' => 'Room changed', 'students' => [],
            'items' => [['resource_pool_id' => $pool->id, 'quantity' => 1, 'resource_ids' => [$laptop->id]]],
        ], $owner);

        $this->assertSame($newRoom->id, $updated->location_id);
        $this->assertSame('Room changed', $updated->notes);
    }

    public function test_a_stranger_cannot_amend_someone_elses_booking(): void
    {
        $pool = ResourcePool::factory()->create();
        $laptop = Resource::factory()->create(['resource_pool_id' => $pool->id]);
        $owner = User::factory()->create();
        $owner->assignRole('user');
        $stranger = User::factory()->create();
        $stranger->assignRole('user');
        $service = app(BookingService::class);

        $booking = $this->book($service, $pool, $owner, [$laptop->id]);

        $this->actingAs($stranger)->get(route('bookings.edit', $booking))->assertForbidden();
    }

    public function test_amending_the_quantity_while_staying_approved_notifies_it_and_owner(): void
    {
        app(\App\Settings\SettingsRepository::class)->set('it_notification_address', 'it@example.com');

        $pool = ResourcePool::factory()->quantityTracked(10)->create();
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $admin->assignRole('administrator');
        $service = app(BookingService::class);

        $booking = $service->create([
            'resource_pool_id' => $pool->id, 'location_id' => null, 'booking_type_id' => null,
            'start_at' => now()->addDay(), 'end_at' => now()->addDay()->addHour(),
            'notes' => null, 'students' => [],
            'items' => [['resource_pool_id' => $pool->id, 'quantity' => 6, 'resource_ids' => null]],
        ], $owner, $owner);

        // Fake only from here — create() itself already sent its own
        // notifications, which aren't what this test is checking.
        Mail::fake();

        $service->update($booking, [
            'resource_pool_id' => $pool->id, 'location_id' => null, 'booking_type_id' => null,
            'start_at' => $booking->start_at, 'end_at' => $booking->end_at,
            'notes' => null, 'students' => [],
            'items' => [['resource_pool_id' => $pool->id, 'quantity' => 8, 'resource_ids' => null]],
        ], $admin);

        Mail::assertQueued(BookingAmendedMail::class, function ($mail) {
            return $mail->hasTo('it@example.com')
                && collect($mail->changes)->contains(fn ($c) => str_contains($c, 'quantity changed from 6 to 8'));
        });
        Mail::assertQueued(BookingNotificationMail::class, fn ($mail) => $mail->hasTo($owner->email) && $mail->changes !== []);
        Mail::assertNotQueued(BookingApprovalRequestMail::class);
    }

    public function test_amending_into_the_lead_time_window_sends_it_a_re_approval_request(): void
    {
        app(\App\Settings\SettingsRepository::class)->set('it_notification_address', 'it@example.com');

        $pool = ResourcePool::factory()->create(['minimum_lead_time_minutes' => 0]);
        $laptop = Resource::factory()->create(['resource_pool_id' => $pool->id]);
        $owner = User::factory()->create();
        $service = app(BookingService::class);

        $booking = $this->book($service, $pool, $owner, [$laptop->id]);
        Mail::fake();

        $service->update($booking, [
            'resource_pool_id' => $pool->id, 'location_id' => null, 'booking_type_id' => null,
            'start_at' => now()->addMinutes(30), 'end_at' => now()->addMinutes(90),
            'notes' => null, 'students' => [],
            'items' => [['resource_pool_id' => $pool->id, 'quantity' => 1, 'resource_ids' => [$laptop->id]]],
        ], $owner);

        Mail::assertQueued(BookingApprovalRequestMail::class, fn ($mail) => $mail->hasTo('it@example.com') && $mail->changes !== []);
        Mail::assertNotQueued(BookingAmendedMail::class);
    }

    /** A weekday, well inside school hours and past every auto-approval threshold. */
    private function safeWindow(): array
    {
        $start = now()->addDays(3)->setTime(10, 0);
        while ($start->isWeekend()) {
            $start->addDay();
        }

        return [$start->copy(), $start->copy()->addHour()];
    }

    public function test_owner_increasing_quantity_on_an_approved_booking_reverts_to_pending(): void
    {
        $pool = ResourcePool::factory()->quantityTracked(20)->create(['minimum_lead_time_minutes' => 0]);
        $owner = User::factory()->create();
        $service = app(BookingService::class);
        [$start, $end] = $this->safeWindow();

        $booking = $service->create([
            'resource_pool_id' => $pool->id, 'location_id' => null, 'booking_type_id' => null,
            'start_at' => $start, 'end_at' => $end,
            'notes' => null, 'students' => [],
            'items' => [['resource_pool_id' => $pool->id, 'quantity' => 4, 'resource_ids' => null]],
        ], $owner, $owner);
        $this->assertSame('approved', $booking->approval_status);

        $updated = $service->update($booking, [
            'resource_pool_id' => $pool->id, 'location_id' => null, 'booking_type_id' => null,
            'start_at' => $booking->start_at, 'end_at' => $booking->end_at,
            'notes' => null, 'students' => [],
            'items' => [['resource_pool_id' => $pool->id, 'quantity' => 9, 'resource_ids' => null]],
        ], $owner);

        $this->assertSame('pending', $updated->approval_status);
    }

    public function test_owner_reducing_quantity_on_an_approved_booking_stays_approved(): void
    {
        $pool = ResourcePool::factory()->quantityTracked(20)->create(['minimum_lead_time_minutes' => 0]);
        $owner = User::factory()->create();
        $service = app(BookingService::class);
        [$start, $end] = $this->safeWindow();

        $booking = $service->create([
            'resource_pool_id' => $pool->id, 'location_id' => null, 'booking_type_id' => null,
            'start_at' => $start, 'end_at' => $end,
            'notes' => null, 'students' => [],
            'items' => [['resource_pool_id' => $pool->id, 'quantity' => 9, 'resource_ids' => null]],
        ], $owner, $owner);
        $this->assertSame('approved', $booking->approval_status);

        $updated = $service->update($booking, [
            'resource_pool_id' => $pool->id, 'location_id' => null, 'booking_type_id' => null,
            'start_at' => $booking->start_at, 'end_at' => $booking->end_at,
            'notes' => null, 'students' => [],
            'items' => [['resource_pool_id' => $pool->id, 'quantity' => 4, 'resource_ids' => null]],
        ], $owner);

        $this->assertSame('approved', $updated->approval_status);
    }

    public function test_saving_with_no_actual_changes_sends_no_notifications(): void
    {
        app(\App\Settings\SettingsRepository::class)->set('it_notification_address', 'it@example.com');

        $pool = ResourcePool::factory()->create();
        $laptop = Resource::factory()->create(['resource_pool_id' => $pool->id]);
        $owner = User::factory()->create();
        $service = app(BookingService::class);

        $booking = $this->book($service, $pool, $owner, [$laptop->id]);
        Mail::fake();

        $service->update($booking, [
            'resource_pool_id' => $pool->id, 'location_id' => null, 'booking_type_id' => null,
            'start_at' => $booking->start_at, 'end_at' => $booking->end_at,
            'notes' => null, 'students' => [],
            'items' => [['resource_pool_id' => $pool->id, 'quantity' => 1, 'resource_ids' => [$laptop->id]]],
        ], $owner);

        Mail::assertNothingQueued();
    }
}
