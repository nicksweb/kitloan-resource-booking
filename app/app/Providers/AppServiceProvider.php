<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Administrators can do everything an IT Operator can, plus catalog
        // and user/settings management (see the ability definitions below).
        Gate::before(fn ($user, string $ability) => $user->hasRole('administrator') ? true : null);

        // Structural catalog management: resource pools, resources, locations,
        // booking types, Snipe-IT integration settings.
        Gate::define('manage-catalog', fn ($user) => $user->hasRole('administrator'));

        // Day-to-day booking operations: approve/reject, allocate/substitute,
        // create on behalf of another user, mark equipment unavailable.
        Gate::define('operate-bookings', fn ($user) => $user->hasAnyRole(['administrator', 'it_operator']));

        Gate::define('manage-users', fn ($user) => $user->hasRole('administrator'));
        Gate::define('manage-settings', fn ($user) => $user->hasRole('administrator'));
        Gate::define('view-audit-log', fn ($user) => $user->hasRole('administrator'));

        if (config('app.env') === 'production' || str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }
    }
}
