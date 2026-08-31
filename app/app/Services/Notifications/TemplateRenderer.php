<?php

namespace App\Services\Notifications;

use App\Models\Booking;
use App\Models\MessageTemplate;
use App\Settings\SettingsRepository;
use Illuminate\Support\Facades\URL;

/**
 * Resolves the admin-editable email copy (see MessageTemplate) and substitutes
 * {{ token }} placeholders from a fixed whitelist. Placeholders are the only
 * dynamic part — the text is never evaluated as Blade or PHP, so an admin
 * cannot inject executable markup.
 */
class TemplateRenderer
{
    /** @var array<string, MessageTemplate>|null */
    private ?array $cache = null;

    public function __construct(private readonly SettingsRepository $settings) {}

    /**
     * Every placeholder an email template may reference.
     *
     * @return array<int, string>
     */
    public function availableTokens(): array
    {
        return [
            'reference', 'requestor_name', 'requestor_email', 'date', 'start_time',
            'end_time', 'room', 'campus', 'pool', 'quantity', 'booking_type',
            'status', 'notes', 'site_name', 'it_email', 'view_url',
        ];
    }

    /**
     * @param  array<string, string>  $extra  Overrides / additions (e.g. a pre-built view_url).
     * @return array<string, string>
     */
    public function tokensFor(Booking $booking, array $extra = []): array
    {
        $booking->loadMissing(['resourcePool', 'location', 'bookingType', 'bookedBy', 'items']);

        $status = match ($booking->approval_status) {
            'approved' => 'Approved',
            'rejected' => 'Declined',
            default => 'Awaiting approval',
        };

        return array_merge([
            'reference' => (string) $booking->reference,
            'requestor_name' => (string) ($booking->bookedBy?->name ?? ''),
            'requestor_email' => (string) ($booking->bookedBy?->email ?? ''),
            'date' => $booking->start_at->format('l j F Y'),
            'start_time' => $booking->start_at->format('g:i A'),
            'end_time' => $booking->end_at->format('g:i A'),
            'room' => $booking->roomLabel(),
            'campus' => (string) ($booking->location?->campus ?? ''),
            'pool' => (string) $booking->resourcePool->name,
            'quantity' => (string) $booking->items->sum('quantity_requested'),
            'booking_type' => (string) ($booking->bookingType?->name ?? '—'),
            'status' => $status,
            'notes' => (string) ($booking->notes ?? ''),
            'site_name' => (string) ($this->settings->get('site_name') ?: config('app.name')),
            'it_email' => (string) ($this->settings->get('it_notification_address') ?? ''),
            'view_url' => URL::temporarySignedRoute('bookings.public-view', now()->addDays(30), ['booking' => $booking->reference]),
        ], $extra);
    }

    /** @param  array<string, string>  $tokens */
    public function subject(string $key, array $tokens, string $fallback): string
    {
        $template = $this->template($key);
        $raw = $template && $template->enabled && $template->subject !== null && $template->subject !== ''
            ? $template->subject
            : $fallback;

        return $this->substitute($raw, $tokens);
    }

    /**
     * The intro paragraph, or null when the template is disabled/blank.
     *
     * @param  array<string, string>  $tokens
     */
    public function intro(string $key, array $tokens): ?string
    {
        $template = $this->template($key);
        if (! $template || ! $template->enabled || $template->intro === null || trim($template->intro) === '') {
            return null;
        }

        return $this->substitute($template->intro, $tokens);
    }

    /** The shared "policy notice" block, or null when disabled/blank. */
    public function policyNotice(array $tokens): ?string
    {
        return $this->intro('booking.policy_notice', $tokens);
    }

    /**
     * Whether a template is enabled — lets a caller suppress an optional email
     * class entirely by turning its template off in Administration → Emails.
     * A key with no row yet is treated as enabled.
     */
    public function isEnabled(string $key): bool
    {
        return $this->template($key)?->enabled ?? true;
    }

    private function template(string $key): ?MessageTemplate
    {
        $this->cache ??= MessageTemplate::all()->keyBy('key')->all();

        return $this->cache[$key] ?? null;
    }

    /** @param  array<string, string>  $tokens */
    private function substitute(string $text, array $tokens): string
    {
        return preg_replace_callback(
            '/\{\{\s*([a-z_]+)\s*\}\}/',
            fn (array $m) => array_key_exists($m[1], $tokens) ? $tokens[$m[1]] : $m[0],
            $text,
        );
    }
}
