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
use Illuminate\Support\Facades\URL;

class BookingApprovalRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<int, string>  $changes  Non-empty when this is a re-approval after an amendment
     */
    public function __construct(public Booking $booking, public array $changes = [])
    {
    }

    public function envelope(): Envelope
    {
        $settings = app(SettingsRepository::class);
        $subject = $this->changes
            ? "Re-approval needed after amendment: {$this->booking->reference}"
            : "Approval needed: {$this->booking->reference}";
        $envelope = new Envelope(subject: $subject);

        $replyTo = $settings->get('helpdesk_reply_to_address');
        if ($replyTo) {
            $envelope->replyTo[] = new Address($replyTo);
        }

        return $envelope;
    }

    public function content(): Content
    {
        $expiry = now()->addDays(7);

        return new Content(
            markdown: 'emails.bookings.approval-request',
            with: [
                'booking' => $this->booking,
                'changes' => $this->changes,
                'viewUrl' => route('bookings.show', $this->booking),
                'approveUrl' => URL::temporarySignedRoute('bookings.approve', $expiry, ['booking' => $this->booking->reference]),
                'rejectUrl' => URL::temporarySignedRoute('bookings.reject.show', $expiry, ['booking' => $this->booking->reference]),
            ],
        );
    }
}
