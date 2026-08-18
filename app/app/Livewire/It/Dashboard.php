<?php

namespace App\Livewire\It;

use App\Models\Booking;
use App\Models\Resource;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Dashboard extends Component
{
    public function render()
    {
        $today = Booking::query()
            ->with(['resourcePool', 'location', 'bookingType', 'items'])
            ->where('lifecycle_status', 'active')
            ->whereDate('start_at', now())
            ->orderBy('start_at')
            ->get();

        $tomorrow = Booking::query()
            ->where('lifecycle_status', 'active')
            ->whereDate('start_at', now()->addDay())
            ->get();

        $pendingApprovals = Booking::query()
            ->with(['resourcePool', 'bookedBy'])
            ->pending()
            ->orderBy('start_at')
            ->get();

        $metrics = [
            'bookings_today' => $today->count(),
            'devices_today' => $today->sum(fn (Booking $b) => $b->items->sum('quantity_requested')),
            'bookings_tomorrow' => $tomorrow->count(),
            'pending_approvals' => $pendingApprovals->count(),
            'unavailable_assets' => Resource::whereIn('status', ['unavailable', 'maintenance', 'missing'])->count(),
        ];

        $warnings = $this->buildWarnings();

        return view('livewire.it.dashboard', [
            'today' => $today,
            'metrics' => $metrics,
            'pendingApprovals' => $pendingApprovals,
            'warnings' => $warnings,
        ]);
    }

    private function buildWarnings(): array
    {
        $warnings = [];

        $tomorrowUnallocated = Booking::query()
            ->with('resourcePool')
            ->where('lifecycle_status', 'active')
            ->whereDate('start_at', now()->addDay())
            ->whereHas('resourcePool', fn ($q) => $q->where('allocation_mode', 'individual'))
            ->get()
            ->filter(fn (Booking $b) => $b->items->contains(fn ($i) => ! $i->isFullyAllocated()));

        foreach ($tomorrowUnallocated as $booking) {
            $warnings[] = "Booking {$booking->reference} tomorrow has assets not fully allocated.";
        }

        $soonPending = Booking::query()
            ->pending()
            ->where('start_at', '<=', now()->addHours(6))
            ->get();

        foreach ($soonPending as $booking) {
            $warnings[] = "{$booking->reference} is less than 6 hours away and still pending approval.";
        }

        $maintenanceAllocated = Resource::query()
            ->whereIn('status', ['maintenance', 'unavailable', 'missing'])
            ->whereHas('allocations', fn ($q) => $q->whereNull('released_at')
                ->whereHas('bookingItem.booking', fn ($q) => $q->where('lifecycle_status', 'active')->where('start_at', '>=', now())))
            ->get();

        foreach ($maintenanceAllocated as $resource) {
            $warnings[] = "{$resource->name} is marked {$resource->status} but is allocated to an upcoming booking.";
        }

        return $warnings;
    }
}
