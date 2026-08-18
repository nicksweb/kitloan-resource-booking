<div>
    <h1 class="text-2xl font-semibold tracking-tight text-gray-900">All Bookings</h1>

    <div class="mt-4 flex flex-wrap items-end gap-3 rounded-xl border border-gray-200 bg-white p-4">
        <div>
            <label class="block text-xs font-medium text-gray-700">From</label>
            <input type="date" wire:model.live="from" class="mt-1 rounded-md border-gray-300 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-700">To</label>
            <input type="date" wire:model.live="to" class="mt-1 rounded-md border-gray-300 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-700">Resource Pool</label>
            <select wire:model.live="resourcePoolId" class="mt-1 rounded-md border-gray-300 text-sm">
                <option value="">All</option>
                @foreach ($pools as $pool)<option value="{{ $pool->id }}">{{ $pool->name }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-700">Room</label>
            <select wire:model.live="locationId" class="mt-1 rounded-md border-gray-300 text-sm">
                <option value="">All</option>
                @foreach ($locations as $location)<option value="{{ $location->id }}">{{ $location->code }}</option>@endforeach
            </select>
        </div>
        <div class="flex-1 min-w-[180px]">
            <label class="block text-xs font-medium text-gray-700">Search</label>
            <input type="text" wire:model.live.debounce.400ms="search" placeholder="Reference or name" class="mt-1 w-full rounded-md border-gray-300 text-sm">
        </div>
    </div>

    <div class="mt-6 space-y-6">
        @forelse ($bookings as $day => $dayBookings)
            <div>
                <h2 class="text-sm font-semibold text-gray-500">{{ \Illuminate\Support\Carbon::parse($day)->format('l, j F Y') }}</h2>
                <div class="mt-2 divide-y divide-gray-100 rounded-xl border border-gray-200 bg-white">
                    @foreach ($dayBookings as $booking)
                        <a href="{{ route('bookings.show', $booking) }}" class="flex flex-wrap items-center justify-between gap-2 px-5 py-3 hover:bg-gray-50">
                            <div class="flex items-center gap-4">
                                <span class="w-28 text-sm font-medium text-gray-900">{{ $booking->start_at->format('g:i A') }}&ndash;{{ $booking->end_at->format('g:i A') }}</span>
                                <span class="text-sm text-gray-700">{{ $booking->location?->code ?? '—' }}</span>
                                <span class="text-sm text-gray-500">{{ $booking->items->sum('quantity_requested') }} &times; {{ $booking->resourcePool->name }}</span>
                                <span class="hidden text-sm text-gray-500 sm:inline">{{ $booking->bookedBy->name }}</span>
                            </div>
                            <x-status-badge :status="$booking->approval_status" />
                        </a>
                    @endforeach
                </div>
            </div>
        @empty
            <p class="text-sm text-gray-500">No bookings in this range.</p>
        @endforelse
    </div>
</div>
