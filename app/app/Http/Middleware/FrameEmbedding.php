<?php

namespace App\Http\Middleware;

use App\Settings\SettingsRepository;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controls whether — and by whom — the app may be embedded in an <iframe>.
 *
 * Default (embedding disabled): `X-Frame-Options: SAMEORIGIN` plus a
 * `frame-ancestors 'self'` CSP directive — the app can only frame itself.
 * This replaces the static header nginx used to send, which couldn't be made
 * conditional.
 *
 * Embedding enabled: the allow-listed parent origins are added to
 * `frame-ancestors` and `X-Frame-Options` is dropped (it can't express an
 * allow-list portably). A visitor hitting the app with `?embed=1` is
 * remembered as embedded for the rest of the session, which trims the app
 * chrome and lets the login screen try a silent SSO sign-in before showing a
 * button (see OidcController::silent).
 */
class FrameEmbedding
{
    public function __construct(private readonly SettingsRepository $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->query('embed') !== null) {
            $request->session()->put('embedded', $request->boolean('embed', true));
        } elseif ($request->session()->has('embedded') && $this->isTopLevelNavigation($request)) {
            // The visitor loaded the app straight into a normal browser tab
            // (not inside a frame) and without the ?embed marker — they are no
            // longer embedded. Without this, a session that was ever embedded
            // keeps its chrome trimmed forever, even in a plain tab.
            $request->session()->forget('embedded');
        }

        /** @var Response $response */
        $response = $next($request);

        // Baseline hardening headers, emitted by the app itself so a bare
        // self-host (no CDN / reverse proxy adding them) is still covered.
        // Only set when absent, so an edge proxy's own value isn't overridden.
        if (! $response->headers->has('X-Content-Type-Options')) {
            $response->headers->set('X-Content-Type-Options', 'nosniff');
        }
        if (! $response->headers->has('Referrer-Policy')) {
            $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        }

        $enabled = (bool) $this->settings->get('embedding_enabled', false);
        $origins = $this->allowedOrigins();

        if ($enabled && $origins !== []) {
            $response->headers->set(
                'Content-Security-Policy',
                "frame-ancestors 'self' ".implode(' ', $origins),
            );
            $response->headers->remove('X-Frame-Options');
        } else {
            $response->headers->set('Content-Security-Policy', "frame-ancestors 'self'");
            $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        }

        return $response;
    }

    /**
     * True only for a genuine top-level document load — a browser tab pointed
     * straight at a URL. Content loading inside an <iframe> reports
     * `Sec-Fetch-Dest: iframe`, and Livewire's wire:navigate fetches report
     * `empty`, so neither trips this. The OIDC round-trip is excluded because
     * its callback can arrive as a top-level navigation even for an embedded
     * session. Browsers that don't send Sec-Fetch-Dest (pre-2020, or plain
     * HTTP) simply keep the previous behaviour.
     */
    private function isTopLevelNavigation(Request $request): bool
    {
        return $request->isMethod('GET')
            && $request->header('Sec-Fetch-Dest') === 'document'
            && ! $request->routeIs('auth.*');
    }

    /**
     * Parse the admin-entered origin list. Accepts newline- and/or
     * comma-separated entries; keeps only well-formed scheme://host[:port]
     * values and normalises away any path/trailing slash.
     *
     * @return list<string>
     */
    private function allowedOrigins(): array
    {
        $raw = (string) $this->settings->get('embedding_allowed_origins', '');

        return collect(preg_split('/[\s,]+/', $raw))
            ->map(fn ($o) => trim((string) $o))
            ->filter()
            ->map(function (string $o) {
                $parts = parse_url($o);
                if (empty($parts['scheme']) || empty($parts['host'])) {
                    return null;
                }
                $origin = $parts['scheme'].'://'.$parts['host'];

                return isset($parts['port']) ? $origin.':'.$parts['port'] : $origin;
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
