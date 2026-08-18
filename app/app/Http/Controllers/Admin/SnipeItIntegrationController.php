<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExternalAssetLink;
use App\Models\IntegrationSyncLog;
use App\Services\SnipeIt\SnipeItClient;
use App\Services\SnipeIt\SnipeItSyncService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class SnipeItIntegrationController extends Controller
{
    public function show(): View
    {
        return view('admin.integrations.snipeit', [
            'enabled' => config('snipeit.enabled'),
            'url' => config('snipeit.url'),
            'linkedCount' => ExternalAssetLink::where('external_source', 'snipeit')->count(),
            'lastLog' => IntegrationSyncLog::where('integration', 'snipeit')->latest('started_at')->first(),
        ]);
    }

    public function test(SnipeItClient $client): RedirectResponse
    {
        $result = $client->testConnection();

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    public function sync(SnipeItSyncService $syncService): RedirectResponse
    {
        $log = $syncService->syncAll();

        return back()->with(
            $log->status === 'failed' ? 'error' : 'success',
            "Sync {$log->status}: {$log->items_synced} asset(s) refreshed."
        );
    }
}
