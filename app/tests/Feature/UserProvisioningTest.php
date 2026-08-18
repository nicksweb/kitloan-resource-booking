<?php

namespace Tests\Feature;

use App\Exceptions\OidcIdentityException;
use App\Models\User;
use App\Services\Auth\UserProvisioningService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserProvisioningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_a_pre_created_account_is_linked_on_first_login(): void
    {
        $preCreated = User::factory()->create(['email' => 'jane.smith@example.edu.au', 'oidc_subject' => null]);

        $user = app(UserProvisioningService::class)->provision([
            'sub' => 'oidc-subject-123', 'email' => 'jane.smith@example.edu.au', 'name' => 'Jane Smith',
        ]);

        $this->assertSame($preCreated->id, $user->id);
        $this->assertSame('oidc-subject-123', $user->fresh()->oidc_subject);
        $this->assertNotNull($user->first_login_at);
    }

    public function test_returning_login_matches_by_subject_even_if_email_changed(): void
    {
        $existing = User::factory()->create(['email' => 'old@example.edu.au', 'oidc_subject' => 'sub-1']);

        $user = app(UserProvisioningService::class)->provision([
            'sub' => 'sub-1', 'email' => 'new@example.edu.au', 'name' => 'Renamed',
        ]);

        $this->assertSame($existing->id, $user->id);
    }

    public function test_an_email_already_linked_to_a_different_subject_is_rejected_not_taken_over(): void
    {
        User::factory()->create(['email' => 'shared@example.edu.au', 'oidc_subject' => 'sub-original']);

        $this->expectException(OidcIdentityException::class);

        app(UserProvisioningService::class)->provision([
            'sub' => 'sub-attacker', 'email' => 'shared@example.edu.au', 'name' => 'Impersonator',
        ]);
    }

    public function test_a_disabled_pre_created_account_cannot_log_in(): void
    {
        User::factory()->disabled()->create(['email' => 'disabled@example.edu.au', 'oidc_subject' => null]);

        $this->expectException(OidcIdentityException::class);

        app(UserProvisioningService::class)->provision([
            'sub' => 'sub-new', 'email' => 'disabled@example.edu.au', 'name' => 'Disabled User',
        ]);
    }

    public function test_an_unknown_email_within_the_allowed_domain_is_auto_provisioned_as_a_basic_user(): void
    {
        config(['oidc.allowed_domains' => ['example.edu.au']]);

        $user = app(UserProvisioningService::class)->provision([
            'sub' => 'sub-brand-new', 'email' => 'brand.new@example.edu.au', 'name' => 'Brand New',
        ]);

        $this->assertTrue($user->hasRole('user'));
        $this->assertFalse($user->hasRole('administrator'));
    }

    public function test_a_domain_outside_the_allow_list_is_rejected(): void
    {
        config(['oidc.allowed_domains' => ['example.edu.au']]);

        $this->expectException(OidcIdentityException::class);

        app(UserProvisioningService::class)->provision([
            'sub' => 'sub-outsider', 'email' => 'someone@gmail.com', 'name' => 'Outsider',
        ]);
    }
}
