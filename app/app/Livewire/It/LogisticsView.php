<?php

namespace App\Livewire\It;

use App\Models\Booking;
use App\Models\BookingType;
use App\Models\Location;
use App\Models\ResourcePool;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
class LogisticsView extends Component
{
    #[Url]
    public string $date;

    #[Url]
    public ?int $resourcePoolId = null;

    #[Url]
    public ?int $locationId = null;

    #[Url]
    public ?int $bookingTypeId = null;

    #[Url]
    public string $approvalStatus = '';

    public function mount(): void
    {
        $this->date ??= now()->format('Y-m-d');
    }

    public function render()
    {
        $bookings = Booking::query()
            ->with(['resourcePool', 'location', 'bookingType', 'items.allocations.resource'])
            ->where('lifecycle_status', 'active')
            ->whereDate('start_at', $this->date)
            ->when($this->resourcePoolId, fn ($q) => $q->where('resource_pool_id', $this->resourcePoolId))
            ->when($this->locationId, fn ($q) => $q->where('location_id', $this->locationId))
            ->when($this->bookingTypeId, fn ($q) => $q->where('booking_type_id', $this->bookingTypeId))
            ->when($this->approvalStatus, fn ($q) => $q->where('approval_status', $this->approvalStatus))
            ->orderBy('start_at')
            ->get();

        return view('livewire.it.logistics-view', [
            'bookings' => $bookings,
            'pools' => ResourcePool::enabled()->ordered()->get(),
            'locations' => Location::enabled()->ordered()->get(),
            'bookingTypes' => BookingType::enabled()->ordered()->get(),
        ]);
    }
}
