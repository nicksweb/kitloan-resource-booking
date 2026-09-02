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

    public function test_the_theme_defaults_to_system_and_can_be_changed(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        $this->assertSame('system', $user->theme);

        Livewire::actingAs($user)->test(Profile::class)
            ->assertSet('theme', 'system')
            ->set('theme', 'dark')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('dark', $user->fresh()->theme);
    }

    public function test_an_unknown_theme_value_is_rejected(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        Livewire::actingAs($user)->test(Profile::class)
            ->set('theme', 'neon')
            ->call('save')
            ->assertHasErrors('theme');

        $this->assertSame('system', $user->fresh()->theme);
    }

    public function test_changing_the_theme_dispatches_a_browser_event_for_live_preview(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        Livewire::actingAs($user)->test(Profile::class)
            ->set('theme', 'light')
            ->assertDispatched('theme-changed', theme: 'light');
    }

    public function test_a_dark_preference_puts_the_class_on_the_html_element(): void
    {
        $user = User::factory()->create(['theme' => 'dark']);
        $user->assignRole('user');

        $html = $this->actingAs($user)->get('/profile')->assertOk()->getContent();

        // The no-flash bootstrap script carries the server value.
        $this->assertStringContainsString('"dark"', $html);
        $this->assertStringContainsString("classList.toggle('dark'", $html);
    }

    public function test_the_sign_in_screen_also_carries_the_theme_bootstrap(): void
    {
        $this->get(route('auth.login'))
            ->assertOk()
            ->assertSee("classList.toggle('dark'", false);
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
