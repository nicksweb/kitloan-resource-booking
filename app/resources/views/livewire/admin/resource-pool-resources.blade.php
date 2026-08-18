<div>
    <x-admin.nav />
    <div class="flex items-center justify-between">
        <div>
            <a href="{{ route('admin.resource-pools.index') }}" class="text-xs text-indigo-600 hover:underline">&larr; Resource Pools</a>
            <h1 class="text-2xl font-semibold tracking-tight text-gray-900">{{ $resourcePool->name }} Resources</h1>
        </div>
        <div class="flex gap-2">
            @if (config('snipeit.enabled'))
                <button wire:click="openSnipeItImport" class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50">Import from Snipe-IT</button>
            @endif
            <button wire:click="$set('showManualForm', true)" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Add Manual Resource</button>
        </div>
    </div>

    <div class="mt-6 overflow-hidden rounded-xl border border-gray-200 bg-white">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead><tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                <th class="px-4 py-3">Name</th><th class="px-4 py-3">Asset Tag</th><th class="px-4 py-3">Source</th><th class="px-4 py-3">Status</th><th class="px-4 py-3"></th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($resources as $resource)
                    <tr>
                        <td class="px-4 py-3 font-medium text-gray-900">{{ $resource->name }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $resource->asset_number ?? $resource->externalAssetLink?->asset_tag ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ ucfirst($resource->source) }}</td>
                        <td class="px-4 py-3"><x-status-badge :status="$resource->status" /></td>
                        <td class="px-4 py-3 text-right">
                            <select wire:change="setStatus({{ $resource->id }}, $event.target.value)" class="rounded-md border-gray-300 text-xs">
                                @foreach (\App\Models\Resource::STATUSES as $status)
                                    <option value="{{ $status }}" @selected($resource->status === $status)>{{ ucfirst($status) }}</option>
                                @endforeach
                            </select>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($showManualForm)
        <div class="fixed inset-0 z-10 flex items-center justify-center bg-gray-900/50 p-4">
            <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
                <h2 class="text-lg font-semibold text-gray-900">Add Manual Resource</h2>
                <div class="mt-4 space-y-3">
                    <div><label class="block text-xs font-medium text-gray-700">Name</label><input type="text" wire:model="manualName" class="mt-1 block w-full rounded-md border-gray-300 text-sm">@error('manualName')<p class="text-xs text-red-600">{{ $message }}</p>@enderror</div>
                    <div><label class="block text-xs font-medium text-gray-700">Asset Number (optional)</label><input type="text" wire:model="manualAssetNumber" class="mt-1 block w-full rounded-md border-gray-300 text-sm"></div>
                    <div><label class="block text-xs font-medium text-gray-700">Serial (optional)</label><input type="text" wire:model="manualSerial" class="mt-1 block w-full rounded-md border-gray-300 text-sm"></div>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <button wire:click="$set('showManualForm', false)" class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300">Cancel</button>
                    <button wire:click="addManual" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white">Add</button>
                </div>
            </div>
        </div>
    @endif

    @if ($showSnipeItImport)
        <div class="fixed inset-0 z-10 flex items-center justify-center bg-gray-900/50 p-4">
            <div class="w-full max-w-2xl rounded-xl bg-white p-6 shadow-xl max-h-[85vh] flex flex-col">
                <h2 class="text-lg font-semibold text-gray-900">Import from Snipe-IT</h2>
                <input type="text" wire:model.live.debounce.400ms="snipeItSearch" placeholder="Search assets&hellip;" class="mt-3 block w-full rounded-md border-gray-300 text-sm">
                @error('snipeit')<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror

                <div class="mt-3 flex-1 overflow-y-auto divide-y divide-gray-100 border-y border-gray-100">
                    @forelse ($snipeItResults as $asset)
                        @php($linked = $alreadyLinkedIds[$asset['id']] ?? null)
                        <label class="flex items-center gap-3 px-2 py-2 text-sm {{ $linked ? 'opacity-50' : 'cursor-pointer hover:bg-gray-50' }}">
                            <input type="checkbox" {{ $linked ? 'disabled' : '' }}
                                   wire:click="toggleSnipeItAsset({{ $asset['id'] }})"
                                   @checked(in_array($asset['id'], $selectedSnipeItAssetIds))
                                   class="rounded border-gray-300 text-indigo-600">
                            <span class="flex-1">
                                Asset {{ $asset['asset_tag'] }} &mdash; {{ $asset['model'] }} &mdash; SN {{ $asset['serial'] }}
                            </span>
                            @if ($linked)
                                <span class="text-xs text-gray-400">Already assigned to {{ $linked->resource->resourcePool->name }}</span>
                            @endif
                        </label>
                    @empty
                        <p class="px-2 py-6 text-center text-sm text-gray-500">No assets found.</p>
                    @endforelse
                </div>

                <div class="mt-3 flex items-center justify-between">
                    <span class="text-sm text-gray-500">Selected: {{ count($selectedSnipeItAssetIds) }}</span>
                    <div class="flex gap-2">
                        <button wire:click="$set('showSnipeItImport', false)" class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300">Cancel</button>
                        <button wire:click="importSelected" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white">Add Selected Assets</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
