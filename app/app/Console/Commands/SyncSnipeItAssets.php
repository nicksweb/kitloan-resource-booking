<?php

namespace App\Console\Commands;

use App\Services\SnipeIt\SnipeItSyncService;
use Illuminate\Console\Command;

class SyncSnipeItAssets extends Command
{
    protected $signature = 'snipeit:sync';

    protected $description = 'Refresh the local snapshot of Snipe-IT-linked resources';

    public function handle(SnipeItSyncService $syncService): int
    {
        if (! config('snipeit.enabled')) {
            $this->info('Snipe-IT integration is disabled — skipping.');

            return self::SUCCESS;
        }

        $log = $syncService->syncAll();

        $this->info("Sync {$log->status}: {$log->items_synced} asset(s) refreshed.");

        if ($log->error_message) {
            $this->warn($log->error_message);
        }

        return $log->status === 'failed' ? self::FAILURE : self::SUCCESS;
    }
}
