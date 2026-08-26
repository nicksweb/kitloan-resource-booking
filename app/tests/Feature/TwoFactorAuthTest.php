<?php

namespace Tests\Feature;

use App\Livewire\Admin\UsersIndex;
use App\Models\User;
use App\Services\Auth\TwoFactorAuthenticator;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        config(['auth.local_login.enabled' => true]);
    }

    private function localAdmin(array $overrides = []): User
    {
        $admin = User::factory()->create(array_merge([
            'password' => Hash::make('a-strong-password-123'),
        ], $overrides));
        $admin->assignRole('administrator');

        return $admin;
    }

    private function otp(string $secret): string
    {
        return app(Google2FA::class)->getCurrentOtp($secret);
    }

    public function test_sso_only_administrator_is_never_prompted_for_two_factor(): void
    {
        $admin = User::factory()->create(['password' => null, 'oidc_subject' => 'sub-abc']);
        $admin->assignRole('administrator');

        $this->actingAs($admin)->get(route('bookings.index'))->assertOk();
    }

    public function test_local_administrator_without_two_factor_is_forced_to_enrol(): void
    {
        $admin = $this->localAdmin();

        $this->actingAs($admin)->get(route('bookings.index'))->assertRedirect(route('two-factor.setup'));
        $this->actingAs($admin)->get(route('two-factor.setup'))->assertOk()->assertSee('Scan this QR code');
    }

    public function test_enrolment_confirms_with_a_valid_code_and_then_lets_the_user_through(): void
    {
        $admin = $this->localAdmin();

        $this->actingAs($admin)->get(route('two-factor.setup'))->assertOk();
        $secret = session('2fa.pending_secret');
        $this->assertNotEmpty($secret);

        $this->actingAs($admin)->post(route('two-factor.setup.confirm'), ['code' => $this->otp($secret)])
            ->assertRedirect(route('home'));

        $admin->refresh();
        $this->assertNotNull($admin->two_factor_confirmed_at);
        $this->assertCount(8, $admin->two_factor_recovery_codes);

        $this->actingAs($admin)->get(route('bookings.index'))->assertOk();
    }

    public function test_enrolment_rejects_a_bad_code(): void
    {
        $admin = $this->localAdmin();
        $this->actingAs($admin)->get(route('two-factor.setup'));

        $this->actingAs($admin)->post(route('two-factor.setup.confirm'), ['code' => '000000'])
            ->assertSessionHasErrors('code');

        $this->assertNull($admin->fresh()->two_factor_confirmed_at);
    }

    public function test_local_login_with_two_factor_enabled_requires_the_second_factor(): void
    {
        $secret = app(TwoFactorAuthenticator::class)->generateSecret();
        $admin = $this->localAdmin([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
        ]);

        // Correct password alone does not authenticate.
        $this->post(route('auth.local'), ['email' => $admin->email, 'password' => 'a-strong-password-123'])
            ->assertRedirect(route('two-factor.challenge'));
        $this->assertGuest();

        // Wrong code is refused.
        $this->post(route('two-factor.challenge.verify'), ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertGuest();

        // Correct code completes the sign-in.
        $this->post(route('two-factor.challenge.verify'), ['code' => $this->otp($secret)])
            ->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($admin);
    }

    public function test_a_recovery_code_works_once(): void
    {
        $authenticator = app(TwoFactorAuthenticator::class);
        $plain = $authenticator->generateRecoveryCodes();
        $admin = $this->localAdmin([
            'two_factor_secret' => $authenticator->generateSecret(),
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => $authenticator->hashRecoveryCodes($plain),
        ]);

        $this->post(route('auth.local'), ['email' => $admin->email, 'password' => 'a-strong-password-123']);

        $this->post(route('two-factor.challenge.verify'), ['code' => $plain[0]])->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($admin);
        $this->assertCount(7, $admin->fresh()->two_factor_recovery_codes);

        // Same code again, from a fresh session — no longer valid.
        auth()->logout();
        $this->post(route('auth.local'), ['email' => $admin->email, 'password' => 'a-strong-password-123']);
        $this->post(route('two-factor.challenge.verify'), ['code' => $plain[0]])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_ten_bad_challenge_codes_hard_lock_the_account(): void
    {
        // The lockout is driven by the per-account counter in the controller,
        // not the route throttle — bypass the latter so we can actually make
        // ten attempts in the test.
        $this->withoutMiddleware(ThrottleRequests::class);

        $secret = app(TwoFactorAuthenticator::class)->generateSecret();
        $admin = $this->localAdmin([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
        ]);

        $this->post(route('auth.local'), ['email' => $admin->email, 'password' => 'a-strong-password-123']);

        for ($i = 0; $i < 10; $i++) {
            $this->post(route('two-factor.challenge.verify'), ['code' => '000000']);
        }

        $this->assertNotNull($admin->fresh()->locked_until);
        $this->assertDatabaseHas('audit_events', ['event_type' => 'auth.local_login_locked']);

        // Even the correct password is now refused while locked.
        $this->post(route('auth.local'), ['email' => $admin->email, 'password' => 'a-strong-password-123'])
            ->assertSessionHasErrors('email');
    }

    public function test_admin_can_reset_another_users_two_factor(): void
    {
        $target = $this->localAdmin([
            'two_factor_secret' => app(TwoFactorAuthenticator::class)->generateSecret(),
            'two_factor_confirmed_at' => now(),
        ]);
        $actor = $this->localAdmin([
            'two_factor_secret' => app(TwoFactorAuthenticator::class)->generateSecret(),
            'two_factor_confirmed_at' => now(),
        ]);

        Livewire::actingAs($actor)
            ->test(UsersIndex::class)
            ->call('clearTwoFactor', $target->id);

        $this->assertNull($target->fresh()->two_factor_confirmed_at);
        $this->assertDatabaseHas('audit_events', ['event_type' => 'auth.two_factor_reset']);
    }
}
