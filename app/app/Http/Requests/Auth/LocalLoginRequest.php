<?php

namespace App\Http\Requests\Auth;

use App\Services\Audit\AuditLogger;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LocalLoginRequest extends FormRequest
{
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
     * Attempt to authenticate. Only succeeds for an enabled user that holds
     * the administrator role and has a local password set — Auth::attempt
     * already fails closed on a null password hash, but the role/enabled
     * checks are enforced explicitly here too, since this path exists
     * specifically to bypass the normal OIDC gate and deserves no ambiguity.
     */
    public function authenticate(AuditLogger $auditLogger): void
    {
        $this->ensureIsNotRateLimited();

        $user = \App\Models\User::where('email', $this->string('email'))->first();

        $valid = $user
            && $user->enabled
            && $user->hasRole('administrator')
            && Auth::attempt($this->only('email', 'password'), false);

        if (! $valid) {
            RateLimiter::hit($this->throttleKey(), 60);
            $emailAttempts = RateLimiter::hit($this->emailThrottleKey(), 900);

            $auditLogger->log(
                'auth.local_login_failed',
                "Failed local-login attempt for {$this->string('email')} from {$this->ip()}"
            );

            // Distinct from the per-IP lockout below: this fires once a single
            // account has racked up failures regardless of source IP, which is
            // what a distributed/credential-stuffing attempt looks like rather
            // than one person mistyping their password a few times.
            if ($emailAttempts === 10) {
                $auditLogger->log(
                    'auth.local_login_bruteforce_suspected',
                    "Ten failed local-login attempts against {$this->string('email')} across multiple requests — possible brute-force attempt"
                );
            }

            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
        RateLimiter::clear($this->emailThrottleKey());

        $auditLogger->log(
            'auth.local_login_succeeded',
            "{$user->name} signed in via local emergency login from {$this->ip()}",
            $user,
        );
    }

    /**
     * Two independent limiters. The IP+email one absorbs ordinary mistyped
     * passwords with a short cooldown; the email-only one has a much longer
     * window and can't be dodged by retrying from a different IP address.
     */
    public function ensureIsNotRateLimited(): void
    {
        if (RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            event(new Lockout($this));

            $seconds = RateLimiter::availableIn($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => "Too many attempts. Try again in {$seconds} seconds.",
            ]);
        }

        if (RateLimiter::tooManyAttempts($this->emailThrottleKey(), 15)) {
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
