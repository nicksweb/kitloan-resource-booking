<?php

use App\Http\Middleware\EmbedSessionConfig;
use App\Http\Middleware\FrameEmbedding;
use App\Http\Middleware\EnsureUserEnabled;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\RoleMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Before StartSession: promote the session cookie to SameSite=None for
        // cross-site iframe embedding when that's enabled.
        $middleware->prependToGroup('web', EmbedSessionConfig::class);

        $middleware->appendToGroup('web', EnsureUserEnabled::class);
        // After everything: emit the frame-ancestors / X-Frame-Options headers
        // and remember an ?embed=1 visit for the rest of the session.
        $middleware->appendToGroup('web', FrameEmbedding::class);
        $middleware->alias([
            'role' => RoleMiddleware::class,
        ]);
        $middleware->redirectGuestsTo(fn () => route('auth.login'));

        // The app container is only reachable via the internal Docker network
        // (no host port is published — see docker-compose.yml), and Nginx
        // Proxy Manager is the only thing that can reach it. Trusting all
        // proxies here is safe in that topology and is required for correct
        // HTTPS/client-IP detection (audit log IPs, signed URLs, secure
        // cookies) behind NPM's TLS termination.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
