<?php

namespace Tests\Feature;

use App\Livewire\BookingDetail;
use App\Mail\BookingNotificationMail;
use App\Models\Booking;
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
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class BookingReassignTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(MessageTemplateSeeder::class);
        app(SettingsRepository::class)->set('it_notification_address', 'it@example.com');
    }

    private function booking(User $owner): Booking
    {
        $pool = ResourcePool::factory()->create(['minimum_lead_time_minutes' => 0]);
        $laptop = Resource::factory()->create(['resource_pool_id' => $pool->id]);

        return app(BookingService::class)->create([
            'resource_pool_id' => $pool->id, 'location_id' => null, 'booking_type_id' => null,
            'start_at' => now()->addDays(2)->setTime(10, 0), 'end_at' => now()->addDays(2)->setTime(11, 0),
            'notes' => null, 'students' => [],
            'items' => [['resource_pool_id' => $pool->id, 'quantity' => 1, 'resource_ids' => [$laptop->id]]],
        ], $owner, $owner);
    }

    public function test_reassign_moves_the_booking_notifies_both_parties_and_audits(): void
    {
        $alice = User::factory()->create(['name' => 'Alice', 'email' => 'alice@example.com']);
        $bob = User::factory()->create(['name' => 'Bob', 'email' => 'bob@example.com']);
        $operator = User::factory()->create();
        $operator->assignRole('it_operator');

        $booking = $this->booking($alice);
        Mail::fake();

        app(BookingService::class)->reassign($booking, $bob, $operator);

        $this->assertSame($bob->id, $booking->fresh()->booked_by_user_id);
        $this->assertDatabaseHas('audit_events', ['event_type' => 'booking.reassigned']);

        Mail::assertQueued(BookingNotificationMail::class, fn ($m) => $m->kind === 'reassigned_to' && $m->hasTo('bob@example.com'));
        Mail::assertQueued(BookingNotificationMail::class, fn ($m) => $m->kind === 'reassigned_away' && $m->hasTo('alice@example.com'));
    }

    public function test_a_plain_user_cannot_reassign(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $stranger->assignRole('user');

        $this->expectException(HttpException::class);
        app(BookingService::class)->reassign($this->booking($owner), User::factory()->create(), $stranger);
    }

    public function test_the_booking_detail_reassign_action_works(): void
    {
        $owner = User::factory()->create();
        $target = User::factory()->create(['name' => 'New Owner']);
        $operator = User::factory()->create();
        $operator->assignRole('it_operator');

        $booking = $this->booking($owner);

        Livewire::actingAs($operator)
            ->test(BookingDetail::class, ['booking' => $booking])
            ->set('reassignUserId', $target->id)
            ->call('reassign')
            ->assertHasNoErrors();

        $this->assertSame($target->id, $booking->fresh()->booked_by_user_id);
    }
}
