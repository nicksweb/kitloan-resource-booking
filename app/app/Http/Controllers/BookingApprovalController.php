<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\BookingNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;

/**
 * Handles the signed-link approve/reject actions from the IT notification
 * email. The link's signature only proves the link wasn't tampered with —
 * the visitor must still be authenticated as IT/admin (enforced by the
 * 'can:operate-bookings' style policy check below) before anything happens.
 */
class BookingApprovalController extends Controller
{
    public function approve(Request $request, Booking $booking, AuditLogger $auditLogger): RedirectResponse
    {
        $this->authorize('approve', $booking);

        if ($booking->approval_status === 'approved') {
            return redirect()->route('bookings.show', $booking)->with('success', "{$booking->reference} was already approved.");
        }

        $booking->update([
            'approval_status' => 'approved',
            'approved_by_user_id' => $request->user()->id,
            'approved_at' => now(),
        ]);

        $auditLogger->log('booking.approved', $request->user()->name." approved {$booking->reference} (via email link)", $request->user(), $booking->id);
        app(BookingNotifier::class)->sendApproved($booking->fresh());

        return redirect()->route('bookings.show', $booking)->with('success', "{$booking->reference} approved.");
    }

    public function showReject(Booking $booking): View
    {
        $this->authorize('reject', $booking);

        return view('bookings.reject', ['booking' => $booking]);
    }

    public function reject(Request $request, Booking $booking, AuditLogger $auditLogger): RedirectResponse
    {
        $this->authorize('reject', $booking);

        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        $booking->update([
            'approval_status' => 'rejected',
            'lifecycle_status' => 'cancelled',
            'rejected_by_user_id' => $request->user()->id,
            'rejected_at' => now(),
            'rejection_reason' => $data['reason'],
        ]);

        \App\Models\BookingResourceAllocation::whereIn('booking_item_id', $booking->items()->pluck('id'))
            ->whereNull('released_at')->update(['released_at' => now()]);

        $auditLogger->log('booking.rejected', $request->user()->name." rejected {$booking->reference}: {$data['reason']}", $request->user(), $booking->id);
        app(BookingNotifier::class)->sendRejected($booking->fresh());

        return redirect()->route('bookings.show', $booking)->with('success', "{$booking->reference} rejected.");
    }
}
