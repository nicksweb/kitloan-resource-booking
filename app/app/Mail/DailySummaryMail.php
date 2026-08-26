<?php

namespace App\Mail;

use App\Services\Notifications\TemplateRenderer;
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
    public function __construct(public Collection $bookings, public Carbon $date) {}

    /** @return array<string, string> */
    private function tokens(): array
    {
        return [
            'date' => $this->date->format('D j M'),
            'count' => (string) $this->bookings->count(),
            'site_name' => (string) (app(SettingsRepository::class)->get('site_name') ?: config('app.name')),
        ];
    }

    public function envelope(): Envelope
    {
        $settings = app(SettingsRepository::class);
        $renderer = app(TemplateRenderer::class);

        $envelope = new Envelope(subject: $renderer->subject(
            'booking.daily_summary',
            $this->tokens(),
            "Today's bookings — {$this->date->format('D j M')} ({$this->bookings->count()})",
        ));

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
            with: [
                'bookings' => $this->bookings,
                'date' => $this->date,
                'intro' => app(TemplateRenderer::class)->intro('booking.daily_summary', $this->tokens()),
            ],
        );
    }
}
