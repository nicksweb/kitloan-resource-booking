<div>
    <x-admin.nav />
    <h1 class="text-2xl font-semibold tracking-tight text-gray-900">Audit Log</h1>

    <input type="text" wire:model.live.debounce.400ms="search" placeholder="Search description or booking reference" class="mt-4 w-full max-w-sm rounded-md border-gray-300 text-sm">

    <div class="mt-4 divide-y divide-gray-100 rounded-xl border border-gray-200 bg-white">
        @forelse ($events as $event)
            <div class="px-5 py-3 text-sm">
                <div class="flex items-center justify-between">
                    <span class="font-medium text-gray-900">{{ $event->created_at->format('H:i') }} {{ $event->description }}</span>
                    <span class="text-xs text-gray-400">{{ $event->created_at->format('j M Y') }}</span>
                </div>
                @if ($event->booking)
                    <a href="{{ route('bookings.show', $event->booking) }}" class="text-xs text-indigo-600 hover:underline">{{ $event->booking->reference }}</a>
                @endif
            </div>
        @empty
            <p class="px-5 py-6 text-sm text-gray-500">No audit events yet.</p>
        @endforelse
    </div>

    <div class="mt-4">{{ $events->links() }}</div>
</div>
