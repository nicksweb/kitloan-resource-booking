<?php

namespace Tests\Feature;

use App\Models\ExternalAssetLink;
use App\Models\Resource;
use App\Models\ResourcePool;
use App\Services\SnipeIt\SnipeItClient;
use App\Services\SnipeIt\SnipeItSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SnipeItIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['snipeit.enabled' => true, 'snipeit.url' => 'https://snipeit.example.test']);
    }

    public function test_search_hardware_normalizes_the_snipe_it_response(): void
    {
        Http::fake([
            '*/api/v1/hardware*' => Http::response([
                'total' => 1,
                'rows' => [[
                    'id' => 438, 'asset_tag' => '10562', 'serial' => '5CD123456',
                    'name' => 'Exam Laptop 03', 'model' => ['name' => 'HP ProBook 440'],
                    'status_label' => ['name' => 'Ready to Deploy'], 'location' => ['name' => 'IT Store'],
                ]],
            ], 200),
        ]);

        $results = app(SnipeItClient::class)->searchHardware('10562');

        $this->assertCount(1, $results);
        $this->assertSame('HP ProBook 440', $results[0]['model']);
        $this->assertSame('10562', $results[0]['asset_tag']);
    }

    public function test_a_failed_snipe_it_api_does_not_crash_the_sync(): void
    {
        $pool = ResourcePool::factory()->create();
        $resource = Resource::factory()->create(['resource_pool_id' => $pool->id, 'source' => 'snipeit']);
        ExternalAssetLink::create([
            'resource_id' => $resource->id, 'external_source' => 'snipeit', 'external_id' => '438',
        ]);

        Http::fake(['*/api/v1/hardware/438' => Http::response('Service Unavailable', 503)]);

        $log = app(SnipeItSyncService::class)->syncAll();

        $this->assertContains($log->status, ['failed', 'partial']);
        $this->assertNotNull($log->error_message);
        // The resource and its link must still exist — a failed sync never deletes local data.
        $this->assertDatabaseHas('external_asset_links', ['resource_id' => $resource->id]);
    }

    public function test_sync_updates_external_fields_without_touching_local_booking_state(): void
    {
        $pool = ResourcePool::factory()->create();
        $resource = Resource::factory()->create([
            'resource_pool_id' => $pool->id, 'source' => 'snipeit', 'status' => 'available', 'notes' => 'do-not-touch',
        ]);
        ExternalAssetLink::create([
            'resource_id' => $resource->id, 'external_source' => 'snipeit', 'external_id' => '438',
        ]);

        Http::fake(['*/api/v1/hardware/438' => Http::response([
            'id' => 438, 'asset_tag' => '10562-NEW', 'serial' => 'SN-NEW',
            'name' => 'Exam Laptop 03', 'model' => ['name' => 'HP ProBook 440 G9'],
            'status_label' => ['name' => 'Deployed'], 'location' => ['name' => 'B12'],
        ], 200)]);

        app(SnipeItSyncService::class)->syncAll();

        $resource->refresh();
        $this->assertSame('do-not-touch', $resource->notes);
        $this->assertSame('available', $resource->status);
        $this->assertSame('10562-NEW', $resource->externalAssetLink->asset_tag);
    }

    public function test_duplicate_snipe_it_imports_are_prevented_by_a_unique_constraint(): void
    {
        $pool = ResourcePool::factory()->create();
        $resource = Resource::factory()->create(['resource_pool_id' => $pool->id]);
        ExternalAssetLink::create(['resource_id' => $resource->id, 'external_source' => 'snipeit', 'external_id' => '438']);

        $otherResource = Resource::factory()->create(['resource_pool_id' => $pool->id]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        ExternalAssetLink::create(['resource_id' => $otherResource->id, 'external_source' => 'snipeit', 'external_id' => '438']);
    }
}
