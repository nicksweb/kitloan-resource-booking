<x-mail::message>
@if (!empty($changes))
# Booking Updated
@elseif ($kind === 'pending')
# Booking Submitted
@elseif ($kind === 'approved')
# Booking Confirmed
@elseif ($kind === 'rejected')
# Booking Declined
@else
# Reminder
@endif

@if (!empty($intro))
{!! $intro !!}
@elseif (!empty($changes))
Your booking has been amended.
@elseif ($kind === 'pending')
Your booking has been submitted and is awaiting IT approval.
@elseif ($kind === 'approved')
Your booking is confirmed.
@elseif ($kind === 'rejected')
Unfortunately your booking could not be approved.
@else
Your booking is coming up tomorrow.
@endif

**Reference:** {{ $booking->reference }}

{{ $booking->start_at->format('l j F Y') }}
{{ $booking->start_at->format('g:i A') }} – {{ $booking->end_at->format('g:i A') }}

**Room:** {{ $booking->location?->name ?? '—' }}
**Exam Type:** {{ $booking->bookingType?->name ?? '—' }}
**Resources:** {{ $booking->items->sum('quantity_requested') }} × {{ $booking->resourcePool->name }}

@if (!empty($changes))
**What changed:**
@foreach ($changes as $change)
- {{ $change }}
@endforeach

@endif
@if ($kind === 'rejected' && $booking->rejection_reason)
**Reason:**
{{ $booking->rejection_reason }}

Please contact IT if you need assistance finding another time.
@endif

**Status:** {{ $kind === 'rejected' ? 'Declined' : ($kind === 'pending' ? 'Awaiting IT Approval' : 'Approved') }}

<x-mail::button :url="$viewUrl">
View Booking
</x-mail::button>

@if (!empty($policyNotice))
---
{!! $policyNotice !!}
@endif

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
