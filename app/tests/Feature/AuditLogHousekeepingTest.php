<?php

namespace Tests\Feature;

use App\Livewire\Admin\AuditLogIndex;
use App\Models\AuditEvent;
use App\Models\ResourcePool;
use App\Models\User;
use App\Services\Booking\BookingService;
use App\Settings\SettingsRepository;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AuditLogHousekeepingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrator');

        return $admin;
    }

    private function event(string $when): void
    {
        AuditEvent::create([
            'event_type' => 'test.event',
            'description' => 'something happened',
            'created_at' => $when,
        ]);
    }

    public function test_an_entry_tied_to_a_booking_renders_its_row_as_a_navigable_link(): void
    {
        $pool = ResourcePool::factory()->quantityTracked(5)->create();
        $owner = User::factory()->create();
        $booking = app(BookingService::class)->create([
            'resource_pool_id' => $pool->id, 'location_id' => null, 'booking_type_id' => null,
            'start_at' => now()->addDays(2)->setTime(10, 0), 'end_at' => now()->addDays(2)->setTime(11, 0),
            'notes' => null, 'students' => [],
            'items' => [['resource_pool_id' => $pool->id, 'quantity' => 1, 'resource_ids' => null]],
        ], $owner, $owner);

        AuditEvent::create([
            'event_type' => 'test.event',
            'description' => 'touched a booking',
            'booking_id' => $booking->id,
            'created_at' => now()->toDateTimeString(),
        ]);

        Livewire::actingAs($this->admin())
            ->test(AuditLogIndex::class)
            ->assertSeeHtml('href="'.route('bookings.show', $booking).'"')
            ->assertSeeHtml('hover:bg-indigo-50/60');
    }

    public function test_clearing_by_range_removes_only_old_entries_and_records_a_marker(): void
    {
        $this->event(now()->subDays(400)->toDateTimeString());
        $this->event(now()->subDays(100)->toDateTimeString());
        $this->event(now()->subDays(5)->toDateTimeString());

        Livewire::actingAs($this->admin())
            ->test(AuditLogIndex::class)
            ->set('clearRange', '365')
            ->call('clear');

        $this->assertSame(0, AuditEvent::where('event_type', 'test.event')->where('created_at', '<', now()->subDays(365))->count());
        $this->assertSame(2, AuditEvent::where('event_type', 'test.event')->count());
        $this->assertDatabaseHas('audit_events', ['event_type' => 'audit.cleared']);
    }

    public function test_clearing_everything_wipes_all_prior_entries(): void
    {
        $this->event(now()->subDays(10)->toDateTimeString());
        $this->event(now()->subDays(1)->toDateTimeString());

        Livewire::actingAs($this->admin())
            ->test(AuditLogIndex::class)
            ->set('clearRange', 'all')
            ->call('clear');

        // Only the audit.cleared marker itself remains.
        $this->assertSame(1, AuditEvent::count());
        $this->assertSame('audit.cleared', AuditEvent::sole()->event_type);
    }

    public function test_audit_prune_respects_the_retention_setting(): void
    {
        $this->event(now()->subMonths(8)->toDateTimeString());
        $this->event(now()->subMonths(1)->toDateTimeString());

        // 0 = keep everything.
        $this->artisan('audit:prune')->assertSuccessful();
        $this->assertSame(2, AuditEvent::where('event_type', 'test.event')->count());

        app(SettingsRepository::class)->set('audit_retention_months', 3, 'integer');

        $this->artisan('audit:prune')->assertSuccessful();
        $this->assertSame(1, AuditEvent::where('event_type', 'test.event')->count());
        $this->assertDatabaseHas('audit_events', ['event_type' => 'audit.pruned']);
    }
}
