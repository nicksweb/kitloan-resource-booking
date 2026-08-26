<?php

namespace Tests\Feature;

use App\Livewire\Admin\SettingsIndex;
use App\Models\User;
use App\Settings\SettingsRepository;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SiteLogoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_default_mark_is_used_when_no_logo_is_set(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('bookings.index'));

        $response->assertOk();
        // The built-in laptop icon renders as inline SVG, not an <img>.
        $response->assertDontSee('storage/logos', escape: false);
    }

    public function test_uploaded_logo_is_rendered_and_can_be_removed(): void
    {
        $settings = app(SettingsRepository::class);
        $settings->set('site_logo_path', 'logos/example.png');

        $user = User::factory()->create();
        $this->actingAs($user)->get(route('bookings.index'))
            ->assertOk()
            ->assertSee('logos/example.png', escape: false);

        $admin = User::factory()->create();
        $admin->assignRole('administrator');
        Livewire::actingAs($admin)->test(SettingsIndex::class)->call('removeLogo');

        $settings->forgetCache();
        $this->assertSame('', $settings->get('site_logo_path'));
    }

    public function test_settings_page_shows_the_running_version(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrator');

        Livewire::actingAs($admin)->test(SettingsIndex::class)
            ->assertSee('About this instance')
            ->assertSee('v'.config('version.app'))
            ->assertSee('not recorded yet');

        app(SettingsRepository::class)->set(config('version.stored_version_key'), config('version.app'));

        Livewire::actingAs($admin)->test(SettingsIndex::class)
            ->assertDontSee('not recorded yet');
    }
}
