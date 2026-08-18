<?php

namespace App\Services\Notifications;

use App\Models\Booking;
use Spatie\IcalendarGenerator\Components\Calendar;
use Spatie\IcalendarGenerator\Components\Event;

class IcsBuilder
{
    public function forBooking(Booking $booking): string
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
            'Room: '.($booking->location?->name ?? '—'),
            'Exam Type: '.($booking->bookingType?->name ?? '—'),
            '',
            'Requested: '.$booking->items->sum('quantity_requested').' × '.$booking->resourcePool->name,
            $assets->isNotEmpty() ? "\nAllocated:\n".$assets->join("\n") : null,
            '',
            'Booked by: '.$booking->bookedBy->name,
        ]);

        return Calendar::create(config('app.name'))
            ->event(function (Event $event) use ($booking, $descriptionLines) {
                $event
                    ->name("{$booking->resourcePool->name} – ".($booking->location?->code ?? '')." – {$booking->items->sum('quantity_requested')} Devices")
                    ->description(implode("\n", $descriptionLines))
                    ->uniqueIdentifier("booking-{$booking->id}@".parse_url(config('app.url'), PHP_URL_HOST))
                    ->startsAt($booking->start_at)
                    ->endsAt($booking->end_at);

                if ($booking->location) {
                    $event->address($booking->location->name);
                }
            })
            ->get();
    }
}
