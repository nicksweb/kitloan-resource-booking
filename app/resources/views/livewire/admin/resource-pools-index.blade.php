<div x-data>
    <x-admin.nav />
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold tracking-tight text-gray-900">Resource Pools</h1>
        <button wire:click="create" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Add Resource Pool</button>
    </div>

    <div class="mt-6 overflow-hidden rounded-xl border border-gray-200 bg-white">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead><tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                <th class="px-4 py-3">Name</th><th class="px-4 py-3">Mode</th><th class="px-4 py-3">Resources</th><th class="px-4 py-3">Status</th><th class="px-4 py-3"></th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($pools as $pool)
                    <tr>
                        <td class="px-4 py-3 font-medium text-gray-900">{{ $pool->name }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ ucfirst($pool->allocation_mode) }}</td>
                        <td class="px-4 py-3 text-gray-500">
                            @if ($pool->allocation_mode === 'individual')
                                <a href="{{ route('admin.resource-pools.resources', $pool) }}" class="text-indigo-600 hover:underline">{{ $pool->resources_count }} items</a>
                            @else
                                {{ $pool->quantity_total }} total
                            @endif
                        </td>
                        <td class="px-4 py-3"><x-status-badge :status="$pool->enabled ? 'available' : 'disabled'">{{ $pool->enabled ? 'Enabled' : 'Disabled' }}</x-status-badge></td>
                        <td class="px-4 py-3 text-right space-x-2">
                            <button wire:click="edit({{ $pool->id }})" class="text-indigo-600 hover:underline">Edit</button>
                            <button wire:click="toggleEnabled({{ $pool->id }})" class="text-gray-500 hover:underline">{{ $pool->enabled ? 'Disable' : 'Enable' }}</button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($showForm)
        <div class="fixed inset-0 z-10 flex items-center justify-center bg-gray-900/50 p-4">
            <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl max-h-[90vh] overflow-y-auto">
                <h2 class="text-lg font-semibold text-gray-900">{{ $editingId ? 'Edit' : 'Add' }} Resource Pool</h2>
                <div class="mt-4 space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700">Name</label>
                        <input type="text" wire:model="name" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                        @error('name') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700">Description</label>
                        <textarea wire:model="description" rows="2" class="mt-1 block w-full rounded-md border-gray-300 text-sm"></textarea>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-700">Icon</label>
                            <select wire:model="icon" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                                <option value="laptop">Laptop</option>
                                <option value="bolt">Charger</option>
                                <option value="monitor">Monitor</option>
                                <option value="camera">Camera</option>
                                <option value="device">Device</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700">Allocation Mode</label>
                            <select wire:model="allocationMode" class="mt-1 block w-full rounded-md border-gray-300 text-sm" {{ $editingId ? 'disabled' : '' }}>
                                <option value="individual">Individually Tracked</option>
                                <option value="quantity">Quantity Tracked</option>
                            </select>
                        </div>
                    </div>
                    @if ($allocationMode === 'quantity')
                        <div>
                            <label class="block text-xs font-medium text-gray-700">Total Quantity</label>
                            <input type="number" min="0" wire:model="quantityTotal" class="mt-1 block w-32 rounded-md border-gray-300 text-sm">
                        </div>
                    @endif
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-700">Min Lead (min)</label>
                            <input type="number" min="0" wire:model="minimumLeadTimeMinutes" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700">Prep Buffer (min)</label>
                            <input type="number" min="0" wire:model="preparationBufferMinutes" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700">Return Buffer (min)</label>
                            <input type="number" min="0" wire:model="returnBufferMinutes" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700">Reference Prefix</label>
                        <input type="text" wire:model="bookingReferencePrefix" maxlength="8" class="mt-1 block w-24 rounded-md border-gray-300 text-sm uppercase">
                    </div>
                    <div class="grid grid-cols-2 gap-2 pt-2">
                        @foreach ([
                            'allowWeekends' => 'Allow weekends', 'allowOutOfHours' => 'Allow out-of-hours',
                            'requiresRoom' => 'Requires room', 'allowsStudent' => 'Allows student',
                            'requiresStudent' => 'Requires student', 'requiresBookingType' => 'Requires exam type',
                            'autoApprovalEnabled' => 'Auto-approval enabled', 'enabled' => 'Pool enabled',
                        ] as $prop => $label)
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" wire:model="{{ $prop }}" class="rounded border-gray-300 text-indigo-600">
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <button wire:click="$set('showForm', false)" class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300">Cancel</button>
                    <button wire:click="save" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white">Save</button>
                </div>
            </div>
        </div>
    @endif
</div>
