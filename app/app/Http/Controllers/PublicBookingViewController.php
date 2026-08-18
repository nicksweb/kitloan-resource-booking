<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use Illuminate\Contracts\View\View;

/**
 * The "magic link" landing page from booking notification emails — a signed,
 * time-limited URL that lets the booking owner see their own booking without
 * an OIDC round-trip. Deliberately a separate, minimal, read-only page (not
 * the full authenticated BookingDetail Livewire component) so the guest-
 * accessible surface stays small and easy to audit: no actions, nothing
 * beyond what the confirmation email itself already summarizes.
 */
class PublicBookingViewController extends Controller
{
    public function show(Booking $booking): View
    {
        $booking->load(['resourcePool', 'location', 'bookingType', 'bookedBy', 'students', 'items.allocations.resource']);

        return view('bookings.public-view', ['booking' => $booking]);
    }
}
