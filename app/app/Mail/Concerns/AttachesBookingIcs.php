<?php

namespace App\Mail\Concerns;

use App\Services\Notifications\IcsBuilder;
use Illuminate\Mail\Mailables\Attachment;

/**
 * Attaches a `booking.ics` calendar file built from `$this->booking`. Used by
 * every booking mailable so IT and the requestor both get a calendar event.
 * Override icsTentative() to mark the event TENTATIVE (e.g. an approval
 * request, where the slot isn't confirmed yet).
 */
trait AttachesBookingIcs
{
    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        return [
            Attachment::fromData(
                fn () => app(IcsBuilder::class)->forBooking($this->booking, $this->icsTentative()),
                'booking.ics',
            )->withMime('text/calendar'),
        ];
    }

    protected function icsTentative(): bool
    {
        return false;
    }
}
