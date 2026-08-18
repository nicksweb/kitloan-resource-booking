<?php

namespace Tests\Feature;

use App\Livewire\Booking\BookingWizard;
use App\Models\ResourcePool;
use App\Models\SchedulePeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BookingWizardSmartsTest extends TestCase
{
    use RefreshDatabase;

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
