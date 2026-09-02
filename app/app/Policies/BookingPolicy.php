<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;

class BookingPolicy
{
    // Admins are already granted every ability via Gate::before in AppServiceProvider.

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Booking $booking): bool
    {
        return $user->hasAnyRole(['it_operator'])
            || $booking->booked_by_user_id === $user->id
            || $booking->hasOfficer($user);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function createForOthers(User $user): bool
    {
        return $user->hasAnyRole(['administrator', 'it_operator']);
    }

    public function update(User $user, Booking $booking): bool
    {
        if ($user->hasRole('it_operator')) {
            return true;
        }

        return $booking->booked_by_user_id === $user->id && $booking->isEditableByOwner();
    }

    public function cancel(User $user, Booking $booking): bool
    {
        if ($user->hasRole('it_operator')) {
            return true;
        }

        return $booking->booked_by_user_id === $user->id && $booking->isCancellable();
    }

    public function approve(User $user, Booking $booking): bool
    {
        return $user->hasAnyRole(['administrator', 'it_operator']) || $booking->hasOfficer($user);
    }

    public function reject(User $user, Booking $booking): bool
    {
        return $user->hasAnyRole(['administrator', 'it_operator']) || $booking->hasOfficer($user);
    }

    public function reallocate(User $user, Booking $booking): bool
    {
        return $user->hasAnyRole(['administrator', 'it_operator']);
    }
}
