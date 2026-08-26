<?php

namespace App\Console\Commands;

use App\Settings\SettingsRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * One command to bring a deployed instance up to the version in this image.
 * Safe to re-run. Invoked by the `migrate` service in docker-compose.yml on
 * every `docker compose up`, and documented for manual runs in
 * docs/UPGRADING.md.
 *
 * Steps, in order, aborting on the first failure:
 *   1. Compatibility check — refuse if the instance is too old to upgrade
 *      directly (would skip a contract-half schema migration).
 *   2. Run migrations.
 *   3. Backfill roles + settings rows added by this release (idempotent).
 *   4. Clear every compiled cache (views/config/routes/events) — this is what
 *      stops a stale compiled Blade template surviving an upgrade.
 *   5. Tell running queue workers to restart so they pick up new code.
 *   6. Record the now-installed version.
 */
class UpgradeKitloan extends Command
{
    protected $signature = 'kitloan:upgrade {--skip-migrations : Run everything except database migrations}';

    protected $description = 'Migrate, backfill and clear caches to finish an upgrade to this release';

    public function handle(SettingsRepository $settings): int
    {
        $target = (string) config('version.app');
        $minFrom = (string) config('version.min_upgrade_from');
        $key = (string) config('version.stored_version_key');
        $current = (string) $settings->get($key, '1.0.0');

        $this->line("Kitloan upgrade: <info>{$current}</info> → <info>{$target}</info>");

        if (version_compare($current, $minFrom, '<')) {
            $this->error("This release can only be applied to instances on {$minFrom} or newer (found {$current}).");
            $this->line('Upgrade to an intermediate tagged release first — see docs/UPGRADING.md.');

            return self::FAILURE;
        }

        if (version_compare($current, $target, '>')) {
            $this->warn("The recorded version ({$current}) is newer than this image ({$target}). Continuing, but check you deployed the right tag.");
        }

        $steps = [];

        if (! $this->option('skip-migrations')) {
            $steps['Running migrations'] = fn () => Artisan::call('migrate', ['--force' => true], $this->output);
        }

        $steps['Backfilling roles'] = fn () => Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RoleSeeder', '--force' => true], $this->output);
        $steps['Backfilling settings'] = fn () => Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\SettingsSeeder', '--force' => true], $this->output);
        $steps['Backfilling message templates'] = fn () => Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\MessageTemplateSeeder', '--force' => true], $this->output);
        // view:clear matters most: compiled Blade lives on the shared
        // `app_storage` volume, so clearing it here reaches the app/queue/
        // scheduler containers too. The others clear this container's own
        // bootstrap/cache — Laravel recomputes config/routes per-boot when no
        // cache file is present, which is the state we want after an upgrade.
        $steps['Clearing compiled caches'] = function () {
            foreach (['view:clear', 'config:clear', 'route:clear', 'event:clear'] as $cmd) {
                Artisan::call($cmd, [], $this->output);
            }
        };
        $steps['Restarting queue workers'] = fn () => Artisan::call('queue:restart', [], $this->output);

        foreach ($steps as $label => $step) {
            $this->line("→ {$label}…");
            try {
                $step();
            } catch (\Throwable $e) {
                $this->error("{$label} failed: {$e->getMessage()}");

                return self::FAILURE;
            }
        }

        $settings->set($key, $target);
        $settings->forgetCache();

        $this->info("Upgrade complete — instance is now on {$target}.");

        return self::SUCCESS;
    }
}
