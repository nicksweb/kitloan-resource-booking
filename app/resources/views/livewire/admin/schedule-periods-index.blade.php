<div>
    <x-admin.nav />
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-gray-900">Periods</h1>
            <p class="mt-1 text-sm text-gray-500">Quick-fill presets for the booking start/finish time fields — not enforced as a constraint.</p>
        </div>
        <button wire:click="create" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Add Period</button>
    </div>

    <div class="mt-6 space-y-6">
        @forelse ($periods as $groupName => $groupPeriods)
            <div>
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-700">{{ $groupName }}</h2>
                    <button wire:click="create('{{ $groupName }}')" class="text-xs font-medium text-indigo-600 hover:text-indigo-500">+ Add to {{ $groupName }}</button>
                </div>
                <div class="mt-2 overflow-hidden rounded-xl border border-gray-200 bg-white">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead><tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="px-4 py-3">Name</th><th class="px-4 py-3">Time</th><th class="px-4 py-3">Status</th><th class="px-4 py-3"></th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($groupPeriods as $period)
                                <tr>
                                    <td class="px-4 py-3 font-medium text-gray-900">{{ $period->name }}</td>
                                    <td class="px-4 py-3 text-gray-500">{{ $period->start_time->format('g:i A') }} &ndash; {{ $period->end_time->format('g:i A') }}</td>
                                    <td class="px-4 py-3"><x-status-badge :status="$period->enabled ? 'available' : 'disabled'">{{ $period->enabled ? 'Enabled' : 'Disabled' }}</x-status-badge></td>
                                    <td class="px-4 py-3 text-right space-x-2">
                                        <button wire:click="edit({{ $period->id }})" class="text-indigo-600 hover:underline">Edit</button>
                                        <button wire:click="toggleEnabled({{ $period->id }})" class="text-gray-500 hover:underline">{{ $period->enabled ? 'Disable' : 'Enable' }}</button>
                                        <button wire:click="delete({{ $period->id }})" wire:confirm="Remove this period?" class="text-red-500 hover:underline">Delete</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @empty
            <p class="text-sm text-gray-500">No periods configured yet.</p>
        @endforelse
    </div>

    @if ($showForm)
        <div class="fixed inset-0 z-10 flex items-center justify-center bg-gray-900/50 p-4">
            <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
                <h2 class="text-lg font-semibold text-gray-900">{{ $editingId ? 'Edit' : 'Add' }} Period</h2>
                <div class="mt-4 space-y-3">
                    <div><label class="block text-xs font-medium text-gray-700">Group</label><input type="text" wire:model="groupName" placeholder="e.g. Senior School" class="mt-1 block w-full rounded-md border-gray-300 text-sm">@error('groupName')<p class="text-xs text-red-600">{{ $message }}</p>@enderror</div>
                    <div><label class="block text-xs font-medium text-gray-700">Name</label><input type="text" wire:model="name" placeholder="e.g. Period 1" class="mt-1 block w-full rounded-md border-gray-300 text-sm">@error('name')<p class="text-xs text-red-600">{{ $message }}</p>@enderror</div>
                    <div class="grid grid-cols-2 gap-3">
                        <div><label class="block text-xs font-medium text-gray-700">Start</label><input type="time" wire:model="startTime" class="mt-1 block w-full rounded-md border-gray-300 text-sm">@error('startTime')<p class="text-xs text-red-600">{{ $message }}</p>@enderror</div>
                        <div><label class="block text-xs font-medium text-gray-700">Finish</label><input type="time" wire:model="endTime" class="mt-1 block w-full rounded-md border-gray-300 text-sm">@error('endTime')<p class="text-xs text-red-600">{{ $message }}</p>@enderror</div>
                    </div>
                    <label class="flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" wire:model="enabled" class="rounded border-gray-300 text-indigo-600"> Enabled</label>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <button wire:click="$set('showForm', false)" class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300">Cancel</button>
                    <button wire:click="save" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white">Save</button>
                </div>
            </div>
        </div>
    @endif
</div>
