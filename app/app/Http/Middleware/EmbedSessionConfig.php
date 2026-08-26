<?php

namespace App\Http\Middleware;

use App\Settings\SettingsRepository;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs before StartSession. When iframe embedding is enabled, a browser will
 * only send the session cookie back inside a cross-site frame if it is
 * `SameSite=None; Secure`, so promote it here (unless the deployment has
 * already pinned SESSION_SAME_SITE / SESSION_SECURE_COOKIE in the environment,
 * which always wins).
 *
 * Cheap and safe on non-embedded deployments: if the setting is off, this is a
 * single cached lookup and a no-op. Wrapped in a catch so a request that
 * somehow reaches the app before the settings table exists (fresh install,
 * mid-migration) still succeeds.
 */
class EmbedSessionConfig
{
    public function __construct(private readonly SettingsRepository $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        try {
            if ((bool) $this->settings->get('embedding_enabled', false)) {
                if (env('SESSION_SAME_SITE') === null) {
                    config(['session.same_site' => 'none']);
                }
                if (env('SESSION_SECURE_COOKIE') === null) {
                    config(['session.secure' => true]);
                }
            }
        } catch (\Throwable) {
            // Settings unavailable this early — fall back to configured defaults.
        }

        return $next($request);
    }
}
