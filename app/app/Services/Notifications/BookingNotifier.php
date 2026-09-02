<?php

namespace App\Services\Notifications;

use App\Mail\BookingAmendedMail;
use App\Mail\BookingApprovalRequestMail;
use App\Mail\BookingConfirmedNoticeMail;
use App\Mail\BookingNotificationMail;
use App\Models\Booking;
use App\Settings\SettingsRepository;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Central place all booking-related email is sent from — controllers and
 * services call this rather than touching Mail:: directly. Every send is
 * queued: a failed/slow mail server must never block or roll back a booking
 * that's already been committed to the database.
 */
class BookingNotifier
{
    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly TemplateRenderer $templates,
    ) {}

    public function sendCreated(Booking $booking): void
    {
        $isStaff = $booking->resourcePool->isStaffPool();

        if ($isStaff) {
            $this->notifyOfficers($booking, 'officer_assigned');
        }

        if ($booking->approval_status === 'approved') {
            $this->queueToOwner($booking, 'approved');
            if (! $isStaff) {
                $this->queueConfirmedNoticeToIt($booking);
            }

            return;
        }

        $this->queueToOwner($booking, 'pending');
        $this->queueApprovalRequest($booking);
    }

    public function sendApproved(Booking $booking): void
    {
        $this->queueToOwner($booking, 'approved');
    }

    public function sendRejected(Booking $booking): void
    {
        $this->queueToOwner($booking, 'rejected');
    }

    public function sendReminder(Booking $booking): void
    {
        $this->queueToOwner($booking, 'reminder');
    }

    /**
     * A booking was amended (date/room/quantity/etc). The owner always gets
     * a copy reflecting the new state; IT gets either a full approval
     * request (if the amendment knocked it back to pending — e.g. it now
     * needs more devices than are free, or moved inside the auto-approval
     * window) or a plain FYI with the change summary if it's still/again
     * approved. Either way, IT sees exactly what changed — quantity changes
     * included, which is the whole point of this notification.
     *
     * @param  array<int, string>  $changes
     * @param  array{name: string, email: ?string}|null  $previousOwner  Set when the requestor was reassigned
     */
    public function sendUpdated(Booking $booking, array $changes, ?array $previousOwner = null): void
    {
        $reassigned = collect($changes)->contains(fn ($c) => str_starts_with($c, 'Requestor changed'));

        if ($reassigned) {
            // The new owner is told it's now theirs; the previous owner is told
            // it's no longer theirs. IT still gets the FYI/approval mail below.
            $this->queueToOwner($booking, 'reassigned_to', $changes);

            if (! empty($previousOwner['email']) && $previousOwner['email'] !== $booking->bookedBy?->email) {
                $this->queueMail($previousOwner['email'], new BookingNotificationMail($booking, 'reassigned_away', $changes), 'reassigned-away');
            }
        } else {
            $this->queueToOwner($booking, $booking->approval_status === 'approved' ? 'approved' : 'pending', $changes);
        }

        if ($booking->resourcePool->isStaffPool()) {
            $this->notifyOfficers($booking, 'officer_updated', $changes);
        }

        if ($booking->approval_status === 'pending') {
            $this->queueApprovalRequest($booking, $changes);
        } else {
            $this->queueAmendedFyiToIt($booking, $changes);
        }
    }

    /**
     * Manual "resend" action from the booking detail page — re-sends
     * whichever notification matches the booking's *current* status, not
     * necessarily what was originally sent (e.g. if it was pending and has
     * since been approved, this sends the approved version).
     */
    public function resendOwnerNotification(Booking $booking): void
    {
        $kind = match ($booking->approval_status) {
            'rejected' => 'rejected',
            'approved' => 'approved',
            default => 'pending',
        };

        $this->queueToOwner($booking, $kind);
    }

    public function resendItNotification(Booking $booking): void
    {
        $this->queueToIt($booking);
    }

    /**
     * @param  array<int, string>  $changes
     */
    private function queueToOwner(Booking $booking, string $kind, array $changes = []): void
    {
        if (! $booking->bookedBy?->email) {
            return;
        }

        try {
            Mail::to($booking->bookedBy->email)->queue(new BookingNotificationMail($booking, $kind, $changes));
        } catch (\Throwable $e) {
            Log::error('Failed to queue booking notification email', ['booking' => $booking->reference, 'kind' => $kind, 'error' => $e->getMessage()]);
        }
    }

    /**
     * @param  array<int, string>  $changes
     */
    private function queueToIt(Booking $booking, array $changes = []): void
    {
        $itAddress = $this->settings->get('it_notification_address');

        if (! $itAddress) {
            return;
        }

        try {
            Mail::to($itAddress)->queue(new BookingApprovalRequestMail($booking, $changes));
        } catch (\Throwable $e) {
            Log::error('Failed to queue IT notification email', ['booking' => $booking->reference, 'error' => $e->getMessage()]);
        }
    }

    /**
     * @param  array<int, string>  $changes
     */
    private function queueAmendedFyiToIt(Booking $booking, array $changes): void
    {
        $itAddress = $this->settings->get('it_notification_address');

        if (! $itAddress) {
            return;
        }

        try {
            Mail::to($itAddress)->queue(new BookingAmendedMail($booking, $changes));
        } catch (\Throwable $e) {
            Log::error('Failed to queue IT amendment FYI email', ['booking' => $booking->reference, 'error' => $e->getMessage()]);
        }
    }

    /**
     * IT FYI for a new booking that auto-approved. Suppressed when the
     * `booking.it_confirmed` template is disabled in Administration → Emails.
     */
    private function queueConfirmedNoticeToIt(Booking $booking): void
    {
        if (! $this->templates->isEnabled('booking.it_confirmed')) {
            return;
        }

        $this->queueMail(
            $this->settings->get('it_notification_address'),
            new BookingConfirmedNoticeMail($booking),
            'it-confirmed',
        );
    }

    private function queueMail(?string $address, Mailable $mail, string $context): void
    {
        if (! $address) {
            return;
        }

        try {
            Mail::to($address)->queue($mail);
        } catch (\Throwable $e) {
            Log::error('Failed to queue booking email', ['context' => $context, 'error' => $e->getMessage()]);
        }
    }

    /**
     * The approve/reject request. For a staff pool set to route to the booked
     * officer, it goes to each officer; otherwise (and for equipment) to the IT
     * team address.
     *
     * @param  array<int, string>  $changes
     */
    private function queueApprovalRequest(Booking $booking, array $changes = []): void
    {
        if ($booking->resourcePool->isStaffPool() && $booking->resourcePool->approval_route === 'assigned_officer') {
            foreach ($booking->officers() as $officer) {
                $this->queueMail($officer->email, new BookingApprovalRequestMail($booking, $changes), 'officer-approval');
            }

            return;
        }

        $this->queueToIt($booking, $changes);
    }

    /**
     * "You've been booked" (and "your booking changed") for each officer on a
     * staff-pool booking. Always carries the calendar file.
     *
     * @param  array<int, string>  $changes
     */
    private function notifyOfficers(Booking $booking, string $kind, array $changes = []): void
    {
        foreach ($booking->officers() as $officer) {
            $this->queueMail($officer->email, new BookingNotificationMail($booking, $kind, $changes), 'officer-notice');
        }
    }
}
