<?php

namespace App\Livewire;

use App\Models\Booking;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
class MyBookings extends Component
{
    use WithPagination;

    #[Url]
    public string $tab = 'upcoming';

    public function render()
    {
        $query = auth()->user()->bookingsOwned()->with(['resourcePool', 'location', 'bookingType', 'items']);

        $query = match ($this->tab) {
            'pending' => $query->where('lifecycle_status', 'active')->where('approval_status', 'pending'),
            'previous' => $query->where('lifecycle_status', 'completed')->orWhere(fn ($q) => $q->where('lifecycle_status', 'active')->where('end_at', '<', now())),
            'cancelled' => $query->where('lifecycle_status', 'cancelled')->where('approval_status', '!=', 'rejected'),
            'rejected' => $query->where('approval_status', 'rejected'),
            default => $query->where('lifecycle_status', 'active')->where('start_at', '>=', now()),
        };

        $bookings = $query->orderByDesc('start_at')->paginate(15);

        return view('livewire.my-bookings', ['bookings' => $bookings]);
    }
}
