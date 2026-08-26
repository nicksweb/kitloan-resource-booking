<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $databaseHealthy = true;
        try {
            DB::select('select 1');
        } catch (\Throwable) {
            $databaseHealthy = false;
        }

        $body = [
            'application' => 'healthy',
            'version' => config('version.app'),
            'database' => $databaseHealthy ? 'healthy' : 'unhealthy',
            'integrations' => [
                // Snipe-IT availability is reported separately and never fails
                // the overall health check — the app must keep working on
                // cached data if the integration is down.
                'snipeit' => config('snipeit.enabled') ? 'enabled' : 'disabled',
            ],
        ];

        return response()->json($body, $databaseHealthy ? 200 : 503);
    }
}
