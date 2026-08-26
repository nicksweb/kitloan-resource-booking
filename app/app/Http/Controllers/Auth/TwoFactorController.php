<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LocalLoginRequest;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\TwoFactorAuthenticator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TwoFactorController extends Controller
{
    public function __construct(
        private readonly TwoFactorAuthenticator $authenticator,
        private readonly AuditLogger $audit,
    ) {}

    // ---- Enrolment (already authenticated, being forced to set up) --------

    public function setup(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user->hasTwoFactorEnabled()) {
            return redirect()->route('home');
        }

        $secret = $request->session()->get('2fa.pending_secret');
        if (! $secret) {
            $secret = $this->authenticator->generateSecret();
            $request->session()->put('2fa.pending_secret', $secret);
        }

        $recoveryCodes = $request->session()->get('2fa.pending_recovery');
        if (! $recoveryCodes) {
            $recoveryCodes = $this->authenticator->generateRecoveryCodes();
            $request->session()->put('2fa.pending_recovery', $recoveryCodes);
        }

        return view('auth.two-factor-setup', [
            'qrSvg' => $this->authenticator->qrCodeSvg($user, $secret),
            'secret' => $secret,
            'recoveryCodes' => $recoveryCodes,
            'required' => $user->requiresTwoFactor(),
        ]);
    }

    public function confirmSetup(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string']]);
        $user = $request->user();

        $secret = $request->session()->get('2fa.pending_secret');
        $recoveryCodes = (array) $request->session()->get('2fa.pending_recovery', []);

        if (! $secret || ! $this->authenticator->verify($secret, (string) $request->string('code'))) {
            return back()->withErrors(['code' => 'That code was not valid. Check the time on your device and try again.']);
        }

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $this->authenticator->hashRecoveryCodes($recoveryCodes),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $request->session()->forget(['2fa.pending_secret', '2fa.pending_recovery']);

        $this->audit->log('auth.two_factor_enabled', "{$user->name} enabled two-factor authentication", $user);

        return redirect()->route('home')->with('success', 'Two-factor authentication is now switched on for your account.');
    }

    // ---- Challenge (password accepted, not yet logged in) ----------------

    public function challenge(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('login.2fa.user_id')) {
            return redirect()->route('auth.login');
        }

        return view('auth.two-factor-challenge');
    }

    public function verifyChallenge(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string']]);

        $user = User::find($request->session()->get('login.2fa.user_id'));
        if (! $user) {
            return redirect()->route('auth.login');
        }

        if ($user->isLocked()) {
            $request->session()->forget('login.2fa.user_id');

            return redirect()->route('auth.login')->with('error', 'This account is temporarily locked. Try again later.');
        }

        $code = (string) $request->string('code');
        $ok = ($user->two_factor_secret && $this->authenticator->verify($user->two_factor_secret, $code))
            || $this->authenticator->consumeRecoveryCode($user, $code);

        if (! $ok) {
            $attempts = (int) $user->two_factor_failed_attempts + 1;
            $user->forceFill(['two_factor_failed_attempts' => $attempts])->save();

            $this->audit->log('auth.local_login_failed', "Failed 2FA code for {$user->email} from {$request->ip()}");

            if ($attempts >= LocalLoginRequest::MAX_ACCOUNT_FAILURES) {
                $user->forceFill([
                    'locked_until' => now()->addMinutes(LocalLoginRequest::LOCK_MINUTES),
                    'two_factor_failed_attempts' => 0,
                ])->save();
                $request->session()->forget('login.2fa.user_id');
                $this->audit->log('auth.local_login_locked', "Local login for {$user->email} locked after ".LocalLoginRequest::MAX_ACCOUNT_FAILURES.' failed 2FA codes');

                return redirect()->route('auth.login')->with('error', 'Too many incorrect codes — this account is temporarily locked.');
            }

            return back()->withErrors(['code' => 'That code was not valid.']);
        }

        $user->forceFill(['two_factor_failed_attempts' => 0, 'locked_until' => null])->save();
        $request->session()->forget('login.2fa.user_id');

        Auth::login($user);
        $request->session()->regenerate();

        $this->audit->log(
            'auth.local_login_succeeded',
            "{$user->name} signed in via local emergency login (2FA verified) from {$request->ip()}",
            $user,
        );

        return redirect()->route('home');
    }
}
