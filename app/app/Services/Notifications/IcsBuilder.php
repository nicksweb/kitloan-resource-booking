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
        $assets = $booking->items->flatMap->allocations
            ->where('released_at', null)
            ->pluck('resource.name')
            ->filter();

        $descriptionLines = array_filter([
            'Resource Booking',
            '',
            "Reference: {$booking->reference}",
            '',
            'Room: '.$booking->roomLabel(),
            'Exam Type: '.($booking->bookingType?->name ?? '—'),
            '',
            'Requested: '.$booking->items->sum('quantity_requested').' × '.$booking->resourcePool->name,
            $assets->isNotEmpty() ? "\nAllocated:\n".$assets->join("\n") : null,
            '',
            'Booked by: '.$booking->bookedBy->name,
        ]);

        return Calendar::create(config('app.name'))
            ->event(function (Event $event) use ($booking, $descriptionLines, $tentative) {
                $event
                    ->name("{$booking->resourcePool->name} – ".$booking->roomCode()." – {$booking->items->sum('quantity_requested')} Devices")
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
