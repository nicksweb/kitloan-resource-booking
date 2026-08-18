<x-mail::message>
# Booking Amended

This booking is still approved — no action needed — but here's what changed.

**Reference:** {{ $booking->reference }}
**Requester:** {{ $booking->bookedBy->name }}

{{ $booking->start_at->format('l j F Y') }}
{{ $booking->start_at->format('g:i A') }} – {{ $booking->end_at->format('g:i A') }}

**Room:** {{ $booking->location?->name ?? '—' }}
**Exam Type:** {{ $booking->bookingType?->name ?? '—' }}
**Requested:** {{ $booking->items->sum('quantity_requested') }} × {{ $booking->resourcePool->name }}

**What changed:**
@foreach ($changes as $change)
- {{ $change }}
@endforeach

<x-mail::button :url="$viewUrl">
View Booking
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
