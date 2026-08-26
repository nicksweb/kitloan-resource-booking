<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LocalLoginRequest;
use App\Services\Audit\AuditLogger;
use App\Settings\SettingsRepository;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class LocalLoginController extends Controller
{
    public function show(SettingsRepository $settings): View
    {
        abort_unless($this->isEnabled($settings), 404);

        return view('auth.local-login');
    }

    public function login(LocalLoginRequest $request, SettingsRepository $settings, AuditLogger $auditLogger): RedirectResponse
    {
        abort_unless($this->isEnabled($settings), 404);

        $user = $request->authenticate($auditLogger);

        // Password is correct. If the account has TOTP 2FA set up, don't
        // complete the login yet — hand off to the challenge screen. If it's
        // required but not yet set up, log in and let RequireTwoFactorEnrolment
        // force enrolment on the next request.
        if ($user->hasTwoFactorEnabled()) {
            $request->session()->put('login.2fa.user_id', $user->id);
            $request->session()->put('login.2fa.remember', false);

            return redirect()->route('two-factor.challenge');
        }

        Auth::login($user);
        $request->session()->regenerate();

        $auditLogger->log(
            'auth.local_login_succeeded',
            "{$user->name} signed in via local emergency login from {$request->ip()}",
            $user,
        );

        return redirect()->route('home');
    }

    /**
     * The env flag is the infra-level "does this deployment even support
     * local login" switch (off by default, requires touching the server).
     * The setting is the day-to-day on/off admins flip from the GUI —
     * both must be true for the route to be reachable.
     */
    private function isEnabled(SettingsRepository $settings): bool
    {
        return config('auth.local_login.enabled') && (bool) $settings->get('local_login_enabled', true);
    }
}
