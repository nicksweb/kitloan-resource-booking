<?php

namespace Tests\Feature;

use App\Mail\BookingApprovalRequestMail;
use App\Mail\BookingConfirmedNoticeMail;
use App\Models\Booking;
use App\Models\MessageTemplate;
use App\Models\ResourcePool;
use App\Models\User;
use App\Services\Booking\BookingService;
use App\Services\Notifications\IcsBuilder;
use App\Settings\SettingsRepository;
use Database\Seeders\MessageTemplateSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BookingNotificationRoutingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(MessageTemplateSeeder::class);
        app(SettingsRepository::class)->set('it_notification_address', 'it@example.com');
    }

    private function safeStart(): Carbon
    {
        $start = now()->addDays(3)->setTime(10, 0);
        while ($start->isWeekend()) {
            $start->addDay();
        }

        return $start;
    }

    private function book(Carbon $start, int $lead = 0): Booking
    {
        $pool = ResourcePool::factory()->quantityTracked(10)->create([
            'requires_room' => false, 'minimum_lead_time_minutes' => $lead,
        ]);
        $owner = User::factory()->create();

        return app(BookingService::class)->create([
            'resource_pool_id' => $pool->id, 'location_id' => null, 'booking_type_id' => null,
            'start_at' => $start, 'end_at' => $start->copy()->addHour(),
            'notes' => null, 'students' => [],
            'items' => [['resource_pool_id' => $pool->id, 'quantity' => 2, 'resource_ids' => null]],
        ], $owner, $owner);
    }

    public function test_auto_approved_booking_sends_it_a_confirmed_notice_not_an_approval_request(): void
    {
        Mail::fake();
        $booking = $this->book($this->safeStart());
        $this->assertSame('approved', $booking->approval_status);

        Mail::assertQueued(BookingConfirmedNoticeMail::class, fn ($m) => $m->hasTo('it@example.com'));
        Mail::assertNotQueued(BookingApprovalRequestMail::class);
    }

    public function test_pending_booking_still_sends_it_the_approval_request(): void
    {
        Mail::fake();
        // Starts in ~1 hour — inside the auto-approval lead window, so it's pending.
        $booking = $this->book(now()->addMinutes(75));
        $this->assertSame('pending', $booking->approval_status);

        Mail::assertQueued(BookingApprovalRequestMail::class, fn ($m) => $m->hasTo('it@example.com'));
        Mail::assertNotQueued(BookingConfirmedNoticeMail::class);
    }

    public function test_it_emails_carry_a_calendar_attachment(): void
    {
        Mail::fake();
        $this->book($this->safeStart());              // -> BookingConfirmedNoticeMail
        $this->book(now()->addMinutes(75));           // -> BookingApprovalRequestMail

        foreach ([BookingConfirmedNoticeMail::class, BookingApprovalRequestMail::class] as $class) {
            Mail::assertQueued($class, function ($mail) {
                $attachments = $mail->attachments();

                return count($attachments) === 1 && $attachments[0]->as === 'booking.ics';
            });
        }
    }

    public function test_the_approval_request_calendar_event_is_marked_tentative(): void
    {
        $booking = $this->book(now()->addMinutes(75));

        $this->assertStringContainsString('STATUS:TENTATIVE', app(IcsBuilder::class)->forBooking($booking, true));
        $this->assertStringNotContainsString('STATUS:TENTATIVE', app(IcsBuilder::class)->forBooking($booking, false));
    }

    public function test_disabling_the_it_confirmed_template_suppresses_the_notice(): void
    {
        MessageTemplate::where('key', 'booking.it_confirmed')->update(['enabled' => false]);

        Mail::fake();
        $this->book($this->safeStart());

        Mail::assertNotQueued(BookingConfirmedNoticeMail::class);
    }
}
