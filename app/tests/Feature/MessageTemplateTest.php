<?php

namespace Tests\Feature;

use App\Livewire\Admin\MessageTemplatesIndex;
use App\Mail\BookingNotificationMail;
use App\Models\Booking;
use App\Models\MessageTemplate;
use App\Models\ResourcePool;
use App\Models\User;
use App\Services\Booking\BookingService;
use App\Services\Config\ConfigTransferService;
use App\Services\Notifications\TemplateRenderer;
use Database\Seeders\MessageTemplateSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class MessageTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(MessageTemplateSeeder::class);
    }

    private function makeBooking(): Booking
    {
        $pool = ResourcePool::factory()->quantityTracked(10)->create(['requires_room' => false]);
        $owner = User::factory()->create();

        return app(BookingService::class)->create([
            'resource_pool_id' => $pool->id, 'location_id' => null, 'booking_type_id' => null,
            'start_at' => now()->addDays(5)->setTime(10, 0), 'end_at' => now()->addDays(5)->setTime(11, 0),
            'notes' => null, 'students' => [],
            'items' => [['resource_pool_id' => $pool->id, 'quantity' => 2, 'resource_ids' => null]],
        ], $owner, $owner);
    }

    public function test_the_policy_notice_appears_in_a_requestor_email(): void
    {
        MessageTemplate::where('key', 'booking.policy_notice')
            ->update(['intro' => 'Bring every laptop back to the IT office.', 'enabled' => true]);

        Mail::fake();
        $booking = $this->makeBooking();

        Mail::assertQueued(BookingNotificationMail::class, function (BookingNotificationMail $mail) use ($booking) {
            return $mail->booking->is($booking)
                && str_contains($mail->render(), 'Bring every laptop back to the IT office.');
        });
    }

    public function test_disabling_the_policy_notice_removes_it(): void
    {
        MessageTemplate::where('key', 'booking.policy_notice')
            ->update(['intro' => 'Return everything to IT.', 'enabled' => false]);

        Mail::fake();
        $this->makeBooking();

        Mail::assertQueued(BookingNotificationMail::class, fn (BookingNotificationMail $mail) => ! str_contains($mail->render(), 'Return everything to IT.'));
    }

    public function test_subject_tokens_are_substituted(): void
    {
        // Independent of whether the booking auto-approves or lands pending.
        MessageTemplate::whereIn('key', ['booking.owner_submitted', 'booking.owner_approved'])
            ->update(['subject' => 'New request {{ reference }} for {{ pool }}']);

        Mail::fake();
        $booking = $this->makeBooking();

        Mail::assertQueued(BookingNotificationMail::class, function (BookingNotificationMail $mail) use ($booking) {
            return $mail->envelope()->subject === "New request {$booking->reference} for {$booking->resourcePool->name}";
        });
    }

    public function test_unknown_tokens_are_left_untouched_not_blanked(): void
    {
        MessageTemplate::where('key', 'booking.owner_submitted')->update(['subject' => 'Hi {{ made_up }} / {{ reference }}']);

        $subject = app(TemplateRenderer::class)->subject('booking.owner_submitted', ['reference' => 'BK-1'], 'fallback');

        $this->assertSame('Hi {{ made_up }} / BK-1', $subject);
    }

    public function test_reset_to_default_restores_the_seed_wording(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrator');
        MessageTemplate::where('key', 'booking.owner_approved')->update(['subject' => 'Changed', 'intro' => 'Changed']);

        Livewire::actingAs($admin)
            ->test(MessageTemplatesIndex::class)
            ->call('resetToDefault', 'booking.owner_approved');

        $default = MessageTemplateSeeder::defaults()['booking.owner_approved'];
        $this->assertSame($default['subject'], MessageTemplate::where('key', 'booking.owner_approved')->value('subject'));
    }

    public function test_templates_round_trip_through_config_transfer(): void
    {
        MessageTemplate::where('key', 'booking.policy_notice')->update(['intro' => 'Original notice']);
        $transfer = app(ConfigTransferService::class);
        $bundle = $transfer->export(['message_templates']);

        MessageTemplate::where('key', 'booking.policy_notice')->update(['intro' => 'Overwritten later']);
        $transfer->import($bundle, ['message_templates']);

        $this->assertSame('Original notice', MessageTemplate::where('key', 'booking.policy_notice')->value('intro'));
    }
}
