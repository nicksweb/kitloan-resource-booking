<?php

namespace App\Mail;

use App\Settings\SettingsRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class DailySummaryMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  Collection  $bookings  Today's active bookings, ordered by start time
     */
    public function __construct(public Collection $bookings, public Carbon $date)
    {
    }

    public function envelope(): Envelope
    {
        $settings = app(SettingsRepository::class);
        $envelope = new Envelope(subject: "Today's bookings — {$this->date->format('D j M')} ({$this->bookings->count()})");

        $replyTo = $settings->get('helpdesk_reply_to_address');
        if ($replyTo) {
            $envelope->replyTo[] = new Address($replyTo);
        }

        return $envelope;
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.bookings.daily-summary',
            with: ['bookings' => $this->bookings, 'date' => $this->date],
        );
    }
}
