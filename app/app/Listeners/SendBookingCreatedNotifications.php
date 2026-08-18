<?php

namespace App\Listeners;

use App\Events\BookingCreated;
use App\Services\Notifications\BookingNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendBookingCreatedNotifications implements ShouldQueue
{
    public function __construct(private readonly BookingNotifier $notifier) {}

    public function handle(BookingCreated $event): void
    {
        $this->notifier->sendCreated($event->booking);
    }
}
