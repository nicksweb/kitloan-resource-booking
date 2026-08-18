<?php

namespace App\Livewire;

use App\Models\Booking;
use App\Models\Location;
use App\Models\ResourcePool;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
class AllBookings extends Component
{
    #[Url]
    public string $from;

    #[Url]
    public string $to;

    #[Url]
    public ?int $resourcePoolId = null;

    #[Url]
    public ?int $locationId = null;

    #[Url]
    public string $search = '';

    public function mount(): void
    {
        $this->from ??= now()->startOfWeek()->format('Y-m-d');
        $this->to ??= now()->endOfWeek()->format('Y-m-d');
    }

    public function render()
    {
        $bookings = Booking::query()
            ->with(['resourcePool', 'location', 'bookingType', 'bookedBy', 'items'])
            ->where('lifecycle_status', 'active')
            ->whereDate('start_at', '>=', $this->from)
            ->whereDate('start_at', '<=', $this->to)
            ->when($this->resourcePoolId, fn ($q) => $q->where('resource_pool_id', $this->resourcePoolId))
            ->when($this->locationId, fn ($q) => $q->where('location_id', $this->locationId))
            ->when($this->search, fn ($q) => $q->where(fn ($q) => $q
                ->where('reference', 'like', "%{$this->search}%")
                ->orWhereHas('bookedBy', fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ))
            ->orderBy('start_at')
            ->get()
            ->groupBy(fn (Booking $b) => $b->start_at->format('Y-m-d'));

        return view('livewire.all-bookings', [
            'bookings' => $bookings,
            'pools' => ResourcePool::enabled()->ordered()->get(),
            'locations' => Location::enabled()->ordered()->get(),
        ]);
    }
}
