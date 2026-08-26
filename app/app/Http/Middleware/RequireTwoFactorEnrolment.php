<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Forces an authenticated account that must have 2FA (local password + a
 * privileged role — see User::requiresTwoFactor) to finish enrolment before it
 * can use the rest of the app. Pure-SSO accounts never reach this — their
 * identity provider owns MFA.
 */
class RequireTwoFactorEnrolment
{
    /** Routes reachable while enrolment is still outstanding. */
    private const ALLOWED = [
        'two-factor.setup',
        'two-factor.setup.confirm',
        'auth.logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user || ! $user->requiresTwoFactor() || $user->hasTwoFactorEnabled()) {
            return $next($request);
        }

        if ($request->routeIs(self::ALLOWED)) {
            return $next($request);
        }

        return redirect()->route('two-factor.setup');
    }
}
