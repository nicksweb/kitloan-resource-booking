<x-layouts.guest>
    <div class="mx-auto w-full max-w-lg text-left">
        <div class="text-center">
            <p class="text-sm font-medium text-indigo-600">{{ $booking->reference }}</p>
            <h1 class="mt-1 text-xl font-semibold text-gray-900">{{ $booking->resourcePool->name }}</h1>
            <div class="mt-2">
                <x-status-badge :status="$booking->lifecycle_status === 'cancelled' ? 'cancelled' : $booking->approval_status" />
            </div>
        </div>

        <div class="mt-6 rounded-xl border border-gray-200 bg-white p-5">
            <dl class="space-y-3 text-sm">
                <div class="flex justify-between"><dt class="text-gray-500">Date</dt><dd class="font-medium text-gray-900">{{ $booking->start_at->format('D j M Y') }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Time</dt><dd class="font-medium text-gray-900">{{ $booking->start_at->format('g:i A') }} &ndash; {{ $booking->end_at->format('g:i A') }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Room</dt><dd class="font-medium text-gray-900">{{ $booking->roomLabel() }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Exam Type</dt><dd class="font-medium text-gray-900">{{ $booking->bookingType?->name ?? '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Resources</dt><dd class="font-medium text-gray-900">{{ $booking->items->sum('quantity_requested') }} &times; {{ $booking->resourcePool->name }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Booked by</dt><dd class="font-medium text-gray-900">{{ $booking->bookedBy->name }}</dd></div>
                @if ($booking->students->isNotEmpty())
                    <div class="flex justify-between"><dt class="text-gray-500">Students</dt><dd class="font-medium text-gray-900 text-right">{{ $booking->students->pluck('student_name')->join(', ') }}</dd></div>
                @endif
            </dl>

            @if ($booking->lifecycle_status === 'cancelled' && $booking->rejection_reason)
                <div class="mt-4 rounded-md bg-red-50 px-3 py-2 text-sm text-red-800 ring-1 ring-inset ring-red-600/20">
                    <strong>Declined:</strong> {{ $booking->rejection_reason }}
                </div>
            @endif
        </div>

        <p class="mt-4 text-center text-xs text-gray-500">
            <a href="{{ route('auth.login', ['redirect' => route('bookings.show', $booking)]) }}" class="text-indigo-600 hover:underline">Sign in</a>
            to manage this booking or view others.
        </p>
    </div>
</x-layouts.guest>
