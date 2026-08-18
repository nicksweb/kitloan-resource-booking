<?php

namespace App\Services\Booking;

use App\Models\Resource;
use App\Models\ResourcePool;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Conflict detection for resource bookings.
 *
 * Two bookings conflict when their *buffered* time ranges overlap, where a
 * booking's buffered range is [start - preparation_buffer, end + return_buffer]
 * for its resource pool. See docs/booking-engine.md for the derivation of the
 * threshold arithmetic used below — it is written this way (rather than raw
 * interval arithmetic in SQL) so it works identically on Postgres, MySQL and
 * SQLite.
 */
class AvailabilityService
{
    /**
     * Resource IDs in the pool that are bookable (status=available, not soft
     * deleted) and have no active allocation whose buffered range overlaps
     * the requested window.
     *
     * Pass $lock = true only inside a transaction immediately before creating
     * allocations — it takes row locks on the candidate resources so two
     * concurrent submissions can't both allocate the same resource.
     */
    public function availableResourceIds(
        ResourcePool $pool,
        CarbonInterface $start,
        CarbonInterface $end,
        ?int $excludeBookingId = null,
        bool $lock = false,
    ): Collection {
        $busyResourceIds = $this->busyResourceIds($pool, $start, $end, $excludeBookingId);

        $query = Resource::query()
            ->where('resource_pool_id', $pool->id)
            ->where('status', 'available')
            ->whereNotIn('id', $busyResourceIds);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->orderBy('display_order')->orderBy('name')->pluck('id');
    }

    public function isResourceAvailable(
        Resource $resource,
        ResourcePool $pool,
        CarbonInterface $start,
        CarbonInterface $end,
        ?int $excludeBookingId = null,
    ): bool {
        if ($resource->status !== 'available') {
            return false;
        }

        return ! $this->busyResourceIds($pool, $start, $end, $excludeBookingId)->contains($resource->id);
    }

    /**
     * Remaining bookable quantity for a quantity-tracked pool over the given
     * window. Pass $lock = true inside a transaction before committing a new
     * reservation against the pool total.
     */
    public function availableQuantity(
        ResourcePool $pool,
        CarbonInterface $start,
        CarbonInterface $end,
        ?int $excludeBookingId = null,
        bool $lock = false,
    ): int {
        if ($lock) {
            // Serialises concurrent quantity checks for this pool — there's no
            // single resource row to lock in quantity mode, so we lock the
            // pool row itself as a stand-in mutex.
            DB::table('resource_pools')->where('id', $pool->id)->lockForUpdate()->first();
        }

        $committed = $this->overlappingBookingItemsQuery($pool, $start, $end, $excludeBookingId)
            ->sum('booking_items.quantity_requested');

        return max(0, (int) $pool->quantity_total - (int) $committed);
    }

    private function busyResourceIds(
        ResourcePool $pool,
        CarbonInterface $start,
        CarbonInterface $end,
        ?int $excludeBookingId,
    ): Collection {
        [$existingStartMustBeBefore, $existingEndMustBeAfter] = $this->conflictThresholds($pool, $start, $end);

        return DB::table('booking_resource_allocations as bra')
            ->join('booking_items as bi', 'bi.id', '=', 'bra.booking_item_id')
            ->join('bookings as b', 'b.id', '=', 'bi.booking_id')
            ->whereNull('bra.released_at')
            ->when($excludeBookingId, fn ($q) => $q->where('b.id', '!=', $excludeBookingId))
            ->where('b.start_at', '<', $existingStartMustBeBefore)
            ->where('b.end_at', '>', $existingEndMustBeAfter)
            ->pluck('bra.resource_id');
    }

    private function overlappingBookingItemsQuery(
        ResourcePool $pool,
        CarbonInterface $start,
        CarbonInterface $end,
        ?int $excludeBookingId,
    ) {
        [$existingStartMustBeBefore, $existingEndMustBeAfter] = $this->conflictThresholds($pool, $start, $end);

        return DB::table('booking_items')
            ->join('bookings as b', 'b.id', '=', 'booking_items.booking_id')
            ->where('booking_items.resource_pool_id', $pool->id)
            ->where('b.lifecycle_status', 'active')
            ->when($excludeBookingId, fn ($q) => $q->where('b.id', '!=', $excludeBookingId))
            ->where('b.start_at', '<', $existingStartMustBeBefore)
            ->where('b.end_at', '>', $existingEndMustBeAfter);
    }

    /**
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    private function conflictThresholds(ResourcePool $pool, CarbonInterface $start, CarbonInterface $end): array
    {
        $prep = $pool->preparation_buffer_minutes;
        $return = $pool->return_buffer_minutes;

        $candidateBusyStart = $start->copy()->subMinutes($prep);
        $candidateBusyEnd = $end->copy()->addMinutes($return);

        return [
            $candidateBusyEnd->copy()->addMinutes($prep),   // existing.start must be before this
            $candidateBusyStart->copy()->subMinutes($return), // existing.end must be after this
        ];
    }
}
