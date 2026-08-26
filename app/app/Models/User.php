<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'oidc_subject', 'enabled', 'receives_daily_summary'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, SoftDeletes;

    /** Roles for which local (non-SSO) sign-in must be protected by TOTP 2FA. */
    public const TWO_FACTOR_REQUIRED_ROLES = ['administrator'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'enabled' => 'boolean',
            'receives_daily_summary' => 'boolean',
            'first_login_at' => 'datetime',
            'last_login_at' => 'datetime',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'locked_until' => 'datetime',
        ];
    }

    public function bookingsOwned(): HasMany
    {
        return $this->hasMany(Booking::class, 'booked_by_user_id');
    }

    public function bookingsCreated(): HasMany
    {
        return $this->hasMany(Booking::class, 'created_by_user_id');
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /** True once the account has a usable local (break-glass) password. */
    public function usesLocalPassword(): bool
    {
        return $this->password !== null;
    }

    /** True for an account that only ever signs in via the identity provider. */
    public function isSsoOnly(): bool
    {
        return $this->oidc_subject !== null && $this->password === null;
    }

    /**
     * Whether this account must have TOTP 2FA. Applies to accounts that can
     * sign in locally with a password and hold a role in
     * TWO_FACTOR_REQUIRED_ROLES. Pure-SSO accounts are exempt — their identity
     * provider already enforces MFA.
     */
    public function requiresTwoFactor(): bool
    {
        return $this->usesLocalPassword() && $this->hasAnyRole(self::TWO_FACTOR_REQUIRED_ROLES);
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_confirmed_at !== null;
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }
}
