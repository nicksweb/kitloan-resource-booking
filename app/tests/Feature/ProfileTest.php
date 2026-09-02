<?php

namespace Tests\Feature;

use App\Livewire\Profile;
use App\Models\ResourcePool;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_the_profile_page_is_reachable_for_an_authenticated_user(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $this->actingAs($user)->get('/profile')->assertOk();
    }

    public function test_the_officer_toggle_is_hidden_for_a_plain_user(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        Livewire::actingAs($user)->test(Profile::class)
            ->assertViewHas('canBeOfficer', false)
            ->assertDontSee('bookable as an IT officer');
    }

    public function test_the_officer_toggle_is_visible_for_an_it_operator(): void
    {
        $user = User::factory()->create();
        $user->assignRole('it_operator');

        Livewire::actingAs($user)->test(Profile::class)
            ->assertViewHas('canBeOfficer', true)
            ->assertSee('bookable as an IT officer');
    }

    public function test_saving_syncs_officer_resources_and_the_summary_flag(): void
    {
        $pool = ResourcePool::factory()->create(['kind' => 'staff']);
        $user = User::factory()->create(['receives_daily_summary' => true]);
        $user->assignRole('it_operator');

        Livewire::actingAs($user)->test(Profile::class)
            ->set('bookableAsOfficer', true)
            ->set('receivesDailySummary', false)
            ->call('save')
            ->assertHasNoErrors();

        $user->refresh();
        $this->assertTrue($user->bookable_as_officer);
        $this->assertFalse($user->receives_daily_summary);
        $this->assertDatabaseHas('resources', [
            'resource_pool_id' => $pool->id,
            'user_id' => $user->id,
            'status' => 'available',
        ]);
        $this->assertDatabaseHas('audit_events', ['event_type' => 'profile.officer_availability']);
    }

    public function test_a_plain_user_cannot_make_themselves_bookable(): void
    {
        ResourcePool::factory()->create(['kind' => 'staff']);
        $user = User::factory()->create();
        $user->assignRole('user');

        Livewire::actingAs($user)->test(Profile::class)
            ->set('bookableAsOfficer', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertFalse($user->fresh()->bookable_as_officer);
        $this->assertDatabaseMissing('resources', ['user_id' => $user->id]);
    }

    public function test_the_daily_summary_opt_out_persists(): void
    {
        $user = User::factory()->create(['receives_daily_summary' => true]);
        $user->assignRole('user');

        Livewire::actingAs($user)->test(Profile::class)
            ->set('receivesDailySummary', false)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertFalse($user->fresh()->receives_daily_summary);
    }
}
