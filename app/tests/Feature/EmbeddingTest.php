<?php

namespace Tests\Feature;

use App\Settings\SettingsRepository;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmbeddingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_embedding_disabled_locks_frames_to_self(): void
    {
        $response = $this->get(route('auth.login'));

        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Content-Security-Policy', "frame-ancestors 'self'");
    }

    public function test_embedding_enabled_allow_lists_the_configured_origins(): void
    {
        $settings = app(SettingsRepository::class);
        $settings->set('embedding_enabled', true, 'boolean');
        $settings->set('embedding_allowed_origins', "https://intranet.example.edu\nhttps://portal.example.edu/");

        $response = $this->get(route('auth.login'));

        $response->assertHeaderMissing('X-Frame-Options');
        $response->assertHeader(
            'Content-Security-Policy',
            "frame-ancestors 'self' https://intranet.example.edu https://portal.example.edu",
        );
    }

    public function test_embed_query_param_is_remembered_for_the_session(): void
    {
        $this->get(route('auth.login', ['embed' => 1]))->assertOk();

        // The flag persists to a later request with no ?embed param, and the
        // login screen switches to the silent sign-in path.
        $this->get(route('auth.login'))
            ->assertOk()
            ->assertSee('Signing you in')
            ->assertDontSee('You\'ll be redirected to your organisation');
    }

    public function test_a_failed_silent_attempt_falls_back_to_the_button(): void
    {
        $this->get(route('auth.login', ['embed' => 1]));

        $this->withSession(['silent_failed' => true])
            ->get(route('auth.login'))
            ->assertOk()
            ->assertSee('Sign in with your school account')
            ->assertDontSee('Signing you in');
    }

    public function test_silent_route_sends_an_authenticated_user_straight_home(): void
    {
        $user = \App\Models\User::factory()->create();

        $this->actingAs($user)->get(route('auth.silent'))->assertRedirect(route('home'));
    }
}
