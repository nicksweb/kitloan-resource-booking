<?php

namespace App\Console\Commands;

use App\Mail\DailySummaryMail;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendDailyBookingSummary extends Command
{
    protected $signature = 'bookings:send-daily-summary';

    protected $description = "Email IT Operators/Administrators today's booking schedule (only sent on days with bookings, only to those who haven't opted out)";

    public function handle(): int
    {
        $today = now();

        $bookings = Booking::query()
            ->with(['resourcePool', 'location', 'bookingType', 'items.allocations.resource.user'])
            ->where('lifecycle_status', 'active')
            ->whereDate('start_at', $today)
            ->orderBy('start_at')
            ->get();

        if ($bookings->isEmpty()) {
            $this->info('No bookings today — nothing to send.');

            return self::SUCCESS;
        }

        $recipients = User::query()
            ->where('enabled', true)
            ->where('receives_daily_summary', true)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['administrator', 'it_operator']))
            ->get();

        foreach ($recipients as $recipient) {
            Mail::to($recipient->email)->queue(new DailySummaryMail($bookings, $today));
        }

        $this->info("Sent daily summary ({$bookings->count()} booking(s)) to {$recipients->count()} recipient(s).");

        return self::SUCCESS;
    }
}
