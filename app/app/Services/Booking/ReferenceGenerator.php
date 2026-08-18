<?php

namespace App\Services\Booking;

use App\Models\Booking;

class ReferenceGenerator
{
    /**
     * Builds a human-friendly reference (e.g. EX-2026-00421) from the
     * booking's own auto-increment id, so it's unique for free with no extra
     * locking. Call only after the booking row has been inserted.
     */
    public function generate(Booking $booking): string
    {
        $prefix = $booking->resourcePool->booking_reference_prefix ?: 'BK';
        $year = $booking->created_at?->format('Y') ?? now()->format('Y');

        return sprintf('%s-%s-%05d', $prefix, $year, $booking->id);
    }
}
