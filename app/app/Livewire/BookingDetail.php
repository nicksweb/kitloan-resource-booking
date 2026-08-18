<?php

namespace App\Livewire;

use App\Models\Booking;
use App\Models\Resource;
use App\Services\Audit\AuditLogger;
use App\Services\Booking\AvailabilityService;
use App\Services\Booking\BookingService;
use App\Services\Notifications\BookingNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class BookingDetail extends Component
{
    public Booking $booking;

    public ?int $substitutingAllocationId = null;

    public ?int $replacementResourceId = null;

    public string $substitutionReason = '';

    public function mount(Booking $booking): void
    {
        $this->authorize('view', $booking);
        $this->booking = $booking->load([
            'items.resourcePool', 'items.allocations.resource.externalAssetLink',
            'students', 'location', 'bookingType', 'bookedBy', 'createdBy', 'approvedBy', 'rejectedBy', 'cancelledBy',
        ]);
    }

    public function render()
    {
        return view('livewire.booking-detail');
    }

    #[Computed]
    public function substitutionOptions()
    {
        if (! $this->substitutingAllocationId) {
            return collect();
        }

        $allocation = $this->booking->items->flatMap->allocations->firstWhere('id', $this->substitutingAllocationId);
        $pool = $allocation?->bookingItem->resourcePool;

        if (! $pool) {
            return collect();
        }

        return app(AvailabilityService::class)
            ->availableResourceIds($pool, $this->booking->start_at, $this->booking->end_at, excludeBookingId: $this->booking->id)
            ->map(fn ($id) => Resource::find($id))
            ->filter();
    }

    public function approve(BookingNotifier $notifier, AuditLogger $auditLogger): void
    {
        $this->authorize('approve', $this->booking);

        $this->booking->update([
            'approval_status' => 'approved',
            'approved_by_user_id' => auth()->id(),
            'approved_at' => now(),
        ]);

        $auditLogger->log('booking.approved', auth()->user()->name." approved {$this->booking->reference}", auth()->user(), $this->booking->id);
        $notifier->sendApproved($this->booking->fresh());

        $this->booking->refresh();
        session()->flash('success', "{$this->booking->reference} approved.");
    }

    public function reject(string $reason, BookingNotifier $notifier, BookingService $bookingService, AuditLogger $auditLogger): void
    {
        $this->authorize('reject', $this->booking);
        $this->validate(['substitutionReason' => 'sometimes'], [], []);

        if (trim($reason) === '') {
            $this->addError('reason', 'A rejection reason is required.');

            return;
        }

        DB::transaction(function () use ($reason) {
            $this->booking->update([
                'approval_status' => 'rejected',
                'lifecycle_status' => 'cancelled',
                'rejected_by_user_id' => auth()->id(),
                'rejected_at' => now(),
                'rejection_reason' => $reason,
            ]);

            \App\Models\BookingResourceAllocation::whereIn('booking_item_id', $this->booking->items()->pluck('id'))
                ->whereNull('released_at')->update(['released_at' => now()]);
        });

        app(AuditLogger::class)->log('booking.rejected', auth()->user()->name." rejected {$this->booking->reference}: {$reason}", auth()->user(), $this->booking->id);
        $notifier->sendRejected($this->booking->fresh());

        $this->booking->refresh();
        session()->flash('success', "{$this->booking->reference} rejected.");
    }

    public function resendToOwner(BookingNotifier $notifier, AuditLogger $auditLogger): void
    {
        Gate::authorize('operate-bookings');

        if (! $this->booking->bookedBy?->email) {
            session()->flash('error', "{$this->booking->bookedBy?->name} has no email address on file.");

            return;
        }

        $notifier->resendOwnerNotification($this->booking);
        $auditLogger->log(
            'booking.notification_resent',
            "{$this->userName()} resent the {$this->booking->reference} notification to {$this->booking->bookedBy->name}",
            auth()->user(),
            $this->booking->id,
        );

        session()->flash('success', "Notification resent to {$this->booking->bookedBy->name}.");
    }

    public function resendToIt(BookingNotifier $notifier, AuditLogger $auditLogger): void
    {
        Gate::authorize('operate-bookings');

        if (! app(\App\Settings\SettingsRepository::class)->get('it_notification_address')) {
            session()->flash('error', 'No IT notification address is configured (Administration -> Settings).');

            return;
        }

        $notifier->resendItNotification($this->booking);
        $auditLogger->log(
            'booking.notification_resent',
            "{$this->userName()} resent the {$this->booking->reference} notification to IT",
            auth()->user(),
            $this->booking->id,
        );

        session()->flash('success', 'Notification resent to IT.');
    }

    private function userName(): string
    {
        return auth()->user()->name;
    }

    public function cancel(BookingService $bookingService): void
    {
        $this->authorize('cancel', $this->booking);
        $bookingService->cancel($this->booking, auth()->user());
        $this->booking->refresh();
        session()->flash('success', "{$this->booking->reference} cancelled.");
    }

    public function startSubstitution(int $allocationId): void
    {
        $this->authorize('reallocate', $this->booking);
        $this->substitutingAllocationId = $allocationId;
        $this->replacementResourceId = null;
        $this->substitutionReason = '';
    }

    public function confirmSubstitution(AuditLogger $auditLogger): void
    {
        $this->authorize('reallocate', $this->booking);

        $this->validate([
            'replacementResourceId' => ['required', 'exists:resources,id'],
            'substitutionReason' => ['required', 'string', 'max:500'],
        ]);

        $oldAllocation = \App\Models\BookingResourceAllocation::findOrFail($this->substitutingAllocationId);

        DB::transaction(function () use ($oldAllocation) {
            $oldAllocation->update(['released_at' => now()]);

            \App\Models\BookingResourceAllocation::create([
                'booking_item_id' => $oldAllocation->booking_item_id,
                'resource_id' => $this->replacementResourceId,
                'allocated_at' => now(),
                'replaced_from_allocation_id' => $oldAllocation->id,
                'replacement_reason' => $this->substitutionReason,
            ]);
        });

        $oldResource = $oldAllocation->resource;
        $newResource = Resource::find($this->replacementResourceId);

        $auditLogger->log(
            'booking.resource_substituted',
            sprintf('%s replaced %s with %s on %s: %s', auth()->user()->name, $oldResource->name, $newResource->name, $this->booking->reference, $this->substitutionReason),
            auth()->user(),
            $this->booking->id,
            $newResource->id,
        );

        $this->substitutingAllocationId = null;
        $this->booking->refresh();
        session()->flash('success', 'Resource substituted.');
    }
}
