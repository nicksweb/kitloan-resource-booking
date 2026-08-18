<?php

namespace App\Listeners;

use App\Events\BookingUpdated;
use App\Services\Notifications\BookingNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendBookingUpdatedNotifications implements ShouldQueue
{
    public function __construct(private readonly BookingNotifier $notifier) {}

    public function handle(BookingUpdated $event): void
    {
        $this->notifier->sendUpdated($event->booking, $event->changes);
    }
}
