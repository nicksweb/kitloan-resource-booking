<div>
    <x-admin.nav />
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold tracking-tight text-gray-900">Booking Types</h1>
        <button wire:click="create" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Add Booking Type</button>
    </div>

    <div class="mt-6 overflow-hidden rounded-xl border border-gray-200 bg-white">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead><tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                <th class="px-4 py-3">Name</th><th class="px-4 py-3">Requires Approval</th><th class="px-4 py-3">Status</th><th class="px-4 py-3"></th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($types as $type)
                    <tr>
                        <td class="px-4 py-3 font-medium text-gray-900">{{ $type->name }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $type->requires_approval ? 'Yes' : 'No' }}</td>
                        <td class="px-4 py-3"><x-status-badge :status="$type->enabled ? 'available' : 'disabled'">{{ $type->enabled ? 'Enabled' : 'Disabled' }}</x-status-badge></td>
                        <td class="px-4 py-3 text-right space-x-2">
                            <button wire:click="edit({{ $type->id }})" class="text-indigo-600 hover:underline">Edit</button>
                            <button wire:click="toggleEnabled({{ $type->id }})" class="text-gray-500 hover:underline">{{ $type->enabled ? 'Disable' : 'Enable' }}</button>
                            <button wire:click="delete({{ $type->id }})" wire:confirm="Delete &quot;{{ $type->name }}&quot;? Existing bookings keep their history; it just stops being offered on new ones." class="text-red-600 hover:underline">Delete</button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($showForm)
        <div class="fixed inset-0 z-10 flex items-center justify-center bg-gray-900/50 p-4">
            <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl max-h-[90vh] overflow-y-auto">
                <h2 class="text-lg font-semibold text-gray-900">{{ $editingId ? 'Edit' : 'Add' }} Booking Type</h2>
                <div class="mt-4 space-y-3">
                    <div><label class="block text-xs font-medium text-gray-700">Name</label><input type="text" wire:model="name" class="mt-1 block w-full rounded-md border-gray-300 text-sm">@error('name')<p class="text-xs text-red-600">{{ $message }}</p>@enderror</div>
                    <div><label class="block text-xs font-medium text-gray-700">Description</label><textarea wire:model="description" rows="2" class="mt-1 block w-full rounded-md border-gray-300 text-sm"></textarea></div>
                    <div><label class="block text-xs font-medium text-gray-700">Instructions for User</label><textarea wire:model="instructionsForUser" rows="2" class="mt-1 block w-full rounded-md border-gray-300 text-sm"></textarea></div>
                    <div><label class="block text-xs font-medium text-gray-700">Instructions for IT</label><textarea wire:model="instructionsForIt" rows="2" class="mt-1 block w-full rounded-md border-gray-300 text-sm"></textarea></div>
                    <label class="flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" wire:model="requiresApproval" class="rounded border-gray-300 text-indigo-600"> Always requires approval</label>
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
