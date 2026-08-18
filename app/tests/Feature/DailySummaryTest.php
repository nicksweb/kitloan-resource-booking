<?php

namespace Tests\Feature;

use App\Mail\DailySummaryMail;
use App\Models\ResourcePool;
use App\Models\User;
use App\Services\Booking\BookingService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DailySummaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        // Freeze to a safe mid-morning moment — "a booking 2 hours from now"
        // must not accidentally cross midnight depending on when tests run.
        Carbon::setTestNow(Carbon::parse('today 09:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_no_summary_is_sent_when_there_are_no_bookings_today(): void
    {
        Mail::fake();
        User::factory()->create()->assignRole('administrator');

        Artisan::call('bookings:send-daily-summary');

        Mail::assertNothingQueued();
    }

    public function test_summary_is_sent_to_it_staff_who_have_not_opted_out(): void
    {
        Mail::fake();

        $pool = ResourcePool::factory()->quantityTracked(5)->create();
        $owner = User::factory()->create();
        app(BookingService::class)->create([
            'resource_pool_id' => $pool->id, 'location_id' => null, 'booking_type_id' => null,
            'start_at' => now()->addHours(2), 'end_at' => now()->addHours(3),
            'notes' => null, 'students' => [],
            'items' => [['resource_pool_id' => $pool->id, 'quantity' => 1, 'resource_ids' => null]],
        ], $owner, $owner);

        $subscribedAdmin = User::factory()->create(['receives_daily_summary' => true]);
        $subscribedAdmin->assignRole('administrator');

        $optedOutOperator = User::factory()->create(['receives_daily_summary' => false]);
        $optedOutOperator->assignRole('it_operator');

        $regularUser = User::factory()->create(['receives_daily_summary' => true]);
        $regularUser->assignRole('user');

        $disabledAdmin = User::factory()->create(['receives_daily_summary' => true, 'enabled' => false]);
        $disabledAdmin->assignRole('administrator');

        Mail::fake(); // re-fake after create()'s own notifications

        Artisan::call('bookings:send-daily-summary');

        Mail::assertQueued(DailySummaryMail::class, fn ($mail) => $mail->hasTo($subscribedAdmin->email));
        Mail::assertNotQueued(DailySummaryMail::class, fn ($mail) => $mail->hasTo($optedOutOperator->email));
        Mail::assertNotQueued(DailySummaryMail::class, fn ($mail) => $mail->hasTo($regularUser->email));
        Mail::assertNotQueued(DailySummaryMail::class, fn ($mail) => $mail->hasTo($disabledAdmin->email));
    }
}
