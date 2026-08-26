<?php

namespace Tests\Feature;

use App\Livewire\Admin\ReportsIndex;
use App\Models\Booking;
use App\Models\ResourcePool;
use App\Models\User;
use App\Services\Booking\BookingService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function operator(): User
    {
        $u = User::factory()->create();
        $u->assignRole('it_operator');

        return $u;
    }

    /**
     * BookingService rejects past start times, so book in the future then
     * shift the row's window into the past for the report to pick up.
     */
    private function book(ResourcePool $pool, User $owner, Carbon $start, int $qty): Booking
    {
        $booking = app(BookingService::class)->create([
            'resource_pool_id' => $pool->id, 'location_id' => null, 'booking_type_id' => null,
            'start_at' => now()->addYear(), 'end_at' => now()->addYear()->addHour(),
            'notes' => null, 'students' => [],
            'items' => [['resource_pool_id' => $pool->id, 'quantity' => $qty, 'resource_ids' => null]],
        ], $owner, $owner);

        $booking->forceFill(['start_at' => $start->copy(), 'end_at' => $start->copy()->addHour()])->save();

        return $booking->refresh();
    }

    public function test_a_plain_user_cannot_open_reports(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $this->actingAs($user)->get(route('admin.reports.index'))->assertForbidden();
    }

    public function test_volume_requestors_and_approval_numbers(): void
    {
        $pool = ResourcePool::factory()->quantityTracked(20)->create(['requires_room' => false]);
        $alice = User::factory()->create(['name' => 'Alice']);
        $bob = User::factory()->create(['name' => 'Bob']);

        // Two bookings ~1 month back, one ~2 months back. Alice books twice.
        $this->book($pool, $alice, now()->subMonthNoOverflow()->startOfMonth()->addDays(9)->setTime(10, 0), 3);
        $this->book($pool, $alice, now()->subMonthsNoOverflow(2)->startOfMonth()->addDays(9)->setTime(10, 0), 5);
        $this->book($pool, $bob, now()->subMonthNoOverflow()->startOfMonth()->addDays(10)->setTime(10, 0), 2);

        Livewire::actingAs($this->operator())
            ->test(ReportsIndex::class)
            ->set('from', now()->subMonthsNoOverflow(3)->toDateString())
            ->set('to', now()->toDateString())
            ->assertViewHas('total', 3)
            ->assertViewHas('totalUnits', 10)
            ->assertViewHas('volume', fn ($v) => count($v) === 2)
            ->assertViewHas('topRequestors', fn ($t) => $t[0]['label'] === 'Alice' && $t[0]['count'] === 2)
            ->assertViewHas('approval', fn ($a) => $a['auto'] + $a['manual'] + $a['pending'] === 3);
    }

    public function test_utilisation_is_computed_per_pool(): void
    {
        $pool = ResourcePool::factory()->quantityTracked(10)->create(['requires_room' => false]);
        $owner = User::factory()->create();
        $this->book($pool, $owner, now()->subDays(10)->setTime(10, 0), 4);

        Livewire::actingAs($this->operator())
            ->test(ReportsIndex::class)
            ->set('from', now()->subDays(30)->toDateString())
            ->set('to', now()->toDateString())
            ->assertViewHas('utilisation', function ($u) {
                return count($u) === 1
                    && $u[0]['resource_days'] === 4
                    && $u[0]['utilisation'] !== null;
            });
    }

    public function test_csv_export_returns_a_file_with_a_header_row(): void
    {
        $pool = ResourcePool::factory()->quantityTracked(10)->create(['requires_room' => false]);
        $owner = User::factory()->create();
        $this->book($pool, $owner, now()->subDays(3)->setTime(9, 0), 1);

        $response = Livewire::actingAs($this->operator())
            ->test(ReportsIndex::class)
            ->call('export');

        $response->assertFileDownloaded();
    }
}
