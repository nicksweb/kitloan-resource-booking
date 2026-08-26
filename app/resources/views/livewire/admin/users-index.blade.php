<div>
    <x-admin.nav />
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold tracking-tight text-gray-900">Users</h1>
        <button wire:click="create" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Add User</button>
    </div>

    <input type="text" wire:model.live.debounce.400ms="search" placeholder="Search name or email" class="mt-4 w-full max-w-sm rounded-md border-gray-300 text-sm">

    <div class="mt-4 overflow-hidden rounded-xl border border-gray-200 bg-white">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead><tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                <th class="px-4 py-3">Name</th><th class="px-4 py-3">Email</th><th class="px-4 py-3">Role</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Local login</th><th class="px-4 py-3">2FA</th><th class="px-4 py-3">Last login</th><th class="px-4 py-3"></th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($users as $user)
                    <tr>
                        <td class="px-4 py-3 font-medium text-gray-900">{{ $user->name }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $user->email }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $user->roles->first()?->name ?? '—' }}</td>
                        <td class="px-4 py-3"><x-status-badge :status="$user->enabled ? 'available' : 'disabled'">{{ $user->enabled ? 'Enabled' : 'Disabled' }}</x-status-badge></td>
                        <td class="px-4 py-3">
                            @if ($user->hasRole('administrator'))
                                <div class="flex items-center gap-2">
                                    <x-status-badge :status="$user->password ? 'available' : 'disabled'">{{ $user->password ? 'Configured' : 'Not set' }}</x-status-badge>
                                    <button wire:click="openLocalPasswordForm({{ $user->id }})" class="text-xs text-indigo-600 hover:underline">{{ $user->password ? 'Reset' : 'Set' }}</button>
                                    @if ($user->password)
                                        <button wire:click="clearLocalPassword({{ $user->id }})" onclick="return confirm('Clear the local-login password for {{ $user->email }}? They will no longer be able to use emergency login until a new one is set.')" class="text-xs text-gray-400 hover:underline">Clear</button>
                                    @endif
                                </div>
                            @else
                                <span class="text-xs text-gray-400">N/A</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if ($user->hasTwoFactorEnabled())
                                <span class="inline-flex items-center gap-2">
                                    <x-status-badge status="available">On</x-status-badge>
                                    <button wire:click="clearTwoFactor({{ $user->id }})" wire:confirm="Reset 2FA for {{ $user->email }}? They must set it up again at next sign-in." class="text-xs text-gray-400 hover:underline">Reset</button>
                                </span>
                            @elseif ($user->requiresTwoFactor())
                                <x-status-badge status="disabled">Required — not set</x-status-badge>
                            @elseif ($user->isSsoOnly())
                                <span class="text-xs text-gray-400">SSO</span>
                            @else
                                <span class="text-xs text-gray-400">Off</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-500">{{ $user->last_login_at?->diffForHumans() ?? 'Never' }}</td>
                        <td class="px-4 py-3 text-right space-x-2">
                            <button wire:click="edit({{ $user->id }})" class="text-indigo-600 hover:underline">Edit</button>
                            <button wire:click="toggleEnabled({{ $user->id }})" class="text-gray-500 hover:underline">{{ $user->enabled ? 'Disable' : 'Enable' }}</button>
                            @if ($user->id !== auth()->id())
                                <button wire:click="delete({{ $user->id }})" wire:confirm="Delete {{ $user->email }}? Their bookings and audit history are kept; the account can no longer sign in." class="text-red-600 hover:underline">Delete</button>
                            @endif
                            @if (! $user->hasRole('administrator') && $user->id !== auth()->id() && $user->enabled)
                                <form method="POST" action="{{ route('admin.users.impersonate', $user) }}" class="inline" onsubmit="return confirm('Sign in as {{ $user->name }}? You can return to your own account at any time.');">
                                    @csrf
                                    <button type="submit" class="text-gray-500 hover:underline">Impersonate</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $users->links() }}</div>

    @if ($showForm)
        <div class="fixed inset-0 z-10 flex items-center justify-center bg-gray-900/50 p-4">
            <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
                <h2 class="text-lg font-semibold text-gray-900">{{ $editingId ? 'Edit' : 'Add' }} User</h2>
                <div class="mt-4 space-y-3">
                    <div><label class="block text-xs font-medium text-gray-700">Name</label><input type="text" wire:model="name" class="mt-1 block w-full rounded-md border-gray-300 text-sm">@error('name')<p class="text-xs text-red-600">{{ $message }}</p>@enderror</div>
                    <div><label class="block text-xs font-medium text-gray-700">Email</label><input type="email" wire:model="email" class="mt-1 block w-full rounded-md border-gray-300 text-sm">@error('email')<p class="text-xs text-red-600">{{ $message }}</p>@enderror</div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700">Role</label>
                        <select wire:model="role" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                            <option value="user">User</option>
                            <option value="it_operator">IT Operator</option>
                            <option value="administrator">Administrator</option>
                        </select>
                        @error('role') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" wire:model="enabled" class="rounded border-gray-300 text-indigo-600"> Enabled</label>
                        @error('enabled') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" wire:model="receivesDailySummary" class="rounded border-gray-300 text-indigo-600"> Receives 7am daily booking summary</label>
                        <p class="mt-0.5 text-xs text-gray-400">Only sent to IT Operators/Administrators on days with bookings. Turn off for e.g. leave.</p>
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <button wire:click="$set('showForm', false)" class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300">Cancel</button>
                    <button wire:click="save" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white">Save</button>
                </div>
            </div>
        </div>
    @endif

    @if ($showLocalPasswordForm)
        <div class="fixed inset-0 z-10 flex items-center justify-center bg-gray-900/50 p-4">
            <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
                <h2 class="text-lg font-semibold text-gray-900">Set Local Login Password</h2>
                <p class="mt-1 text-xs text-gray-500">Emergency fallback credential for signing in if SSO is unavailable. Not used for day-to-day sign-in.</p>
                <div class="mt-4 space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700">New Password (min. 12 characters)</label>
                        <input type="password" wire:model="newLocalPassword" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                        @error('newLocalPassword') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700">Confirm Password</label>
                        <input type="password" wire:model="newLocalPasswordConfirmation" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                        @error('newLocalPasswordConfirmation') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <button wire:click="$set('showLocalPasswordForm', false)" class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300">Cancel</button>
                    <button wire:click="saveLocalPassword" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white">Save</button>
                </div>
            </div>
        </div>
    @endif
</div>
