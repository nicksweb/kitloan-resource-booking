<?php

namespace Tests\Feature;

use App\Mail\BookingApprovalRequestMail;
use App\Mail\BookingNotificationMail;
use App\Mail\DailySummaryMail;
use App\Models\Booking;
use App\Models\Resource;
use App\Models\ResourcePool;
use App\Models\User;
use App\Services\Booking\BookingService;
use App\Services\Booking\StaffResourceSync;
use App\Settings\SettingsRepository;
use Database\Seeders\MessageTemplateSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class StaffBookingNotificationTest extends TestCase
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

    private function staffPool(array $overrides = []): ResourcePool
    {
        return ResourcePool::factory()->create(array_merge([
            'kind' => 'staff',
            'allocation_mode' => 'individual',
            'minimum_lead_time_minutes' => 0,
        ], $overrides));
    }

    /** @return array{0: User, 1: resource} */
    private function officer(ResourcePool $pool, string $name, string $email): array
    {
        $user = User::factory()->create(['name' => $name, 'email' => $email, 'bookable_as_officer' => true]);
        $user->assignRole('user');
        app(StaffResourceSync::class)->syncUser($user);
        $resource = Resource::where('resource_pool_id', $pool->id)->where('user_id', $user->id)->firstOrFail();

        return [$user, $resource];
    }

    private function bookOfficer(ResourcePool $pool, Resource $resource, User $staff, Carbon $start): Booking
    {
        return app(BookingService::class)->create([
            'resource_pool_id' => $pool->id, 'location_id' => null, 'booking_type_id' => null,
            'start_at' => $start, 'end_at' => $start->copy()->addHour(),
            'notes' => 'Screen share issue', 'students' => [],
            'items' => [['resource_pool_id' => $pool->id, 'quantity' => 1, 'resource_ids' => [$resource->id]]],
        ], $staff, $staff);
    }

    public function test_the_booked_officer_gets_an_fyi_with_a_calendar_attachment(): void
    {
        Mail::fake();
        $pool = $this->staffPool();
        [$alice, $aliceResource] = $this->officer($pool, 'Alice', 'alice@example.com');
        $staff = User::factory()->create();

        $this->bookOfficer($pool, $aliceResource, $staff, $this->safeStart());

        Mail::assertQueued(BookingNotificationMail::class, function ($mail) {
            if ($mail->kind !== 'officer_assigned' || ! $mail->hasTo('alice@example.com')) {
                return false;
            }
            $attachments = $mail->attachments();

            return count($attachments) === 1 && $attachments[0]->as === 'booking.ics';
        });
    }

    public function test_the_assigned_officer_route_sends_the_approval_request_to_the_officer(): void
    {
        Mail::fake();
        $pool = $this->staffPool(['approval_route' => 'assigned_officer']);
        [$alice, $aliceResource] = $this->officer($pool, 'Alice', 'alice@example.com');
        $staff = User::factory()->create();

        $booking = $this->bookOfficer($pool, $aliceResource, $staff, now()->addMinutes(75));
        $this->assertSame('pending', $booking->approval_status);

        Mail::assertQueued(BookingApprovalRequestMail::class, fn ($m) => $m->hasTo('alice@example.com'));
        Mail::assertNotQueued(BookingApprovalRequestMail::class, fn ($m) => $m->hasTo('it@example.com'));
    }

    public function test_a_user_role_officer_can_approve_via_the_signed_link(): void
    {
        $pool = $this->staffPool(['approval_route' => 'assigned_officer']);
        [$alice, $aliceResource] = $this->officer($pool, 'Alice', 'alice@example.com');
        $staff = User::factory()->create();

        $booking = $this->bookOfficer($pool, $aliceResource, $staff, now()->addMinutes(75));
        $this->assertSame('pending', $booking->approval_status);

        $url = URL::temporarySignedRoute('bookings.approve', now()->addDay(), ['booking' => $booking->reference]);
        $this->actingAs($alice)->get($url)->assertRedirect();

        $this->assertSame('approved', $booking->fresh()->approval_status);
    }

    public function test_the_team_route_still_sends_the_approval_request_to_it(): void
    {
        Mail::fake();
        $pool = $this->staffPool(); // approval_route defaults to 'team'
        [$alice, $aliceResource] = $this->officer($pool, 'Alice', 'alice@example.com');
        $staff = User::factory()->create();

        $booking = $this->bookOfficer($pool, $aliceResource, $staff, now()->addMinutes(75));
        $this->assertSame('pending', $booking->approval_status);

        Mail::assertQueued(BookingApprovalRequestMail::class, fn ($m) => $m->hasTo('it@example.com'));
        Mail::assertNotQueued(BookingApprovalRequestMail::class, fn ($m) => $m->hasTo('alice@example.com'));
    }

    public function test_an_officer_booking_appears_in_the_daily_summary_with_the_officer_name(): void
    {
        Carbon::setTestNow(Carbon::parse('today 09:00'));

        try {
            $pool = $this->staffPool();
            [$alice, $aliceResource] = $this->officer($pool, 'Alice Zephyr', 'alice@example.com');
            $staff = User::factory()->create();
            $this->bookOfficer($pool, $aliceResource, $staff, now()->addHours(2));

            $admin = User::factory()->create(['receives_daily_summary' => true]);
            $admin->assignRole('administrator');

            Mail::fake();
            Artisan::call('bookings:send-daily-summary');

            Mail::assertQueued(DailySummaryMail::class, fn ($m) => $m->hasTo($admin->email)
                && str_contains($m->render(), 'Alice Zephyr'));
        } finally {
            Carbon::setTestNow();
        }
    }
}
