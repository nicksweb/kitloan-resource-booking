<?php

namespace App\Services\SnipeIt;

use App\Models\ExternalAssetLink;
use App\Models\IntegrationSyncLog;
use Illuminate\Support\Facades\Log;

/**
 * Refreshes the local snapshot of already-linked Snipe-IT assets. Never
 * touches resource_pool_id, resource status, notes, or anything booking
 * related — only the external_* fields Snipe-IT actually owns. If Snipe-IT
 * is unreachable, the run is logged as failed and the app keeps using the
 * last-known snapshot; nothing here can break booking.
 */
class SnipeItSyncService
{
    public function __construct(private readonly SnipeItClient $client) {}

    public function syncAll(): IntegrationSyncLog
    {
        $log = IntegrationSyncLog::create([
            'integration' => 'snipeit',
            'status' => 'running',
            'started_at' => now(),
        ]);

        $synced = 0;
        $errors = [];

        try {
            ExternalAssetLink::where('external_source', 'snipeit')->chunk(25, function ($links) use (&$synced, &$errors) {
                foreach ($links as $link) {
                    try {
                        $asset = $this->client->getHardware((int) $link->external_id);

                        if ($asset === null) {
                            $link->update(['missing_since' => $link->missing_since ?? now()]);

                            continue;
                        }

                        $link->update([
                            'asset_tag' => $asset['asset_tag'],
                            'serial' => $asset['serial'],
                            'name' => $asset['name'],
                            'model' => $asset['model'],
                            'status' => $asset['status'],
                            'location' => $asset['location'],
                            'missing_since' => null,
                            'last_synced_at' => now(),
                            'external_metadata' => $asset,
                        ]);

                        $synced++;
                    } catch (\Throwable $e) {
                        $errors[] = "Asset {$link->external_id}: {$e->getMessage()}";
                    }
                }
            });
        } catch (\Throwable $e) {
            $errors[] = $e->getMessage();
        }

        if ($errors) {
            Log::warning('Snipe-IT sync completed with errors', ['errors' => $errors]);
        }

        $log->update([
            'status' => $errors === [] ? 'success' : ($synced > 0 ? 'partial' : 'failed'),
            'finished_at' => now(),
            'items_synced' => $synced,
            'error_message' => $errors ? implode("\n", $errors) : null,
        ]);

        return $log;
    }
}
