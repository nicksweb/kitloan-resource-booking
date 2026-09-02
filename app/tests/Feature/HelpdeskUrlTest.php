<?php

namespace Tests\Feature;

use App\Livewire\BookingDetail;
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
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class HelpdeskUrlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(MessageTemplateSeeder::class);
        app(SettingsRepository::class)->set('it_notification_address', 'it@example.com');
        Mail::fake();
    }

    private function safeStart(): Carbon
    {
        $start = now()->addDays(3)->setTime(10, 0);
        while ($start->isWeekend()) {
            $start->addDay();
        }

        return $start;
    }

    private function booking(User $owner, ?string $helpdeskUrl = null): Booking
    {
        $pool = ResourcePool::factory()->create(['minimum_lead_time_minutes' => 0]);
        $laptop = Resource::factory()->create(['resource_pool_id' => $pool->id]);

        $start = $this->safeStart();

        return app(BookingService::class)->create([
            'resource_pool_id' => $pool->id, 'location_id' => null, 'booking_type_id' => null,
            'start_at' => $start, 'end_at' => $start->copy()->addHour(),
            'notes' => null, 'helpdesk_url' => $helpdeskUrl, 'students' => [],
            'items' => [['resource_pool_id' => $pool->id, 'quantity' => 1, 'resource_ids' => [$laptop->id]]],
        ], $owner, $owner);
    }

    public function test_the_helpdesk_url_is_persisted_from_the_create_payload(): void
    {
        $owner = User::factory()->create();
        $booking = $this->booking($owner, 'https://help.example.com/T-42');

        $this->assertSame('https://help.example.com/T-42', $booking->helpdesk_url);
    }

    public function test_an_it_operator_can_edit_the_helpdesk_url_from_the_detail_page(): void
    {
        $owner = User::factory()->create();
        $operator = User::factory()->create();
        $operator->assignRole('it_operator');
        $booking = $this->booking($owner);

        Livewire::actingAs($operator)->test(BookingDetail::class, ['booking' => $booking])
            ->set('helpdeskUrl', 'https://help.example.com/T-7')
            ->call('setHelpdeskUrl')
            ->assertHasNoErrors();

        $this->assertSame('https://help.example.com/T-7', $booking->fresh()->helpdesk_url);
        $this->assertDatabaseHas('audit_events', ['event_type' => 'booking.helpdesk_url_set']);
    }

    public function test_a_booked_officer_with_only_the_user_role_can_edit_the_helpdesk_url(): void
    {
        $pool = ResourcePool::factory()->create([
            'kind' => 'staff', 'allocation_mode' => 'individual', 'minimum_lead_time_minutes' => 0,
        ]);
        $officerUser = User::factory()->create(['name' => 'Olivia', 'bookable_as_officer' => true]);
        $officerUser->assignRole('user');
        app(StaffResourceSync::class)->syncUser($officerUser);
        $officerResource = Resource::where('resource_pool_id', $pool->id)->where('user_id', $officerUser->id)->firstOrFail();

        $staff = User::factory()->create();
        $start = $this->safeStart();
        $booking = app(BookingService::class)->create([
            'resource_pool_id' => $pool->id, 'location_id' => null, 'booking_type_id' => null,
            'start_at' => $start, 'end_at' => $start->copy()->addHour(),
            'notes' => 'Teams support', 'students' => [],
            'items' => [['resource_pool_id' => $pool->id, 'quantity' => 1, 'resource_ids' => [$officerResource->id]]],
        ], $staff, $staff);

        Livewire::actingAs($officerUser)->test(BookingDetail::class, ['booking' => $booking])
            ->set('helpdeskUrl', 'https://help.example.com/T-99')
            ->call('setHelpdeskUrl')
            ->assertHasNoErrors();

        $this->assertSame('https://help.example.com/T-99', $booking->fresh()->helpdesk_url);
    }

    public function test_an_unrelated_user_cannot_view_or_edit_the_booking(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $stranger->assignRole('user');
        $booking = $this->booking($owner);

        $this->assertFalse($stranger->can('view', $booking));

        Livewire::actingAs($stranger)->test(BookingDetail::class, ['booking' => $booking])
            ->assertForbidden();
    }

    public function test_an_invalid_url_is_rejected(): void
    {
        $owner = User::factory()->create();
        $booking = $this->booking($owner);

        Livewire::actingAs($owner)->test(BookingDetail::class, ['booking' => $booking])
            ->set('helpdeskUrl', 'not-a-url')
            ->call('setHelpdeskUrl')
            ->assertHasErrors('helpdeskUrl');

        $this->assertNull($booking->fresh()->helpdesk_url);
    }

    public function test_the_helpdesk_link_renders_on_the_detail_page(): void
    {
        $owner = User::factory()->create();
        $booking = $this->booking($owner, 'https://help.example.com/T-1');

        Livewire::actingAs($owner)->test(BookingDetail::class, ['booking' => $booking])
            ->assertSee('https://help.example.com/T-1');
    }
}
