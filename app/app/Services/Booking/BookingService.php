<?php

namespace App\Services\Booking;

use App\Events\BookingCreated;
use App\Events\BookingUpdated;
use App\Exceptions\BookingConflictException;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\BookingResourceAllocation;
use App\Models\Resource;
use App\Models\ResourcePool;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Settings\SettingsRepository;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class BookingService
{
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly ApprovalEvaluator $approvalEvaluator,
        private readonly ReferenceGenerator $referenceGenerator,
        private readonly AuditLogger $auditLogger,
        private readonly SettingsRepository $settings,
    ) {}

    /**
     * Create a booking. $data shape:
     * [
     *   'resource_pool_id' => int,               // primary pool
     *   'location_id' => ?int,
     *   'booking_type_id' => ?int,
     *   'start_at' => CarbonInterface,
     *   'end_at' => CarbonInterface,
     *   'notes' => ?string,
     *   'students' => [['student_name' => ..., 'student_identifier' => ...], ...],
     *   'items' => [                              // first item must match resource_pool_id
     *       ['resource_pool_id' => int, 'quantity' => int, 'resource_ids' => ?int[]],
     *       ...
     *   ],
     *   'conflict_override' => bool,              // admin only
     *   'override_reason' => ?string,
     * ]
     *
     * @throws BookingConflictException when availability changed underneath the request
     */
    public function create(array $data, User $bookedBy, User $createdBy): Booking
    {
        $items = $data['items'];
        $primaryPool = ResourcePool::findOrFail($data['resource_pool_id']);
        $start = $data['start_at'];
        $end = $data['end_at'];
        $override = (bool) ($data['conflict_override'] ?? false);

        if (! $override) {
            $errors = $this->approvalEvaluator->validateWindow($primaryPool, $start, $end);
            if ($errors) {
                throw new BookingConflictException(implode(' ', $errors));
            }
        }

        $booking = DB::transaction(function () use ($data, $items, $primaryPool, $start, $end, $bookedBy, $createdBy, $override) {
            $booking = Booking::create([
                'resource_pool_id' => $primaryPool->id,
                'location_id' => $data['location_id'] ?? null,
                'booking_type_id' => $data['booking_type_id'] ?? null,
                'booked_by_user_id' => $bookedBy->id,
                'created_by_user_id' => $createdBy->id,
                'start_at' => $start,
                'end_at' => $end,
                'notes' => $data['notes'] ?? null,
                'approval_status' => 'pending',
                'allocation_status' => 'unallocated',
                'lifecycle_status' => 'active',
                'conflict_override' => $override,
                'override_reason' => $data['override_reason'] ?? null,
            ]);

            $booking->reference = $this->referenceGenerator->generate($booking);
            $booking->save();

            foreach ($data['students'] ?? [] as $student) {
                $booking->students()->create($student);
            }

            $totalPrimaryQuantity = 0;

            foreach ($items as $itemData) {
                $pool = $itemData['resource_pool_id'] == $primaryPool->id
                    ? $primaryPool
                    : ResourcePool::findOrFail($itemData['resource_pool_id']);

                $quantity = (int) ($itemData['resource_ids'] ? count($itemData['resource_ids']) : $itemData['quantity']);

                if ($pool->id === $primaryPool->id) {
                    $totalPrimaryQuantity = $quantity;
                }

                $item = $booking->items()->create([
                    'resource_pool_id' => $pool->id,
                    'quantity_requested' => $quantity,
                ]);

                $this->allocateItem($item, $pool, $start, $end, $itemData['resource_ids'] ?? null, $override);
            }

            $reasons = $override ? [] : $this->approvalEvaluator->reasonsRequiringApproval(
                $primaryPool,
                $start,
                $end,
                $booking->bookingType,
                $totalPrimaryQuantity,
            );

            if ($reasons === []) {
                $booking->approval_status = 'approved';
                $booking->auto_approved = true;
                $booking->approved_at = now();
            } else {
                $booking->approval_status = 'pending';
            }

            $booking->allocation_status = 'allocated';
            $booking->save();

            $this->auditLogger->log(
                'booking.created',
                $override
                    ? "{$createdBy->name} created {$booking->reference} (conflict override)"
                    : "{$createdBy->name} created {$booking->reference}",
                $createdBy,
                $booking->id,
            );

            if ($booking->auto_approved) {
                $this->auditLogger->log(
                    'booking.auto_approved',
                    "System auto-approved {$booking->reference}",
                    null,
                    $booking->id,
                );
            } else {
                $this->auditLogger->log(
                    'booking.pending_approval',
                    "{$booking->reference} requires approval: ".implode(' ', $reasons),
                    null,
                    $booking->id,
                );
            }

            return $booking->fresh(['items.allocations.resource', 'students', 'resourcePool', 'location', 'bookingType', 'bookedBy']);
        });

        event(new BookingCreated($booking));

        return $booking;
    }

    /**
     * Amend an existing booking — date/time, room, exam type, notes,
     * students, and/or the requested resources — without cancelling and
     * recreating it. $data has the same shape as create(), minus
     * booked_by/created_by (the booking keeps its original owner unless the
     * caller explicitly changes booked_by_user_id).
     *
     * Re-runs the exact same conflict/allocation checks as create(): old
     * allocations are released and old items removed *before* re-allocating,
     * inside the same transaction, so the availability queries naturally see
     * the booking's own current slot as free again — no special-casing
     * needed to "exclude itself".
     *
     * Approval: an edit by IT/Admin is auto-approved directly (they already
     * have approval authority — forcing their own edit back to "pending"
     * would just be friction). An edit by the booking's owner re-runs the
     * full auto-approval evaluation, exactly like a new booking, since they
     * aren't authorised to self-approve — and, additionally, an owner who
     * increases the requested quantity on an *already-approved* booking always
     * lands back in "pending", even if the new quantity would still have
     * auto-approved on a fresh booking. Reducing the quantity (or leaving it
     * unchanged) keeps the existing approval.
     *
     * @throws BookingConflictException when validation or availability fails
     */
    public function update(Booking $booking, array $data, User $actor): Booking
    {
        $items = $data['items'];
        $primaryPool = ResourcePool::findOrFail($data['resource_pool_id']);
        $start = $data['start_at'];
        $end = $data['end_at'];
        $override = (bool) ($data['conflict_override'] ?? false);
        $isPrivileged = $actor->hasAnyRole(['administrator', 'it_operator']);

        if (! $override) {
            $errors = $this->approvalEvaluator->validateWindow($primaryPool, $start, $end);
            if ($errors) {
                throw new BookingConflictException(implode(' ', $errors));
            }
        }

        $before = $booking->only(['start_at', 'end_at', 'location_id', 'booking_type_id']);
        $beforeSnapshot = $this->captureChangeSnapshot($booking);
        $wasApproved = $booking->approval_status === 'approved';
        $previousPrimaryQuantity = (int) $booking->items()
            ->where('resource_pool_id', $booking->resource_pool_id)
            ->value('quantity_requested');

        $updated = DB::transaction(function () use ($booking, $data, $items, $primaryPool, $start, $end, $actor, $override, $isPrivileged, $before, $wasApproved, $previousPrimaryQuantity) {
            $oldItemIds = $booking->items()->pluck('id');
            BookingResourceAllocation::whereIn('booking_item_id', $oldItemIds)->whereNull('released_at')->update(['released_at' => now()]);
            $booking->items()->delete();

            $booking->update([
                'resource_pool_id' => $primaryPool->id,
                'location_id' => $data['location_id'] ?? null,
                'booking_type_id' => $data['booking_type_id'] ?? null,
                'start_at' => $start,
                'end_at' => $end,
                'notes' => $data['notes'] ?? null,
                'conflict_override' => $override,
                'override_reason' => $data['override_reason'] ?? null,
            ]);

            if (array_key_exists('students', $data)) {
                $booking->students()->delete();
                foreach ($data['students'] as $student) {
                    $booking->students()->create($student);
                }
            }

            $totalPrimaryQuantity = 0;

            foreach ($items as $itemData) {
                $pool = $itemData['resource_pool_id'] == $primaryPool->id
                    ? $primaryPool
                    : ResourcePool::findOrFail($itemData['resource_pool_id']);

                $quantity = (int) ($itemData['resource_ids'] ? count($itemData['resource_ids']) : $itemData['quantity']);

                if ($pool->id === $primaryPool->id) {
                    $totalPrimaryQuantity = $quantity;
                }

                $item = $booking->items()->create([
                    'resource_pool_id' => $pool->id,
                    'quantity_requested' => $quantity,
                ]);

                $this->allocateItem($item, $pool, $start, $end, $itemData['resource_ids'] ?? null, $override);
            }

            if ($isPrivileged) {
                $booking->approval_status = 'approved';
                $booking->auto_approved = false;
                $booking->approved_by_user_id = $actor->id;
                $booking->approved_at = now();
                $reasons = [];
            } else {
                $reasons = $override ? [] : $this->approvalEvaluator->reasonsRequiringApproval(
                    $primaryPool, $start, $end, $booking->bookingType, $totalPrimaryQuantity,
                );

                if (! $override && $wasApproved && $totalPrimaryQuantity > $previousPrimaryQuantity) {
                    $reasons[] = sprintf(
                        'Quantity increased from %d to %d after approval.',
                        $previousPrimaryQuantity,
                        $totalPrimaryQuantity,
                    );
                }

                if ($reasons === []) {
                    $booking->approval_status = 'approved';
                    $booking->auto_approved = true;
                    $booking->approved_at = now();
                } else {
                    $booking->approval_status = 'pending';
                    $booking->auto_approved = false;
                    $booking->approved_at = null;
                    $booking->approved_by_user_id = null;
                }
            }

            $booking->allocation_status = 'allocated';
            $booking->save();

            $after = $booking->only(['start_at', 'end_at', 'location_id', 'booking_type_id']);

            $this->auditLogger->log(
                'booking.updated',
                $override
                    ? "{$actor->name} amended {$booking->reference} (conflict override)"
                    : "{$actor->name} amended {$booking->reference}",
                $actor,
                $booking->id,
                null,
                $before,
                $after,
            );

            if (! $isPrivileged && $reasons !== []) {
                $this->auditLogger->log(
                    'booking.pending_approval',
                    "{$booking->reference} requires re-approval after amendment: ".implode(' ', $reasons),
                    null,
                    $booking->id,
                );
            }

            return $booking->fresh(['items.allocations.resource', 'students', 'resourcePool', 'location', 'bookingType', 'bookedBy']);
        });

        $changes = $this->summarizeChanges($beforeSnapshot, $this->captureChangeSnapshot($updated));
        if ($changes) {
            event(new BookingUpdated($updated, $changes));
        }

        return $updated;
    }

    /**
     * @return array{start_at: CarbonInterface, end_at: CarbonInterface, location_name: ?string, booking_type_name: ?string, quantity: int, pool_name: string}
     */
    private function captureChangeSnapshot(Booking $booking): array
    {
        $booking->loadMissing(['location', 'bookingType', 'resourcePool', 'items']);

        return [
            'start_at' => $booking->start_at,
            'end_at' => $booking->end_at,
            'location_name' => $booking->location?->name,
            'booking_type_name' => $booking->bookingType?->name,
            'quantity' => (int) ($booking->items->firstWhere('resource_pool_id', $booking->resource_pool_id)?->quantity_requested ?? 0),
            'pool_name' => $booking->resourcePool->name,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function summarizeChanges(array $before, array $after): array
    {
        $changes = [];

        if (! $before['start_at']->equalTo($after['start_at']) || ! $before['end_at']->equalTo($after['end_at'])) {
            $changes[] = sprintf(
                'Time changed from %s %s-%s to %s %s-%s',
                $before['start_at']->format('D j M'), $before['start_at']->format('g:i A'), $before['end_at']->format('g:i A'),
                $after['start_at']->format('D j M'), $after['start_at']->format('g:i A'), $after['end_at']->format('g:i A'),
            );
        }

        if ($before['location_name'] !== $after['location_name']) {
            $changes[] = sprintf('Room changed from %s to %s', $before['location_name'] ?? 'none', $after['location_name'] ?? 'none');
        }

        if ($before['booking_type_name'] !== $after['booking_type_name']) {
            $changes[] = sprintf('Exam type changed from %s to %s', $before['booking_type_name'] ?? 'none', $after['booking_type_name'] ?? 'none');
        }

        if ($before['quantity'] !== $after['quantity']) {
            $changes[] = sprintf('%s quantity changed from %d to %d', $after['pool_name'], $before['quantity'], $after['quantity']);
        }

        return $changes;
    }

    private function allocateItem(
        BookingItem $item,
        ResourcePool $pool,
        CarbonInterface $start,
        CarbonInterface $end,
        ?array $requestedResourceIds,
        bool $override,
    ): void {
        if ($pool->isQuantityTracked()) {
            if (! $override) {
                // Exclude this booking itself — its booking_item row was already
                // inserted before this check runs, so without this it would
                // double-count its own requested quantity as "already committed".
                $remaining = $this->availability->availableQuantity($pool, $start, $end, excludeBookingId: $item->booking_id, lock: true);
                if ($remaining < $item->quantity_requested) {
                    throw new BookingConflictException(
                        "Only {$remaining} of \"{$pool->name}\" remain available for this time — availability changed. Please review and resubmit."
                    );
                }
            }

            return;
        }

        // Individual mode: either the caller picked specific resources, or we
        // auto-pick from what's currently available.
        $availableIds = $this->availability->availableResourceIds($pool, $start, $end, lock: true);

        if ($requestedResourceIds) {
            $missing = array_diff($requestedResourceIds, $availableIds->all());
            if ($missing && ! $override) {
                throw new BookingConflictException(
                    "One or more selected \"{$pool->name}\" items are no longer available — availability changed. Please review and resubmit."
                );
            }
            $toAllocate = $requestedResourceIds;
        } else {
            if ($availableIds->count() < $item->quantity_requested && ! $override) {
                throw new BookingConflictException(
                    "Only {$availableIds->count()} of \"{$pool->name}\" remain available for this time — availability changed. Please review and resubmit."
                );
            }
            $toAllocate = $availableIds->take($item->quantity_requested)->all();
        }

        foreach ($toAllocate as $resourceId) {
            BookingResourceAllocation::create([
                'booking_item_id' => $item->id,
                'resource_id' => $resourceId,
                'allocated_at' => now(),
            ]);
        }
    }

    public function cancel(Booking $booking, User $actor, ?string $reason = null): Booking
    {
        DB::transaction(function () use ($booking, $actor, $reason) {
            $booking->update([
                'lifecycle_status' => 'cancelled',
                'cancelled_by_user_id' => $actor->id,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);

            BookingResourceAllocation::whereIn(
                'booking_item_id',
                $booking->items()->pluck('id')
            )->whereNull('released_at')->update(['released_at' => now()]);

            $this->auditLogger->log(
                'booking.cancelled',
                "{$actor->name} cancelled {$booking->reference}".($reason ? " ({$reason})" : ''),
                $actor,
                $booking->id,
            );
        });

        return $booking->fresh();
    }
}
