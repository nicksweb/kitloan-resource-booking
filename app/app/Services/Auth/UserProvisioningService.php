<?php

namespace App\Services\Auth;

use App\Exceptions\OidcIdentityException;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Str;

/**
 * Matches an incoming OIDC identity to a local account. See README for the
 * matching rules — in short: the immutable `sub` claim is authoritative once
 * established; email is only used to link a *pre-created, not-yet-logged-in*
 * account, and a collision (email already linked to a different subject) is
 * rejected rather than silently resolved.
 */
class UserProvisioningService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @param  array{sub: string, email: ?string, name: ?string}  $claims
     *
     * @throws OidcIdentityException
     */
    public function provision(array $claims): User
    {
        $sub = $claims['sub'];
        $email = $claims['email'];
        $name = $claims['name'] ?: $email;

        if ($sub === '' || ! $email) {
            throw new OidcIdentityException('The identity provider did not return the required subject/email claims.');
        }

        $allowedDomains = config('oidc.allowed_domains');
        if ($allowedDomains) {
            $domain = Str::after($email, '@');
            if (! in_array($domain, $allowedDomains, true)) {
                throw new OidcIdentityException("The email domain \"{$domain}\" is not permitted to sign in to this application.");
            }
        }

        if ($existing = User::where('oidc_subject', $sub)->first()) {
            return $this->completeLogin($existing, $name);
        }

        $byEmail = User::where('email', $email)->first();

        if ($byEmail) {
            if ($byEmail->oidc_subject !== null && $byEmail->oidc_subject !== $sub) {
                $this->auditLogger->log(
                    'auth.identity_collision',
                    "Login blocked: {$email} is already linked to a different identity ({$byEmail->oidc_subject} vs {$sub}).",
                );

                throw new OidcIdentityException(
                    'This email is already linked to a different sign-in identity. Contact IT for assistance.'
                );
            }

            if (! $byEmail->enabled) {
                throw new OidcIdentityException('Your account has been disabled. Contact IT for assistance.');
            }

            $byEmail->oidc_subject = $sub;

            return $this->completeLogin($byEmail, $name, firstLogin: true);
        }

        // No pre-created account and no existing subject — provision a new,
        // least-privilege User account automatically (the domain restriction
        // above already scopes this to trusted school accounts).
        $user = new User([
            'name' => $name,
            'email' => $email,
            'oidc_subject' => $sub,
            'enabled' => true,
        ]);
        $user->save();
        $user->assignRole('user');

        $this->auditLogger->log('auth.user_provisioned', "New account auto-provisioned for {$email}.");

        return $this->completeLogin($user, $name, firstLogin: true);
    }

    private function completeLogin(User $user, string $name, bool $firstLogin = false): User
    {
        $user->name = $name;
        $user->last_login_at = now();
        if ($firstLogin || ! $user->first_login_at) {
            $user->first_login_at ??= now();
        }
        $user->save();

        return $user;
    }
}
