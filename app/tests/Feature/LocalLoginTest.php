<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LocalLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_local_login_is_a_404_when_disabled(): void
    {
        config(['auth.local_login.enabled' => false]);

        $this->get(route('auth.local.show'))->assertNotFound();
        $this->post(route('auth.local'), ['email' => 'x@example.com', 'password' => 'whatever'])->assertNotFound();
    }

    public function test_an_administrator_with_a_local_password_can_sign_in(): void
    {
        config(['auth.local_login.enabled' => true]);
        $admin = User::factory()->create(['password' => Hash::make('a-strong-password-123')]);
        $admin->assignRole('administrator');

        $this->post(route('auth.local'), [
            'email' => $admin->email,
            'password' => 'a-strong-password-123',
        ])->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_a_non_administrator_cannot_use_local_login_even_with_a_password_set(): void
    {
        config(['auth.local_login.enabled' => true]);
        $user = User::factory()->create(['password' => Hash::make('a-strong-password-123')]);
        $user->assignRole('user');

        $this->post(route('auth.local'), [
            'email' => $user->email,
            'password' => 'a-strong-password-123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_repeated_failed_attempts_are_rate_limited(): void
    {
        config(['auth.local_login.enabled' => true]);
        $admin = User::factory()->create(['password' => Hash::make('a-strong-password-123')]);
        $admin->assignRole('administrator');

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('auth.local'), ['email' => $admin->email, 'password' => 'wrong']);
        }

        $response = $this->post(route('auth.local'), ['email' => $admin->email, 'password' => 'a-strong-password-123']);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
