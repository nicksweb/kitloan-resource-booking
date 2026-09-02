<?php

namespace Tests\Feature;

use App\Livewire\Admin\SettingsIndex;
use App\Models\User;
use App\Settings\SettingsRepository;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DeveloperLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(SettingsSeeder::class);
    }

    public function test_the_footer_shows_the_project_link_by_default(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/')
            ->assertOk()
            ->assertSee('Built with Kitloan')
            ->assertSee('github.com/nicksweb/kitloan-resource-booking', false);
    }

    public function test_an_administrator_can_hide_the_project_link(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrator');

        Livewire::actingAs($admin)
            ->test(SettingsIndex::class)
            ->set('showDeveloperLink', false)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertFalse((bool) app(SettingsRepository::class)->get('show_developer_link'));

        $this->actingAs($admin)->get('/')
            ->assertOk()
            ->assertDontSee('Built with Kitloan');
    }
}
