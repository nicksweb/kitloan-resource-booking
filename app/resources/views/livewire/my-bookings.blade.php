<div>
    <h1 class="text-2xl font-semibold tracking-tight text-gray-900">My Bookings</h1>

    <div class="mt-4 border-b border-gray-200">
        <nav class="-mb-px flex gap-6">
            @foreach (['upcoming' => 'Upcoming', 'pending' => 'Pending', 'previous' => 'Previous', 'cancelled' => 'Cancelled', 'rejected' => 'Rejected'] as $key => $label)
                <button wire:click="$set('tab', '{{ $key }}')"
                        class="border-b-2 px-1 py-3 text-sm font-medium {{ $tab === $key ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                    {{ $label }}
                </button>
            @endforeach
        </nav>
    </div>

    <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($bookings as $booking)
            <a href="{{ route('bookings.show', $booking) }}" class="block rounded-xl border border-gray-200 bg-white p-4 hover:border-indigo-300 hover:shadow-sm">
                <div class="flex items-start justify-between">
                    <p class="text-sm font-medium text-gray-900">{{ $booking->start_at->format('D j M Y') }}</p>
                    <x-status-badge :status="$booking->lifecycle_status === 'cancelled' ? 'cancelled' : $booking->approval_status" />
                </div>
                <p class="mt-1 text-sm text-gray-500">{{ $booking->start_at->format('g:i A') }} &ndash; {{ $booking->end_at->format('g:i A') }}</p>
                <p class="mt-2 text-sm text-gray-700">{{ $booking->location?->code ?? 'No room' }} &middot; {{ $booking->items->sum('quantity_requested') }} &times; {{ $booking->resourcePool->name }}</p>
                @if ($booking->bookingType)
                    <p class="text-xs text-gray-500">{{ $booking->bookingType->name }}</p>
                @endif
                <p class="mt-2 text-xs font-medium text-indigo-600">{{ $booking->reference }}</p>
            </a>
        @empty
            <p class="col-span-full text-sm text-gray-500">Nothing here yet.</p>
        @endforelse
    </div>

    <div class="mt-6">{{ $bookings->links() }}</div>
</div>
