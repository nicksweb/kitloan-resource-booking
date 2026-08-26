<?php

namespace Tests\Feature;

use App\Livewire\Booking\BookingWizard;
use App\Models\Location;
use App\Models\ResourcePool;
use App\Models\SchedulePeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class BookingWizardSmartsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_mounting_within_school_hours_defaults_to_an_hour_from_now(): void
    {
        Carbon::setTestNow(Carbon::parse('today 10:00', config('app.timezone')));
        $pool = ResourcePool::factory()->quantityTracked(5)->create();
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(BookingWizard::class, ['resourcePool' => $pool])
            ->assertSet('date', now(config('app.timezone'))->format('Y-m-d'))
            ->assertSet('startTime', '11:00')
            ->assertSet('endTime', '12:00');
    }

    public function test_mounting_outside_school_hours_defaults_to_next_days_opening_time(): void
    {
        Carbon::setTestNow(Carbon::parse('today 23:00', config('app.timezone')));
        $pool = ResourcePool::factory()->quantityTracked(5)->create(['allow_weekends' => true]);
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(BookingWizard::class, ['resourcePool' => $pool])
            ->assertSet('date', now(config('app.timezone'))->addDay()->format('Y-m-d'))
            ->assertSet('startTime', '07:00')
            ->assertSet('endTime', '08:00');
    }

    public function test_mounting_outside_hours_before_a_weekend_skips_to_monday_when_the_pool_disallows_weekends(): void
    {
        Carbon::setTestNow(Carbon::now(config('app.timezone'))->next(Carbon::FRIDAY)->setTime(23, 0));
        $pool = ResourcePool::factory()->quantityTracked(5)->create(['allow_weekends' => false]);
        $user = User::factory()->create();

        $expectedMonday = now(config('app.timezone'))->next(Carbon::MONDAY)->format('Y-m-d');

        Livewire::actingAs($user)
            ->test(BookingWizard::class, ['resourcePool' => $pool])
            ->assertSet('date', $expectedMonday)
            ->assertSet('startTime', '07:00');
    }

    public function test_changing_the_start_time_jumps_the_finish_time_an_hour_later(): void
    {
        $pool = ResourcePool::factory()->quantityTracked(5)->create();
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(BookingWizard::class, ['resourcePool' => $pool])
            ->set('startTime', '09:15')
            ->assertSet('endTime', '10:15');
    }

    public function test_selecting_a_period_fills_start_and_finish_from_it(): void
    {
        $pool = ResourcePool::factory()->quantityTracked(5)->create();
        $user = User::factory()->create();
        $period = SchedulePeriod::factory()->create([
            'group_name' => 'Senior School', 'name' => 'Period 1',
            'start_time' => '08:45:00', 'end_time' => '09:58:00',
        ]);

        Livewire::actingAs($user)
            ->test(BookingWizard::class, ['resourcePool' => $pool])
            ->call('applyPeriod', $period->id)
            ->assertSet('startTime', '08:45')
            ->assertSet('endTime', '09:58');
    }

    public function test_quick_fill_select_binding_applies_the_period_and_resets_itself(): void
    {
        $pool = ResourcePool::factory()->quantityTracked(5)->create();
        $user = User::factory()->create();
        $period = SchedulePeriod::factory()->create([
            'group_name' => 'Senior School', 'name' => 'Period 2',
            'start_time' => '10:05:00', 'end_time' => '11:15:00',
        ]);

        Livewire::actingAs($user)
            ->test(BookingWizard::class, ['resourcePool' => $pool])
            ->set('quickPeriodId', $period->id)
            ->assertSet('startTime', '10:05')
            ->assertSet('endTime', '11:15')
            ->assertSet('quickPeriodId', null);
    }

    public function test_room_picker_renders_as_a_searchable_select(): void
    {
        $pool = ResourcePool::factory()->quantityTracked(5)->create(['requires_room' => true]);
        $user = User::factory()->create();
        Location::factory()->create(['code' => 'A1', 'name' => 'Science Lab']);

        Livewire::actingAs($user)
            ->test(BookingWizard::class, ['resourcePool' => $pool])
            ->assertOk()
            ->assertSee('Search rooms…')
            ->assertSee('A1 — Science Lab');
    }

    public function test_disabled_periods_are_not_offered(): void
    {
        $pool = ResourcePool::factory()->quantityTracked(5)->create();
        $user = User::factory()->create();
        SchedulePeriod::factory()->create(['name' => 'Retired Period', 'enabled' => false]);

        Livewire::actingAs($user)
            ->test(BookingWizard::class, ['resourcePool' => $pool])
            ->assertDontSee('Retired Period');
    }
}
