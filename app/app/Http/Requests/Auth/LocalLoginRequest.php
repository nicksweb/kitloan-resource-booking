<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LocalLoginRequest extends FormRequest
{
    /** Failed attempts against one account (any IP) before it is hard-locked. */
    public const MAX_ACCOUNT_FAILURES = 10;

    /** How long a hard lock lasts. */
    public const LOCK_MINUTES = 15;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Verify the submitted credentials WITHOUT logging anyone in. Only an
     * enabled, unlocked administrator with a local password succeeds. The
     * caller decides what happens next (straight in, or a 2FA challenge).
     *
     * Two independent limiters plus a durable per-account lock:
     *  - IP+email, short cooldown — absorbs ordinary mistyped passwords.
     *  - email-only, longer window — can't be dodged by rotating IPs.
     *  - `users.locked_until`, set on the Nth email-scoped failure — survives a
     *    cache flush and is what a distributed brute-force run actually trips.
     *
     * @throws ValidationException on any failure or while rate-limited/locked
     */
    public function authenticate(AuditLogger $auditLogger): User
    {
        $this->ensureIsNotRateLimited();

        $user = User::where('email', $this->string('email'))->first();

        if ($user && $user->isLocked()) {
            $minutes = max(1, (int) ceil(now()->diffInSeconds($user->locked_until, false) / 60));

            throw ValidationException::withMessages([
                'email' => "This account is temporarily locked after repeated failed sign-ins. Try again in about {$minutes} minute(s).",
            ]);
        }

        $valid = $user
            && $user->enabled
            && $user->hasRole('administrator')
            && $user->password !== null
            && Hash::check((string) $this->string('password'), $user->password);

        if (! $valid) {
            RateLimiter::hit($this->throttleKey(), 60);
            $emailAttempts = RateLimiter::hit($this->emailThrottleKey(), self::LOCK_MINUTES * 60);

            $auditLogger->log(
                'auth.local_login_failed',
                "Failed local-login attempt for {$this->string('email')} from {$this->ip()}"
            );

            if ($emailAttempts >= self::MAX_ACCOUNT_FAILURES) {
                $this->hardLock($user, $auditLogger);
            }

            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
        RateLimiter::clear($this->emailThrottleKey());

        if ($user->locked_until || $user->two_factor_failed_attempts) {
            $user->forceFill(['locked_until' => null, 'two_factor_failed_attempts' => 0])->save();
        }

        return $user;
    }

    /**
     * Called once an account has racked up MAX_ACCOUNT_FAILURES failures
     * regardless of source IP — the signature of a distributed / credential-
     * stuffing run rather than one person mistyping. Locks the account and
     * pings IT.
     */
    private function hardLock(?User $user, AuditLogger $auditLogger): void
    {
        $email = (string) $this->string('email');

        $auditLogger->log(
            'auth.local_login_locked',
            "Local login for {$email} locked for ".self::LOCK_MINUTES." minutes after ".self::MAX_ACCOUNT_FAILURES." failed attempts — possible brute-force attempt"
        );

        if ($user) {
            $user->forceFill(['locked_until' => now()->addMinutes(self::LOCK_MINUTES)])->save();
        }

        $itAddress = app(\App\Settings\SettingsRepository::class)->get('it_notification_address');
        if ($itAddress) {
            try {
                \Illuminate\Support\Facades\Mail::raw(
                    "Local (break-glass) login for {$email} was locked for ".self::LOCK_MINUTES." minutes at ".now()->toDateTimeString()." after ".self::MAX_ACCOUNT_FAILURES." failed attempts from {$this->ip()}.",
                    fn ($m) => $m->to($itAddress)->subject('Kitloan: local login locked after repeated failures')
                );
            } catch (\Throwable) {
                // A broken mail server must not swallow the lockout itself.
            }
        }
    }

    public function ensureIsNotRateLimited(): void
    {
        if (RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            event(new Lockout($this));
            $seconds = RateLimiter::availableIn($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => "Too many attempts. Try again in {$seconds} seconds.",
            ]);
        }

        if (RateLimiter::tooManyAttempts($this->emailThrottleKey(), self::MAX_ACCOUNT_FAILURES)) {
            event(new Lockout($this));
            $seconds = RateLimiter::availableIn($this->emailThrottleKey());

            throw ValidationException::withMessages([
                'email' => "Too many attempts against this account. Try again in {$seconds} seconds.",
            ]);
        }
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }

    public function emailThrottleKey(): string
    {
        return 'local-login-email|'.Str::transliterate(Str::lower($this->string('email')));
    }
}
