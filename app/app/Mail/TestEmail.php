<?php

namespace App\Mail;

use App\Models\User;
use App\Settings\SettingsRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Sent synchronously (never queued) from Administration -> Settings so a
 * misconfigured mail server surfaces its error immediately in the UI,
 * instead of silently failing in a background queue job.
 */
class TestEmail extends Mailable
{
    use Queueable;

    public function __construct(public User $sentBy)
    {
    }

    public function envelope(): Envelope
    {
        $settings = app(SettingsRepository::class);
        $envelope = new Envelope(subject: '['.config('app.name').'] Test email');

        $replyTo = $settings->get('helpdesk_reply_to_address');
        if ($replyTo) {
            $envelope->replyTo[] = new Address($replyTo);
        }

        return $envelope;
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.misc.test',
            with: [
                'sentBy' => $this->sentBy,
                'sentAt' => now(),
                'mailHost' => config('mail.mailers.smtp.host'),
                'mailFrom' => config('mail.from.address'),
            ],
        );
    }
}
