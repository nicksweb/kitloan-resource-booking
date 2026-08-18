<?php

namespace Tests\Feature;

use App\Livewire\Admin\ResourcePoolResources;
use App\Models\ResourcePool;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regression coverage for a real bug: the component used to re-fetch Snipe-IT
 * search results from render() unconditionally, so *any* interaction while
 * the import modal was open (checking a checkbox, etc.) fired a fresh
 * blocking API call — making the whole page feel slow. Search should only
 * be re-fetched when the search term actually changes.
 */
class ResourcePoolResourcesImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        config(['snipeit.enabled' => true, 'snipeit.url' => 'https://snipeit.example.test']);
    }

    private function fakeSearch(): void
    {
        Http::fake([
            '*/api/v1/hardware*' => Http::response([
                'total' => 1,
                'rows' => [[
                    'id' => 438, 'asset_tag' => '10562', 'serial' => '5CD123456',
                    'name' => 'Exam iPad 03', 'model' => ['name' => 'iPad 10th Gen'],
                    'status_label' => ['name' => 'Ready to Deploy'], 'location' => ['name' => 'IT Store'],
                ]],
            ], 200),
        ]);
    }

    public function test_toggling_a_checkbox_does_not_trigger_another_snipe_it_api_call(): void
    {
        $this->fakeSearch();
        $admin = User::factory()->create();
        $admin->assignRole('administrator');
        $pool = ResourcePool::factory()->create();

        Livewire::actingAs($admin)
            ->test(ResourcePoolResources::class, ['resourcePool' => $pool])
            ->call('openSnipeItImport')
            ->assertSet('snipeItResults.0.id', 438)
            ->call('toggleSnipeItAsset', 438)
            ->call('toggleSnipeItAsset', 438);

        Http::assertSentCount(1);
    }

    public function test_changing_the_search_term_triggers_exactly_one_new_call(): void
    {
        $this->fakeSearch();
        $admin = User::factory()->create();
        $admin->assignRole('administrator');
        $pool = ResourcePool::factory()->create();

        Livewire::actingAs($admin)
            ->test(ResourcePoolResources::class, ['resourcePool' => $pool])
            ->call('openSnipeItImport')
            ->set('snipeItSearch', 'ipad');

        Http::assertSentCount(2);
    }

    public function test_importing_selected_assets_makes_no_additional_api_calls(): void
    {
        $this->fakeSearch();
        $admin = User::factory()->create();
        $admin->assignRole('administrator');
        $pool = ResourcePool::factory()->create();

        Livewire::actingAs($admin)
            ->test(ResourcePoolResources::class, ['resourcePool' => $pool])
            ->call('openSnipeItImport')
            ->call('toggleSnipeItAsset', 438)
            ->call('importSelected');

        // Exactly the one search call from openSnipeItImport — importSelected
        // must reuse that data rather than calling getHardware() per asset.
        Http::assertSentCount(1);
        $this->assertDatabaseHas('external_asset_links', ['external_id' => '438']);
    }
}
