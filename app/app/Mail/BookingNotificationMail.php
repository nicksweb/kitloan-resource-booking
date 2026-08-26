<?php

namespace App\Mail;

use App\Models\Booking;
use App\Services\Notifications\IcsBuilder;
use App\Services\Notifications\TemplateRenderer;
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
    public function __construct(public Booking $booking, public string $kind, public array $changes = []) {}

    /** Editable-template key for this notification's kind. */
    private function templateKey(): string
    {
        if ($this->changes) {
            return 'booking.owner_amended';
        }

        return match ($this->kind) {
            'approved' => 'booking.owner_approved',
            'rejected' => 'booking.owner_rejected',
            'reminder' => 'booking.owner_reminder',
            default => 'booking.owner_submitted',
        };
    }

    private function fallbackSubject(): string
    {
        $ref = $this->booking->reference;

        if ($this->changes) {
            return $this->kind === 'pending'
                ? "Booking updated, awaiting re-approval: {$ref}"
                : "Booking updated: {$ref}";
        }

        return match ($this->kind) {
            'approved' => "Booking confirmed: {$ref}",
            'rejected' => "Booking declined: {$ref}",
            'reminder' => "Reminder: {$ref} is tomorrow",
            default => "Booking submitted: {$ref}",
        };
    }

    public function envelope(): Envelope
    {
        $settings = app(SettingsRepository::class);
        $renderer = app(TemplateRenderer::class);
        $tokens = $renderer->tokensFor($this->booking);

        $envelope = new Envelope(
            subject: $renderer->subject($this->templateKey(), $tokens, $this->fallbackSubject()),
        );

        $replyTo = $settings->get('helpdesk_reply_to_address');
        if ($replyTo) {
            $envelope->replyTo[] = new Address($replyTo);
        }

        return $envelope;
    }

    public function content(): Content
    {
        $renderer = app(TemplateRenderer::class);
        $tokens = $renderer->tokensFor($this->booking);

        return new Content(
            markdown: 'emails.bookings.notification',
            with: [
                'booking' => $this->booking,
                'kind' => $this->kind,
                'changes' => $this->changes,
                'intro' => $renderer->intro($this->templateKey(), $tokens),
                'policyNotice' => $renderer->policyNotice($tokens),
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
