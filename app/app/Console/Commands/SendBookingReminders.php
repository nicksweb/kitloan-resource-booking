<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\Notifications\BookingNotifier;
use Illuminate\Console\Command;

class SendBookingReminders extends Command
{
    protected $signature = 'bookings:send-reminders';

    protected $description = 'Send a reminder to booking owners ~24 hours before their booking starts';

    /**
     * Runs hourly; catches bookings starting 23-25 hours from now so each
     * booking is only ever inside the window for a single run.
     */
    public function handle(BookingNotifier $notifier): int
    {
        $bookings = Booking::query()
            ->with(['bookedBy', 'resourcePool', 'location'])
            ->where('lifecycle_status', 'active')
            ->where('approval_status', 'approved')
            ->whereBetween('start_at', [now()->addHours(23), now()->addHours(25)])
            ->get();

        foreach ($bookings as $booking) {
            $notifier->sendReminder($booking);
        }

        $this->info("Sent {$bookings->count()} reminder(s).");

        return self::SUCCESS;
    }
}
