<x-mail::message>
# Test Email

If you're reading this, outbound email from {{ config('app.name') }} is working.

**Sent:** {{ $sentAt->format('j F Y, g:i A') }}
**Sent by:** {{ $sentBy->name }} ({{ $sentBy->email }})
**Mail server:** {{ $mailHost }}
**From address:** {{ $mailFrom }}

No action needed — this was triggered manually from Administration &rarr; Settings.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
