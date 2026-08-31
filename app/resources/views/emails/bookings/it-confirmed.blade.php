<x-mail::message>
# Booking Confirmed

{!! $intro ?? 'A new booking was auto-approved — no action needed.' !!}

**Reference:** {{ $booking->reference }}
**Requester:** {{ $booking->bookedBy->name }}
@if ($booking->students->isNotEmpty())
**Student(s):** {{ $booking->students->pluck('student_name')->join(', ') }}
@endif

{{ $booking->start_at->format('l j F Y') }}
{{ $booking->start_at->format('g:i A') }} – {{ $booking->end_at->format('g:i A') }}

**Room:** {{ $booking->roomLabel() }}
**Exam Type:** {{ $booking->bookingType?->name ?? '—' }}
**Requested:** {{ $booking->items->sum('quantity_requested') }} × {{ $booking->resourcePool->name }}
@if ($booking->notes)
**Notes:** {{ $booking->notes }}
@endif

<x-mail::button :url="$viewUrl">
View Booking
</x-mail::button>

A calendar invitation is attached.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
