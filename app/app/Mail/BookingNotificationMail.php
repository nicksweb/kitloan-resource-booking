<?php

namespace App\Mail;

use App\Models\Booking;
use App\Services\Notifications\IcsBuilder;
use App\Settings\SettingsRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class BookingNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  'pending'|'approved'|'rejected'|'reminder'  $kind
     * @param  array<int, string>  $changes  Non-empty only when this is an amendment notice
     */
    public function __construct(public Booking $booking, public string $kind, public array $changes = [])
    {
    }

    public function envelope(): Envelope
    {
        $settings = app(SettingsRepository::class);

        $subjects = $this->changes
            ? [
                'pending' => "Booking updated, awaiting re-approval: {$this->booking->reference}",
                'approved' => "Booking updated: {$this->booking->reference}",
                'rejected' => "Booking declined: {$this->booking->reference}",
                'reminder' => "Reminder: {$this->booking->reference} is tomorrow",
            ]
            : [
                'pending' => "Booking submitted: {$this->booking->reference}",
                'approved' => "Booking confirmed: {$this->booking->reference}",
                'rejected' => "Booking declined: {$this->booking->reference}",
                'reminder' => "Reminder: {$this->booking->reference} is tomorrow",
            ];

        $envelope = new Envelope(subject: $subjects[$this->kind]);

        $replyTo = $settings->get('helpdesk_reply_to_address');
        if ($replyTo) {
            $envelope->replyTo[] = new Address($replyTo);
        }

        return $envelope;
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.bookings.notification',
            with: [
                'booking' => $this->booking,
                'kind' => $this->kind,
                'changes' => $this->changes,
                // A genuine "magic link" — signed, 30 days, no login required —
                // rather than the normal authenticated /bookings/{ref} route.
                'viewUrl' => URL::temporarySignedRoute('bookings.public-view', now()->addDays(30), ['booking' => $this->booking->reference]),
            ],
        );
    }

    public function attachments(): array
    {
        if ($this->kind !== 'approved' && $this->kind !== 'reminder') {
            return [];
        }

        $ics = app(IcsBuilder::class)->forBooking($this->booking);

        return [
            Attachment::fromData(fn () => $ics, 'booking.ics')->withMime('text/calendar'),
        ];
    }
}
