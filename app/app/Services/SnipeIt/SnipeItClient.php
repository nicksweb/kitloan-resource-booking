<?php

namespace App\Services\SnipeIt;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Thin wrapper over the Snipe-IT REST API v1. Every method normalizes
 * Snipe-IT's response shape into a flat array so callers never touch the
 * raw API response — that keeps Snipe-IT's data model from leaking into the
 * rest of the app, and makes this trivial to mock in tests.
 */
class SnipeItClient
{
    private function http(): PendingRequest
    {
        return Http::baseUrl(config('snipeit.url').'/api/v1')
            ->withToken(config('snipeit.api_token'))
            ->acceptJson()
            ->timeout(config('snipeit.timeout', 10));
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function testConnection(): array
    {
        try {
            $response = $this->http()->get('/hardware', ['limit' => 1]);
            if ($response->successful()) {
                return ['ok' => true, 'message' => 'Connected.'];
            }

            return ['ok' => false, 'message' => "Snipe-IT responded with HTTP {$response->status()}."];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function searchHardware(string $search = '', int $limit = 50): array
    {
        $response = $this->http()->get('/hardware', array_filter([
            'search' => $search ?: null,
            'limit' => $limit,
        ]));

        $response->throw();

        return array_map($this->normalize(...), $response->json('rows') ?? []);
    }

    public function getHardware(int $id): ?array
    {
        $response = $this->http()->get("/hardware/{$id}");

        if ($response->status() === 404) {
            return null;
        }

        $response->throw();

        return $this->normalize($response->json());
    }

    private function normalize(array $row): array
    {
        return [
            'id' => $row['id'] ?? null,
            'asset_tag' => $row['asset_tag'] ?? null,
            'serial' => $row['serial'] ?? null,
            'name' => $row['name'] ?: ($row['asset_tag'] ?? null),
            'model' => $row['model']['name'] ?? null,
            'status' => $row['status_label']['name'] ?? null,
            'location' => $row['location']['name'] ?? null,
        ];
    }
}
