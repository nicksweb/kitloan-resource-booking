<?php

namespace App\Mail;

use App\Models\Booking;
use App\Settings\SettingsRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * IT-facing FYI when a booking is amended but stays (or becomes) approved —
 * no action needed, but IT still needs to see it, particularly when the
 * device count changed (they may already be prepping the original quantity).
 * When an amendment instead needs re-approval, BookingApprovalRequestMail is
 * used instead (it carries the approve/reject links).
 */
class BookingAmendedMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<int, string>  $changes
     */
    public function __construct(public Booking $booking, public array $changes)
    {
    }

    public function envelope(): Envelope
    {
        $settings = app(SettingsRepository::class);
        $envelope = new Envelope(subject: "Booking amended: {$this->booking->reference}");

        $replyTo = $settings->get('helpdesk_reply_to_address');
        if ($replyTo) {
            $envelope->replyTo[] = new Address($replyTo);
        }

        return $envelope;
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.bookings.amended',
            with: [
                'booking' => $this->booking,
                'changes' => $this->changes,
                'viewUrl' => route('bookings.show', $this->booking),
            ],
        );
    }
}
