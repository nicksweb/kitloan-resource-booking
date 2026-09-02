<x-mail::message>
@if ($kind === 'officer_assigned')
# You've Been Booked
@elseif ($kind === 'officer_updated')
# IT Support Booking Updated
@elseif ($kind === 'reassigned_to')
# Booking Assigned To You
@elseif ($kind === 'reassigned_away')
# Booking Reassigned
@elseif (!empty($changes))
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
@elseif ($kind === 'officer_assigned')
You've been booked to provide IT support. A calendar invitation is attached.
@elseif ($kind === 'officer_updated')
An IT support booking you're on has been amended.
@elseif ($kind === 'reassigned_to')
This booking has been assigned to you.
@elseif ($kind === 'reassigned_away')
This booking is no longer assigned to you.
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

**Room:** {{ $booking->roomLabel() }}
@if ($booking->resourcePool->isStaffPool())
**IT Officer:** {{ $booking->officers()->pluck('name')->join(', ') ?: 'any available officer' }}
@if ($booking->notes)
**Issue:** {{ $booking->notes }}
@endif
@else
**Exam Type:** {{ $booking->bookingType?->name ?? '—' }}
**Resources:** {{ $booking->items->sum('quantity_requested') }} × {{ $booking->resourcePool->name }}
@endif
@if ($booking->helpdesk_url)
**Helpdesk ticket:** [{{ $booking->helpdesk_url }}]({{ $booking->helpdesk_url }})
@endif

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

**Status:** {{ match ($booking->approval_status) { 'rejected' => 'Declined', 'pending' => 'Awaiting IT Approval', default => 'Approved' } }}

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
