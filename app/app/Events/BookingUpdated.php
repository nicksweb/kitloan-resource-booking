<?php

namespace App\Events;

use App\Models\Booking;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BookingUpdated
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<int, string>  $changes  Human-readable summary lines (only fired when non-empty)
     */
    public function __construct(public Booking $booking, public array $changes) {}
}
