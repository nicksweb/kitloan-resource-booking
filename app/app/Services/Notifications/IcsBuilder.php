<?php

namespace App\Services\Notifications;

use App\Models\Booking;
use Spatie\IcalendarGenerator\Components\Calendar;
use Spatie\IcalendarGenerator\Components\Event;
use Spatie\IcalendarGenerator\Enums\EventStatus;

class IcsBuilder
{
    /**
     * @param  bool  $tentative  Mark the event TENTATIVE — used for the IT
     *                           approval-request email, where the slot isn't
     *                           confirmed yet.
     */
    public function forBooking(Booking $booking, bool $tentative = false): string
    {
        $isStaff = $booking->resourcePool->isStaffPool();

        $assets = $booking->items->flatMap->allocations
            ->where('released_at', null)
            ->pluck('resource.name')
            ->filter();

        $officers = $isStaff ? $booking->officers()->pluck('name')->join(', ') : '';

        $descriptionLines = array_filter([
            $isStaff ? 'IT Support Booking' : 'Resource Booking',
            '',
            "Reference: {$booking->reference}",
            '',
            'Room: '.$booking->roomLabel(),
            $isStaff ? 'IT Officer: '.($officers ?: 'any available officer') : 'Exam Type: '.($booking->bookingType?->name ?? '—'),
            $isStaff && $booking->notes ? 'Issue: '.$booking->notes : null,
            $isStaff ? null : 'Requested: '.$booking->items->sum('quantity_requested').' × '.$booking->resourcePool->name,
            (! $isStaff && $assets->isNotEmpty()) ? "\nAllocated:\n".$assets->join("\n") : null,
            $booking->helpdesk_url ? 'Helpdesk ticket: '.$booking->helpdesk_url : null,
            '',
            'Booked by: '.$booking->bookedBy->name,
        ]);

        $title = $isStaff
            ? 'IT Support – '.$booking->roomCode().($officers ? " – {$officers}" : '')
            : "{$booking->resourcePool->name} – ".$booking->roomCode()." – {$booking->items->sum('quantity_requested')} Devices";

        return Calendar::create(config('app.name'))
            ->event(function (Event $event) use ($booking, $descriptionLines, $tentative, $title) {
                $event
                    ->name($title)
                    ->description(implode("\n", $descriptionLines))
                    ->uniqueIdentifier("booking-{$booking->id}@".parse_url(config('app.url'), PHP_URL_HOST))
                    ->startsAt($booking->start_at)
                    ->endsAt($booking->end_at);

                if ($tentative) {
                    $event->status(EventStatus::Tentative);
                }

                if ($booking->location) {
                    $event->address($booking->location->name);
                } elseif ($booking->room_choice === 'pickup') {
                    $event->address('Pick-up from IT');
                }
            })
            ->get();
    }
}
