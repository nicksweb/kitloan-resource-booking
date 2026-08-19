<?php

namespace Tests\Feature;

use App\Livewire\Admin\LocationsIndex;
use App\Livewire\Admin\UsersIndex;
use App\Models\Location;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class AdminLocalUserManagementTest extends TestCase
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

    // Regression: LocationsIndex::save() 500'd on create because the unique
    // rule was built as 'unique:locations,code,'.$editingId, which is
    // 'unique:locations,code,' (empty ignore-id) when editingId is null —
    // Postgres rejects casting '' to bigint.
    public function test_creating_a_new_location_does_not_error(): void
    {
        Livewire::actingAs($this->admin())
            ->test(LocationsIndex::class)
            ->call('create')
            ->set('code', 'D01')
            ->set('name', 'D Block Room 01')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('locations', ['code' => 'D01']);
    }

    public function test_editing_a_location_keeping_the_same_code_does_not_error(): void
    {
        $location = Location::factory()->create(['code' => 'D01']);

        Livewire::actingAs($this->admin())
            ->test(LocationsIndex::class)
            ->call('edit', $location->id)
            ->set('name', 'Renamed Room')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('locations', ['id' => $location->id, 'name' => 'Renamed Room']);
    }

    // Regression: same bug pattern in UsersIndex::save().
    public function test_creating_a_new_user_does_not_error(): void
    {
        Livewire::actingAs($this->admin())
            ->test(UsersIndex::class)
            ->call('create')
            ->set('name', 'New Person')
            ->set('email', 'newperson@example.com')
            ->set('role', 'user')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', ['email' => 'newperson@example.com']);
    }

    public function test_admin_can_set_a_local_login_password_for_another_administrator(): void
    {
        $actor = $this->admin();
        $target = $this->admin();

        Livewire::actingAs($actor)
            ->test(UsersIndex::class)
            ->call('openLocalPasswordForm', $target->id)
            ->set('newLocalPassword', 'a-strong-password-123')
            ->set('newLocalPasswordConfirmation', 'a-strong-password-123')
            ->call('saveLocalPassword')
            ->assertHasNoErrors();

        $this->assertTrue(Hash::check('a-strong-password-123', $target->fresh()->password));
        $this->assertDatabaseHas('audit_events', ['event_type' => 'auth.local_password_set']);
    }

    public function test_setting_a_local_password_requires_matching_confirmation(): void
    {
        $target = $this->admin();

        Livewire::actingAs($this->admin())
            ->test(UsersIndex::class)
            ->call('openLocalPasswordForm', $target->id)
            ->set('newLocalPassword', 'a-strong-password-123')
            ->set('newLocalPasswordConfirmation', 'does-not-match')
            ->call('saveLocalPassword')
            ->assertHasErrors('newLocalPasswordConfirmation');

        $this->assertNull($target->fresh()->password);
    }

    public function test_local_password_cannot_be_set_for_a_non_administrator(): void
    {
        $target = User::factory()->create();
        $target->assignRole('user');

        Livewire::actingAs($this->admin())
            ->test(UsersIndex::class)
            ->call('openLocalPasswordForm', $target->id)
            ->assertSet('showLocalPasswordForm', false);
    }

    public function test_admin_can_clear_a_local_login_password(): void
    {
        $target = $this->admin();
        $target->update(['password' => Hash::make('a-strong-password-123')]);

        Livewire::actingAs($this->admin())
            ->test(UsersIndex::class)
            ->call('clearLocalPassword', $target->id);

        $this->assertNull($target->fresh()->password);
        $this->assertDatabaseHas('audit_events', ['event_type' => 'auth.local_password_cleared']);
    }

    public function test_local_login_route_is_a_404_when_the_settings_toggle_is_off_even_if_the_env_flag_is_on(): void
    {
        config(['auth.local_login.enabled' => true]);
        Setting::updateOrCreate(['key' => 'local_login_enabled'], ['value' => '0', 'type' => 'boolean']);
        app(\App\Settings\SettingsRepository::class)->forgetCache();

        $this->get(route('auth.local.show'))->assertNotFound();
    }

    // The per-IP+email limiter (5/60s) and the built-in route-level
    // throttle:10,1 middleware both key off the source IP, so a real
    // distributed attack rotates IPs to dodge them — this simulates exactly
    // that, to prove the email-only limiter still catches it.
    public function test_repeated_failures_against_one_account_from_rotating_ips_are_still_throttled(): void
    {
        config(['auth.local_login.enabled' => true]);
        $admin = User::factory()->create(['password' => Hash::make('a-strong-password-123')]);
        $admin->assignRole('administrator');

        for ($i = 0; $i < 15; $i++) {
            $this->call('POST', route('auth.local'), ['email' => $admin->email, 'password' => 'wrong'], [], [], ['REMOTE_ADDR' => "10.0.0.{$i}"]);
        }

        $response = $this->call('POST', route('auth.local'), ['email' => $admin->email, 'password' => 'a-strong-password-123'], [], [], ['REMOTE_ADDR' => '10.0.0.99']);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertDatabaseHas('audit_events', ['event_type' => 'auth.local_login_bruteforce_suspected']);
    }
}
