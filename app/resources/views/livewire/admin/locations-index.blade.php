<div>
    <x-admin.nav />
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold tracking-tight text-gray-900">Locations</h1>
        <div class="flex flex-wrap gap-2">
            <button wire:click="export" class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50">Export JSON</button>
            <button wire:click="openCampusRename" class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50">Rename campus</button>
            <button wire:click="openImport" class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50">Import CSV</button>
            <button wire:click="create" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Add Location</button>
        </div>
    </div>

    <div class="mt-6 overflow-hidden rounded-xl border border-gray-200 bg-white">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead><tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                <th class="px-4 py-3">Code</th><th class="px-4 py-3">Name</th><th class="px-4 py-3">Campus</th><th class="px-4 py-3">Status</th><th class="px-4 py-3"></th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($locations as $location)
                    <tr wire:click="edit({{ $location->id }})" class="cursor-pointer transition-colors hover:bg-indigo-50/60">
                        <td class="px-4 py-3 font-medium text-gray-900">{{ $location->code }}</td>
                        <td class="px-4 py-3 text-gray-700">{{ $location->name }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $location->campus }}</td>
                        <td class="px-4 py-3"><x-status-badge :status="$location->enabled ? 'available' : 'disabled'">{{ $location->enabled ? 'Enabled' : 'Disabled' }}</x-status-badge></td>
                        <td class="px-4 py-3 text-right space-x-2">
                            <button wire:click.stop="edit({{ $location->id }})" class="text-indigo-600 hover:underline">Edit</button>
                            <button wire:click.stop="toggleEnabled({{ $location->id }})" class="text-gray-500 hover:underline">{{ $location->enabled ? 'Disable' : 'Enable' }}</button>
                            <button wire:click.stop="delete({{ $location->id }})" wire:confirm="Delete location {{ $location->code }}? Existing bookings keep their history; it just stops appearing in the room picker." class="text-red-600 hover:underline">Delete</button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($showForm)
        <div class="fixed inset-0 z-10 flex items-center justify-center bg-gray-900/50 p-4">
            <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
                <h2 class="text-lg font-semibold text-gray-900">{{ $editingId ? 'Edit' : 'Add' }} Location</h2>
                <div class="mt-4 space-y-3">
                    <div><label class="block text-xs font-medium text-gray-700">Code</label><input type="text" wire:model="code" class="mt-1 block w-full rounded-md border-gray-300 text-sm">@error('code')<p class="text-xs text-red-600">{{ $message }}</p>@enderror</div>
                    <div><label class="block text-xs font-medium text-gray-700">Name</label><input type="text" wire:model="name" class="mt-1 block w-full rounded-md border-gray-300 text-sm">@error('name')<p class="text-xs text-red-600">{{ $message }}</p>@enderror</div>
                    <div><label class="block text-xs font-medium text-gray-700">Campus</label><input type="text" wire:model="campus" class="mt-1 block w-full rounded-md border-gray-300 text-sm"></div>
                    <div><label class="block text-xs font-medium text-gray-700">Building</label><input type="text" wire:model="building" class="mt-1 block w-full rounded-md border-gray-300 text-sm"></div>
                    <div><label class="block text-xs font-medium text-gray-700">Description</label><textarea wire:model="description" rows="2" class="mt-1 block w-full rounded-md border-gray-300 text-sm"></textarea></div>
                    <label class="flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" wire:model="enabled" class="rounded border-gray-300 text-indigo-600"> Enabled</label>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <button wire:click="$set('showForm', false)" class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300">Cancel</button>
                    <button wire:click="save" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white">Save</button>
                </div>
            </div>
        </div>
    @endif

    @if ($showCampusRename)
        <div class="fixed inset-0 z-10 flex items-center justify-center bg-gray-900/50 p-4">
            <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
                <h2 class="text-lg font-semibold text-gray-900">Rename a campus</h2>
                <p class="mt-1 text-xs text-gray-500">Updates the campus label on every location that currently carries it.</p>
                <div class="mt-4 space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700">Current campus</label>
                        <input type="text" list="campus-list" wire:model="campusRenameFrom" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                        <datalist id="campus-list">
                            @foreach ($campuses as $campus)
                                <option value="{{ $campus }}"></option>
                            @endforeach
                        </datalist>
                        @error('campusRenameFrom') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700">New campus name</label>
                        <input type="text" wire:model="campusRenameTo" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                        @error('campusRenameTo') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <button wire:click="$set('showCampusRename', false)" class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300">Cancel</button>
                    <button wire:click="renameCampus" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white">Rename</button>
                </div>
            </div>
        </div>
    @endif

    @if ($showImport)
        <div class="fixed inset-0 z-10 flex items-center justify-center bg-gray-900/50 p-4">
            <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl">
                <h2 class="text-lg font-semibold text-gray-900">Import Locations from CSV</h2>
                <p class="mt-1 text-xs text-gray-500">
                    Header row required, with at least <code class="rounded bg-gray-100 px-1">code</code> and
                    <code class="rounded bg-gray-100 px-1">name</code> columns — optionally
                    <code class="rounded bg-gray-100 px-1">campus</code>, <code class="rounded bg-gray-100 px-1">building</code>,
                    <code class="rounded bg-gray-100 px-1">description</code>. Existing locations (matched by code) are updated; new codes are created.
                </p>
                <p class="mt-2 text-xs text-gray-400">Example: <code class="rounded bg-gray-100 px-1">code,name,campus,building</code> then <code class="rounded bg-gray-100 px-1">B12,B Block Room 12,Main Campus,B Block</code></p>

                <div class="mt-4">
                    <input type="file" wire:model="csvFile" accept=".csv,text/csv" class="block w-full text-sm">
                    <div wire:loading wire:target="csvFile" class="mt-1 text-xs text-gray-500">Uploading&hellip;</div>
                    @error('csvFile') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                @if ($importResults)
                    <div class="mt-4 rounded-md bg-gray-50 p-3 text-xs">
                        <p class="font-medium text-gray-700">{{ $importResults['created'] }} created, {{ $importResults['updated'] }} updated, {{ count($importResults['skipped']) }} skipped.</p>
                        @if (!empty($importResults['skipped']))
                            <ul class="mt-2 space-y-0.5 text-red-600">
                                @foreach ($importResults['skipped'] as $reason)
                                    <li>{{ $reason }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endif

                <div class="mt-6 flex justify-end gap-2">
                    <button wire:click="$set('showImport', false)" class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300">Close</button>
                    <button wire:click="importCsv" wire:loading.attr="disabled" wire:target="importCsv" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white disabled:opacity-60">
                        <span wire:loading.remove wire:target="importCsv">Import</span>
                        <span wire:loading wire:target="importCsv">Importing&hellip;</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
