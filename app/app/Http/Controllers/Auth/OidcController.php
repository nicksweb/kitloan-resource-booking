<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\OidcIdentityException;
use App\Http\Controllers\Controller;
use App\Services\Auth\UserProvisioningService;
use App\Services\Oidc\OidcClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class OidcController extends Controller
{
    public function showLogin(Request $request): \Illuminate\Contracts\View\View
    {
        return view('auth.login');
    }

    public function redirect(Request $request, OidcClient $client): RedirectResponse
    {
        if (! config('oidc.enabled')) {
            abort(404);
        }

        [$url, $state] = $client->authorizationUrl();

        $request->session()->put('oidc.state', $state);
        $request->session()->put('oidc.intended', $request->query('redirect'));

        return redirect()->away($url);
    }

    public function callback(Request $request, OidcClient $client, UserProvisioningService $provisioning): RedirectResponse
    {
        $expectedState = $request->session()->pull('oidc.state');
        $intended = $request->session()->pull('oidc.intended');

        if (! $expectedState || $request->query('state') !== $expectedState) {
            return redirect()->route('auth.login')->with('error', 'Your sign-in session expired. Please try again.');
        }

        if ($request->query('error')) {
            // Surfaced server-side only (never to the visitor — it can contain
            // provider-internal detail) so a misconfiguration is diagnosable
            // from `docker compose logs app` instead of only the access log.
            Log::warning('OIDC provider returned an error on callback', [
                'error' => $request->query('error'),
                'error_description' => $request->query('error_description') ?? $request->query('error_message'),
            ]);

            return redirect()->route('auth.login')->with('error', 'Sign-in was cancelled or denied.');
        }

        try {
            $token = $client->exchangeCode((string) $request->query('code'));
            $claims = $client->claims($token);
            $user = $provisioning->provision($claims);
        } catch (OidcIdentityException $e) {
            return redirect()->route('auth.login')->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            Log::error('OIDC callback failed', ['exception' => $e->getMessage()]);

            return redirect()->route('auth.login')->with('error', 'Sign-in failed. Please try again or contact IT.');
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->to($intended && str_starts_with($intended, '/') ? $intended : route('home'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('auth.login');
    }
}
