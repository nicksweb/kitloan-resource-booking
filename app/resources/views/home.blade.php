<x-layouts.app :title="'Home'">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold tracking-tight text-gray-900">Resource Booking</h1>
    </div>

    <div class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
        @forelse ($pools as $pool)
            <a href="{{ route('booking.wizard', $pool) }}"
               class="group flex flex-col items-center gap-3 rounded-xl border border-gray-200 bg-white p-6 text-center shadow-sm transition hover:border-indigo-300 hover:shadow-md">
                <span class="flex h-12 w-12 items-center justify-center rounded-full bg-indigo-50 text-indigo-600 group-hover:bg-indigo-100">
                    <x-pool-icon :icon="$pool->icon" class="w-6 h-6" />
                </span>
                <span class="text-sm font-medium text-gray-900">{{ $pool->name }}</span>
            </a>
        @empty
            <p class="col-span-full text-sm text-gray-500">No resource pools have been set up yet.</p>
        @endforelse
    </div>

    <div class="mt-10">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold text-gray-900">Upcoming Bookings</h2>
            <a href="{{ route('bookings.mine') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">View all &rarr;</a>
        </div>

        <div class="mt-4 divide-y divide-gray-100 rounded-xl border border-gray-200 bg-white">
            @forelse ($upcomingBookings as $booking)
                <a href="{{ route('bookings.show', $booking) }}" class="flex items-center justify-between gap-4 px-5 py-4 hover:bg-gray-50">
                    <div>
                        <p class="text-sm font-medium text-gray-900">{{ $booking->start_at->format('D j M, g:i A') }} &ndash; {{ $booking->end_at->format('g:i A') }}</p>
                        <p class="text-sm text-gray-500">{{ $booking->location?->name ?? 'No room' }} &middot; {{ $booking->resourcePool->name }}</p>
                    </div>
                    <x-status-badge :status="$booking->approval_status" />
                </a>
            @empty
                <p class="px-5 py-6 text-sm text-gray-500">No upcoming bookings. <a href="{{ route('home') }}" class="text-indigo-600 font-medium">Pick a resource pool above to get started.</a></p>
            @endforelse
        </div>
    </div>
</x-layouts.app>
