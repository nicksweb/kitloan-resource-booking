<?php

namespace Database\Seeders;

use App\Models\MessageTemplate;
use Illuminate\Database\Seeder;

/**
 * Seeds the editable email copy. Idempotent: existing rows are left untouched
 * (an admin's wording is never overwritten on upgrade), only missing keys are
 * added. `defaults()` is also the source the admin "Reset to default" action
 * reads from.
 */
class MessageTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach (self::defaults() as $key => $default) {
            MessageTemplate::firstOrCreate(['key' => $key], [
                'subject' => $default['subject'] ?? null,
                'intro' => $default['intro'] ?? null,
                'enabled' => $default['enabled'] ?? true,
            ]);
        }
    }

    /**
     * @return array<string, array{label: string, audience: string, subject?: ?string, intro?: ?string, enabled?: bool}>
     */
    public static function defaults(): array
    {
        return [
            'booking.owner_submitted' => [
                'label' => 'Requestor — booking submitted',
                'audience' => 'requestor',
                'subject' => 'Booking submitted: {{ reference }}',
                'intro' => 'Your booking has been submitted and is awaiting IT approval.',
            ],
            'booking.owner_approved' => [
                'label' => 'Requestor — booking confirmed',
                'audience' => 'requestor',
                'subject' => 'Booking confirmed: {{ reference }}',
                'intro' => 'Your booking is confirmed. A calendar invitation is attached.',
            ],
            'booking.owner_rejected' => [
                'label' => 'Requestor — booking declined',
                'audience' => 'requestor',
                'subject' => 'Booking declined: {{ reference }}',
                'intro' => 'Unfortunately your booking could not be approved. Please contact IT if you need help finding another time.',
            ],
            'booking.owner_reminder' => [
                'label' => 'Requestor — reminder',
                'audience' => 'requestor',
                'subject' => 'Reminder: {{ reference }} is tomorrow',
                'intro' => 'This is a reminder that your booking is coming up tomorrow.',
            ],
            'booking.owner_amended' => [
                'label' => 'Requestor — booking updated',
                'audience' => 'requestor',
                'subject' => 'Booking updated: {{ reference }}',
                'intro' => 'Your booking has been amended. The current details are below.',
            ],
            'booking.policy_notice' => [
                'label' => 'Requestor — shared policy notice (appended to every email above)',
                'audience' => 'requestor',
                'subject' => null,
                'intro' => 'All equipment must be returned to IT at the end of the booking unless collection has been arranged with IT in advance.',
            ],
            'booking.it_approval' => [
                'label' => 'IT — approval request',
                'audience' => 'it',
                'subject' => 'Approval needed: {{ reference }}',
                'intro' => 'A booking needs your review.',
            ],
            'booking.it_amended' => [
                'label' => 'IT — amendment FYI',
                'audience' => 'it',
                'subject' => 'Booking amended: {{ reference }}',
                'intro' => 'A booking was amended and stays approved — no action needed, but the changes are listed below.',
            ],
            'booking.daily_summary' => [
                'label' => 'IT — daily summary',
                'audience' => 'it',
                'subject' => "Today's bookings — {{ date }}",
                'intro' => null,
            ],
        ];
    }
}
