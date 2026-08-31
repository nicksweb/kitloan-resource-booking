<x-mail::message>
# Today's Bookings — {{ $date->format('l j F Y') }}

@if (!empty($intro))
{!! $intro !!}

@endif
{{ $bookings->count() }} booking(s) today.

@foreach ($bookings as $booking)
**{{ $booking->start_at->format('g:i A') }}–{{ $booking->end_at->format('g:i A') }}** — {{ $booking->roomCode() }} — {{ $booking->items->sum('quantity_requested') }} × {{ $booking->resourcePool->name }} ({{ $booking->bookingType?->name ?? 'No type' }}) — {{ ucfirst($booking->approval_status) }} — {{ $booking->reference }}
@if (!$loop->last)

@endif
@endforeach

<x-mail::button :url="route('it.dashboard')">
Open IT Dashboard
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
