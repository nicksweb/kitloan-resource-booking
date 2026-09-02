<?php

namespace App\Livewire\Admin;

use App\Models\ExternalAssetLink;
use App\Models\Resource;
use App\Models\ResourcePool;
use App\Services\Audit\AuditLogger;
use App\Services\SnipeIt\SnipeItClient;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class ResourcePoolResources extends Component
{
    public ResourcePool $resourcePool;

    public bool $showManualForm = false;

    public string $manualName = '';

    public string $manualAssetNumber = '';

    public string $manualSerial = '';

    public bool $showSnipeItImport = false;

    public string $snipeItSearch = '';

    /** @var array<int> */
    public array $selectedSnipeItAssetIds = [];

    /**
     * The last-fetched Snipe-IT search results — a stored property, not
     * recomputed on every render. Livewire re-renders the component on
     * *every* interaction (checking a checkbox, any other click on the
     * page), and this used to trigger a fresh, blocking Snipe-IT API call
     * each time regardless of whether the search term had changed, making
     * the whole page feel slow while the import modal was open.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $snipeItResults = [];

    public function mount(ResourcePool $resourcePool): void
    {
        $this->resourcePool = $resourcePool;
    }

    public function render()
    {
        $resources = $this->resourcePool->resources()->with('externalAssetLink')->orderBy('display_order')->orderBy('name')->get();

        $alreadyLinkedIds = [];
        if ($this->snipeItResults) {
            $alreadyLinkedIds = ExternalAssetLink::where('external_source', 'snipeit')
                ->whereIn('external_id', collect($this->snipeItResults)->pluck('id'))
                ->with('resource.resourcePool')
                ->get()
                ->keyBy('external_id');
        }

        return view('livewire.admin.resource-pool-resources', [
            'resources' => $resources,
            'snipeItResults' => $this->snipeItResults,
            'alreadyLinkedIds' => $alreadyLinkedIds,
        ]);
    }

    public function addManual(): void
    {
        $this->validate([
            'manualName' => ['required', 'string', 'max:255'],
            'manualAssetNumber' => ['nullable', 'string', 'max:255'],
            'manualSerial' => ['nullable', 'string', 'max:255'],
        ]);

        Resource::create([
            'resource_pool_id' => $this->resourcePool->id,
            'name' => $this->manualName,
            'asset_number' => $this->manualAssetNumber ?: null,
            'serial' => $this->manualSerial ?: null,
            'source' => 'manual',
            'status' => 'available',
            'display_order' => Resource::where('resource_pool_id', $this->resourcePool->id)->max('display_order') + 1,
        ]);

        $this->reset(['manualName', 'manualAssetNumber', 'manualSerial']);
        $this->showManualForm = false;
        session()->flash('success', 'Resource added.');
    }

    public function openSnipeItImport(SnipeItClient $client): void
    {
        $this->showSnipeItImport = true;
        $this->searchSnipeIt($client);
    }

    /**
     * Livewire's naming convention: fires automatically when snipeItSearch
     * changes (i.e. once per debounced keystroke group), not on every render.
     */
    public function updatedSnipeItSearch(SnipeItClient $client): void
    {
        $this->searchSnipeIt($client);
    }

    private function searchSnipeIt(SnipeItClient $client): void
    {
        if (! config('snipeit.enabled')) {
            return;
        }

        try {
            $this->snipeItResults = $client->searchHardware($this->snipeItSearch);
            $this->resetErrorBag('snipeit');
        } catch (\Throwable $e) {
            $this->snipeItResults = [];
            $this->addError('snipeit', 'Could not reach Snipe-IT: '.$e->getMessage());
        }
    }

    public function toggleSnipeItAsset(int $assetId): void
    {
        if (in_array($assetId, $this->selectedSnipeItAssetIds, true)) {
            $this->selectedSnipeItAssetIds = array_values(array_diff($this->selectedSnipeItAssetIds, [$assetId]));
        } else {
            $this->selectedSnipeItAssetIds[] = $assetId;
        }
    }

    public function importSelected(): void
    {
        if (! $this->selectedSnipeItAssetIds) {
            return;
        }

        // Reuse the data from the search we already ran — the /hardware
        // search endpoint already returns everything we store locally, so
        // there's no need for a separate per-asset API round-trip here.
        $byId = collect($this->snipeItResults)->keyBy('id');

        $imported = 0;
        foreach ($this->selectedSnipeItAssetIds as $assetId) {
            if (ExternalAssetLink::where('external_source', 'snipeit')->where('external_id', $assetId)->exists()) {
                continue;
            }

            $asset = $byId->get($assetId);
            if (! $asset) {
                continue;
            }

            $resource = Resource::create([
                'resource_pool_id' => $this->resourcePool->id,
                'name' => $asset['name'] ?: ($asset['asset_tag'] ?? "Asset {$assetId}"),
                'asset_number' => $asset['asset_tag'] ?? null,
                'serial' => $asset['serial'] ?? null,
                'source' => 'snipeit',
                'status' => 'available',
                'display_order' => Resource::where('resource_pool_id', $this->resourcePool->id)->max('display_order') + 1,
            ]);

            $resource->externalAssetLink()->create([
                'external_source' => 'snipeit',
                'external_id' => (string) $assetId,
                'asset_tag' => $asset['asset_tag'] ?? null,
                'serial' => $asset['serial'] ?? null,
                'name' => $asset['name'] ?? null,
                'model' => $asset['model'] ?? null,
                'status' => $asset['status'] ?? null,
                'location' => $asset['location'] ?? null,
                'last_synced_at' => now(),
                'external_metadata' => $asset,
            ]);

            $imported++;
        }

        $this->selectedSnipeItAssetIds = [];
        $this->showSnipeItImport = false;
        $this->snipeItResults = [];
        session()->flash('success', "{$imported} asset(s) imported from Snipe-IT.");
    }

    public function setStatus(Resource $resource, string $status): void
    {
        abort_unless(in_array($status, Resource::STATUSES, true), 422);
        $resource->update(['status' => $status]);
    }

    /**
     * Soft-delete a single resource. Refused while it holds a live allocation
     * for an upcoming booking — set its status to "retired" or "unavailable"
     * instead, or wait for the booking to pass. Past bookings keep their
     * history regardless.
     */
    public function deleteResource(Resource $resource, AuditLogger $auditLogger): void
    {
        abort_unless($resource->resource_pool_id === $this->resourcePool->id, 404);

        if ($resource->user_id) {
            session()->flash('error', 'This officer is managed from their profile — they can turn "bookable as an IT officer" off there.');

            return;
        }

        $hasUpcomingAllocation = $resource->allocations()
            ->whereNull('released_at')
            ->whereHas('bookingItem.booking', fn ($q) => $q->where('lifecycle_status', 'active')->where('end_at', '>=', now()))
            ->exists();

        if ($hasUpcomingAllocation) {
            session()->flash('error', "\"{$resource->name}\" is allocated to an upcoming booking — retire it instead of deleting.");

            return;
        }

        $resource->delete();

        $auditLogger->log(
            'catalog.resource_deleted',
            auth()->user()->name." deleted resource \"{$resource->name}\" from {$this->resourcePool->name}",
            auth()->user(),
            null,
            $resource->id,
        );

        session()->flash('success', "Resource \"{$resource->name}\" deleted.");
    }
}
