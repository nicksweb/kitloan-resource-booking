<div>
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold tracking-tight text-gray-900">IT Operations</h1>
        <a href="{{ route('it.logistics') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">Logistics view &rarr;</a>
    </div>

    <div class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
        @foreach ([
            'Bookings Today' => $metrics['bookings_today'],
            'Devices Required Today' => $metrics['devices_today'],
            'Bookings Tomorrow' => $metrics['bookings_tomorrow'],
            'Pending Approvals' => $metrics['pending_approvals'],
            'Unavailable Assets' => $metrics['unavailable_assets'],
        ] as $label => $value)
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <p class="text-2xl font-semibold text-gray-900">{{ $value }}</p>
                <p class="text-xs text-gray-500">{{ $label }}</p>
            </div>
        @endforeach
    </div>

    @if (!empty($warnings))
        <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-4">
            <h2 class="text-sm font-semibold text-amber-800">Attention needed</h2>
            <ul class="mt-2 space-y-1 text-sm text-amber-800">
                @foreach ($warnings as $warning)<li>&bull; {{ $warning }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div class="mt-8 grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <h2 class="text-sm font-semibold text-gray-700">Today</h2>
            <div class="mt-2 divide-y divide-gray-100 rounded-xl border border-gray-200 bg-white">
                @forelse ($today as $booking)
                    <a href="{{ route('bookings.show', $booking) }}" class="flex items-center justify-between gap-3 px-5 py-3 hover:bg-gray-50">
                        <div>
                            <p class="text-sm font-medium text-gray-900">{{ $booking->start_at->format('g:i A') }} &ndash; {{ $booking->end_at->format('g:i A') }}</p>
                            <p class="text-sm text-gray-500">{{ $booking->location?->code ?? '—' }} &middot; {{ $booking->items->sum('quantity_requested') }} {{ $booking->resourcePool->name }} &middot; {{ $booking->bookingType?->name }}</p>
                        </div>
                        <x-status-badge :status="$booking->approval_status" />
                    </a>
                @empty
                    <p class="px-5 py-6 text-sm text-gray-500">Nothing booked today.</p>
                @endforelse
            </div>
        </div>

        <div>
            <h2 class="text-sm font-semibold text-gray-700">Pending Approvals</h2>
            <div class="mt-2 divide-y divide-gray-100 rounded-xl border border-gray-200 bg-white">
                @forelse ($pendingApprovals as $booking)
                    <a href="{{ route('bookings.show', $booking) }}" class="block px-4 py-3 hover:bg-gray-50">
                        <p class="text-sm font-medium text-gray-900">{{ $booking->reference }}</p>
                        <p class="text-xs text-gray-500">{{ $booking->start_at->format('D j M, g:i A') }} &middot; {{ $booking->resourcePool->name }}</p>
                    </a>
                @empty
                    <p class="px-4 py-6 text-sm text-gray-500">Nothing pending.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
