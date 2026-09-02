<div>
    <x-admin.nav />
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold tracking-tight text-gray-900">Audit Log</h1>
        <button wire:click="$set('showClear', true)" class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50">Clear log&hellip;</button>
    </div>

    <div class="mt-4 flex flex-wrap gap-2">
        <input type="text" wire:model.live.debounce.400ms="search" placeholder="Search description or booking reference" class="w-full max-w-sm rounded-md border-gray-300 text-sm">
        <select wire:model.live="type" class="rounded-md border-gray-300 text-sm">
            <option value="">All event types</option>
            @foreach ($eventTypes as $eventType)
                <option value="{{ $eventType }}">{{ $eventType }}</option>
            @endforeach
        </select>
    </div>

    <div class="mt-4 divide-y divide-gray-100 rounded-xl border border-gray-200 bg-white">
        @forelse ($events as $event)
            @php($rowTag = $event->booking ? 'a' : 'div')
            <{{ $rowTag }}
                @if ($event->booking) href="{{ route('bookings.show', $event->booking) }}" wire:navigate @endif
                class="block px-5 py-3 text-sm transition-colors {{ $event->booking ? 'cursor-pointer hover:bg-indigo-50/60' : '' }}">
                <div class="flex items-center justify-between gap-4">
                    <span class="font-medium text-gray-900">{{ $event->created_at->format('H:i') }} {{ $event->description }}</span>
                    <span class="shrink-0 text-xs text-gray-400">{{ $event->created_at->format('j M Y') }}</span>
                </div>
                <div class="mt-0.5 flex items-center gap-3 text-xs text-gray-400">
                    <span class="rounded bg-gray-100 px-1.5 py-0.5 font-mono text-gray-500">{{ $event->event_type }}</span>
                    @if ($event->actor)<span>by {{ $event->actor->name }}</span>@endif
                    @if ($event->ip_address)<span>{{ $event->ip_address }}</span>@endif
                    @if ($event->booking)
                        <span class="text-indigo-600">{{ $event->booking->reference }}</span>
                    @endif
                </div>
            </{{ $rowTag }}>
        @empty
            <p class="px-5 py-6 text-sm text-gray-500">No audit events match.</p>
        @endforelse
    </div>

    <div class="mt-4">{{ $events->links() }}</div>

    @if ($showClear)
        <div class="fixed inset-0 z-10 flex items-center justify-center bg-gray-900/50 p-4">
            <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
                <h2 class="text-lg font-semibold text-gray-900">Clear audit log</h2>
                <p class="mt-1 text-xs text-gray-500">Permanently deletes entries. The clear itself is recorded as a new audit entry.</p>
                <div class="mt-4">
                    <label class="block text-xs font-medium text-gray-700">Remove</label>
                    <select wire:model="clearRange" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                        <option value="30">entries older than 30 days</option>
                        <option value="90">entries older than 90 days</option>
                        <option value="180">entries older than 180 days</option>
                        <option value="365">entries older than 365 days</option>
                        <option value="all">everything</option>
                    </select>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <button wire:click="$set('showClear', false)" class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300">Cancel</button>
                    <button wire:click="clear" wire:confirm="Permanently delete the selected audit entries?" class="rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white">Clear</button>
                </div>
            </div>
        </div>
    @endif
</div>
