<?php

namespace Tests\Feature;

use App\Livewire\Booking\BookingWizard;
use App\Mail\BookingAmendedMail;
use App\Models\Booking;
use App\Models\Location;
use App\Models\Resource;
use App\Models\ResourcePool;
use App\Models\User;
use App\Services\Booking\BookingService;
use App\Settings\SettingsRepository;
use Database\Seeders\MessageTemplateSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class RoomChoiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(MessageTemplateSeeder::class);
    }

    public function test_pick_up_choice_saves_without_a_room(): void
    {
        $pool = ResourcePool::factory()->quantityTracked(5)->create(['requires_room' => true]);
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(BookingWizard::class, ['resourcePool' => $pool])
            ->set('roomChoice', 'pickup')
            ->set('quantityRequested', 1)
            ->call('submit')
            ->assertHasNoErrors();

        $booking = Booking::firstOrFail();
        $this->assertNull($booking->location_id);
        $this->assertSame('pickup', $booking->room_choice);
        $this->assertSame('Pick-up from IT', $booking->roomLabel());
    }

    public function test_choosing_room_still_requires_a_location_when_the_pool_needs_one(): void
    {
        $pool = ResourcePool::factory()->quantityTracked(5)->create(['requires_room' => true]);
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(BookingWizard::class, ['resourcePool' => $pool])
            ->set('roomChoice', 'room')
            ->set('quantityRequested', 1)
            ->call('submit')
            ->assertHasErrors('locationId');
    }

    public function test_switching_from_a_room_to_pick_up_shows_up_in_the_change_summary(): void
    {
        app(SettingsRepository::class)->set('it_notification_address', 'it@example.com');

        $pool = ResourcePool::factory()->create(['requires_room' => true, 'minimum_lead_time_minutes' => 0]);
        $laptop = Resource::factory()->create(['resource_pool_id' => $pool->id]);
        $room = Location::factory()->create(['code' => 'A1', 'name' => 'Science Lab']);
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $admin->assignRole('administrator');

        $start = now()->addDays(3)->setTime(10, 0);
        while ($start->isWeekend()) {
            $start->addDay();
        }

        $booking = app(BookingService::class)->create([
            'resource_pool_id' => $pool->id, 'location_id' => $room->id, 'room_choice' => 'room',
            'booking_type_id' => null, 'start_at' => $start, 'end_at' => $start->copy()->addHour(),
            'notes' => null, 'students' => [],
            'items' => [['resource_pool_id' => $pool->id, 'quantity' => 1, 'resource_ids' => [$laptop->id]]],
        ], $owner, $owner);

        Mail::fake();

        app(BookingService::class)->update($booking, [
            'resource_pool_id' => $pool->id, 'location_id' => null, 'room_choice' => 'pickup',
            'booking_type_id' => null, 'start_at' => $booking->start_at, 'end_at' => $booking->end_at,
            'notes' => null, 'students' => [],
            'items' => [['resource_pool_id' => $pool->id, 'quantity' => 1, 'resource_ids' => [$laptop->id]]],
        ], $admin);

        Mail::assertQueued(BookingAmendedMail::class, fn ($mail) => collect($mail->changes)
            ->contains(fn ($c) => str_contains($c, 'Pick-up from IT')));
    }
}
