<x-layouts.app :title="'Reject Booking'">
    <div class="mx-auto max-w-lg">
        <h1 class="text-xl font-semibold text-gray-900">Reject Booking {{ $booking->reference }}</h1>
        <p class="mt-1 text-sm text-gray-500">
            {{ $booking->start_at->format('l j F Y, g:i A') }} &ndash; {{ $booking->end_at->format('g:i A') }}
            &middot; {{ $booking->items->sum('quantity_requested') }} &times; {{ $booking->resourcePool->name }}
        </p>

        <form method="POST" action="{{ route('bookings.reject', $booking) }}" class="mt-6">
            @csrf
            <label class="block text-sm font-medium text-gray-700">Reason</label>
            <textarea name="reason" rows="4" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">{{ old('reason') }}</textarea>
            @error('reason') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror

            <div class="mt-4 flex gap-2">
                <button type="submit" class="rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-500">Reject Booking</button>
                <a href="{{ route('bookings.show', $booking) }}" class="rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300">Cancel</a>
            </div>
        </form>
    </div>
</x-layouts.app>
