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
        }

        /** @var Response $response */
        $response = $next($request);

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
