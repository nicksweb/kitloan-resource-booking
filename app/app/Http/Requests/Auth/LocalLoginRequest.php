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

            $auditLogger->log(
                'auth.local_login_failed',
                "Failed local-login attempt for {$this->string('email')} from {$this->ip()}"
            );

            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        $auditLogger->log(
            'auth.local_login_succeeded',
            "{$user->name} signed in via local emergency login from {$this->ip()}",
            $user,
        );
    }

    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => "Too many attempts. Try again in {$seconds} seconds.",
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
