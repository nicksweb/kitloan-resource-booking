<div class="max-w-3xl">
    <h1 class="text-2xl font-semibold tracking-tight text-gray-900">My Profile</h1>

    <div class="mt-6 space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-gray-900">Account</h2>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-gray-500">Name</dt><dd class="font-medium text-gray-900">{{ auth()->user()->name }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Email</dt><dd class="font-medium text-gray-900">{{ auth()->user()->email }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Role</dt><dd class="font-medium text-gray-900">{{ auth()->user()->roles->first()?->name ?? '—' }}</dd></div>
            </dl>
            <p class="mt-2 text-xs text-gray-400">Name and email come from your organisation sign-in and can't be changed here.</p>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-gray-900">Appearance</h2>
            <p class="mt-1 text-xs text-gray-400">Choose a colour theme. "System" follows your device setting.</p>
            <div class="mt-3 flex flex-wrap gap-2">
                @foreach (['system' => 'System', 'light' => 'Light', 'dark' => 'Dark'] as $value => $label)
                    <label class="flex cursor-pointer items-center gap-2 rounded-md border px-3 py-2 text-sm {{ $theme === $value ? 'border-indigo-600 bg-indigo-50 text-indigo-700' : 'border-gray-300 text-gray-700' }}">
                        <input type="radio" wire:model.live="theme" value="{{ $value }}" class="text-indigo-600">
                        {{ $label }}
                    </label>
                @endforeach
            </div>
            @error('theme') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-gray-900">Notifications</h2>
            <label class="mt-3 flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" wire:model="receivesDailySummary" class="rounded border-gray-300 text-indigo-600">
                Email me the 7am daily booking summary
            </label>
        </div>

        @if ($canBeOfficer)
            <div class="rounded-xl border border-gray-200 bg-white p-5">
                <h2 class="flex items-center gap-1.5 text-sm font-semibold text-gray-900"><x-pool-icon icon="it-officer" class="h-4 w-4 text-indigo-600" /> IT officer bookings</h2>
                <label class="mt-3 flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" wire:model="bookableAsOfficer" class="rounded border-gray-300 text-indigo-600">
                    Make me bookable as an IT officer
                </label>
                <p class="mt-1 text-xs text-gray-400">
                    Staff can then book you for a time, place and issue (e.g. Teams support). You'll get an email
                    and a calendar invitation for each booking. Turn this off when you're unavailable — existing
                    bookings are kept, and IT can reassign them to another officer.
                </p>
            </div>
        @endif

        <button wire:click="save" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Save</button>
    </div>
</div>
