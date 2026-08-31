<?php

namespace App\Mail;

use App\Mail\Concerns\AttachesBookingIcs;
use App\Models\Booking;
use App\Services\Notifications\TemplateRenderer;
use App\Settings\SettingsRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * IT-facing FYI for a brand-new booking that auto-approved — no action needed,
 * but IT still wants it on their calendar. When a new booking instead needs
 * review, BookingApprovalRequestMail is sent (it carries the approve/reject
 * links). Suppressible by disabling the `booking.it_confirmed` template.
 */
class BookingConfirmedNoticeMail extends Mailable
{
    use AttachesBookingIcs, Queueable, SerializesModels;

    public function __construct(public Booking $booking) {}

    public function envelope(): Envelope
    {
        $settings = app(SettingsRepository::class);
        $renderer = app(TemplateRenderer::class);

        $envelope = new Envelope(subject: $renderer->subject(
            'booking.it_confirmed',
            $renderer->tokensFor($this->booking),
            "Booking confirmed: {$this->booking->reference}",
        ));

        $replyTo = $settings->get('helpdesk_reply_to_address');
        if ($replyTo) {
            $envelope->replyTo[] = new Address($replyTo);
        }

        return $envelope;
    }

    public function content(): Content
    {
        $renderer = app(TemplateRenderer::class);

        return new Content(
            markdown: 'emails.bookings.it-confirmed',
            with: [
                'booking' => $this->booking,
                'intro' => $renderer->intro('booking.it_confirmed', $renderer->tokensFor($this->booking)),
                'viewUrl' => route('bookings.show', $this->booking),
            ],
        );
    }
}
