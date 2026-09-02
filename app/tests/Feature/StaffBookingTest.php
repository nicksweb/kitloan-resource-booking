<?php

namespace Tests\Feature;

use App\Exceptions\BookingConflictException;
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

class StaffBookingTest extends TestCase
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
    private function officer(ResourcePool $pool, string $name): array
    {
        $user = User::factory()->create(['name' => $name, 'bookable_as_officer' => true]);
        app(StaffResourceSync::class)->syncUser($user);
        $resource = Resource::where('resource_pool_id', $pool->id)->where('user_id', $user->id)->firstOrFail();

        return [$user, $resource];
    }

    private function bookOfficer(ResourcePool $pool, Resource $resource, User $staff, Carbon $start): Booking
    {
        return app(BookingService::class)->create([
            'resource_pool_id' => $pool->id, 'location_id' => null, 'booking_type_id' => null,
            'start_at' => $start, 'end_at' => $start->copy()->addHour(),
            'notes' => 'Teams support', 'students' => [],
            'items' => [['resource_pool_id' => $pool->id, 'quantity' => 1, 'resource_ids' => [$resource->id]]],
        ], $staff, $staff);
    }

    public function test_booking_a_staff_pool_allocates_the_officer(): void
    {
        Mail::fake();
        $pool = $this->staffPool();
        [$alice, $aliceResource] = $this->officer($pool, 'Alice');
        $staff = User::factory()->create();
        $staff->assignRole('user');

        $booking = $this->bookOfficer($pool, $aliceResource, $staff, $this->safeStart());

        $this->assertTrue($booking->hasOfficer($alice));
        $this->assertSame(['Alice'], $booking->officers()->pluck('name')->all());
    }

    public function test_an_officer_cannot_be_double_booked_for_overlapping_times(): void
    {
        Mail::fake();
        $pool = $this->staffPool();
        [, $aliceResource] = $this->officer($pool, 'Alice');
        $staff = User::factory()->create();

        $start = $this->safeStart();
        $this->bookOfficer($pool, $aliceResource, $staff, $start);

        $this->expectException(BookingConflictException::class);
        $this->bookOfficer($pool, $aliceResource, $staff, $start->copy()->addMinutes(30));
    }

    public function test_it_can_substitute_one_officer_for_another(): void
    {
        $pool = $this->staffPool();
        [$alice, $aliceResource] = $this->officer($pool, 'Alice');
        [$bob, $bobResource] = $this->officer($pool, 'Bob');

        $staff = User::factory()->create();
        $operator = User::factory()->create();
        $operator->assignRole('it_operator');

        $booking = $this->bookOfficer($pool, $aliceResource, $staff, $this->safeStart());
        $allocationId = $booking->items->first()->allocations->first()->id;

        Mail::fake();

        Livewire::actingAs($operator)->test(BookingDetail::class, ['booking' => $booking])
            ->call('startSubstitution', $allocationId)
            ->set('replacementResourceId', $bobResource->id)
            ->set('substitutionReason', 'Alice is on leave')
            ->call('confirmSubstitution')
            ->assertHasNoErrors();

        $booking->refresh()->load('items.allocations.resource.user');
        $this->assertFalse($booking->hasOfficer($alice));
        $this->assertTrue($booking->hasOfficer($bob));
    }
}
