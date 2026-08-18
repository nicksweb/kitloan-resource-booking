<x-mail::message>
# {{ !empty($changes) ? 'Re-approval Needed After Amendment' : 'Approval Needed' }}

**Reference:** {{ $booking->reference }}
**Requester:** {{ $booking->bookedBy->name }}
@if ($booking->students->isNotEmpty())
**Student(s):** {{ $booking->students->pluck('student_name')->join(', ') }}
@endif

{{ $booking->start_at->format('l j F Y') }}
{{ $booking->start_at->format('g:i A') }} – {{ $booking->end_at->format('g:i A') }}

**Room:** {{ $booking->location?->name ?? '—' }}
**Exam Type:** {{ $booking->bookingType?->name ?? '—' }}
**Requested:** {{ $booking->items->sum('quantity_requested') }} × {{ $booking->resourcePool->name }}
@if (!empty($changes))
**What changed:**
@foreach ($changes as $change)
- {{ $change }}
@endforeach

@endif
@if ($booking->notes)
**Notes:** {{ $booking->notes }}
@endif

<x-mail::button :url="$approveUrl" color="success">
Approve
</x-mail::button>
<x-mail::button :url="$rejectUrl" color="error">
Reject
</x-mail::button>
<x-mail::button :url="$viewUrl">
View Booking
</x-mail::button>

These links expire in 7 days and require you to be signed in as IT staff.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
