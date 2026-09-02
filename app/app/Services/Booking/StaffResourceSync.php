<?php

namespace App\Services\Booking;

use App\Models\Resource;
use App\Models\ResourcePool;
use App\Models\User;

/**
 * Keeps the auto-managed "officer" resource rows on staff pools in step with
 * who has opted in (`users.bookable_as_officer`).
 *
 * An opted-in user has one `available` Resource per staff pool; opting out (or
 * being disabled) flips those rows to `disabled` rather than deleting them, so
 * a past booking's allocation history survives. `AvailabilityService` already
 * only considers `status = 'available'`, so a disabled officer simply drops out
 * of the pick list.
 */
class StaffResourceSync
{
    /** Reconcile every staff pool for one user. */
    public function syncUser(User $user): void
    {
        $shouldBeBookable = $user->bookable_as_officer && $user->enabled;

        foreach (ResourcePool::where('kind', 'staff')->get() as $pool) {
            $this->reconcile($pool, $user, $shouldBeBookable);
        }
    }

    /** Reconcile one staff pool against every opted-in user (e.g. a pool just became staff). */
    public function syncPool(ResourcePool $pool): void
    {
        if (! $pool->isStaffPool()) {
            return;
        }

        foreach (User::where('bookable_as_officer', true)->where('enabled', true)->get() as $user) {
            $this->reconcile($pool, $user, true);
        }
    }

    private function reconcile(ResourcePool $pool, User $user, bool $shouldBeBookable): void
    {
        $resource = Resource::withTrashed()
            ->where('resource_pool_id', $pool->id)
            ->where('user_id', $user->id)
            ->first();

        if (! $shouldBeBookable) {
            if ($resource && ! $resource->trashed() && $resource->status === 'available') {
                $resource->update(['status' => 'disabled']);
            }

            return;
        }

        if (! $resource) {
            $pool->resources()->create([
                'user_id' => $user->id,
                'name' => $user->name,
                'status' => 'available',
                'source' => 'manual',
                'display_order' => (int) $pool->resources()->max('display_order') + 1,
            ]);

            return;
        }

        if ($resource->trashed()) {
            $resource->restore();
        }

        $updates = [];
        if ($resource->status === 'disabled') {
            $updates['status'] = 'available';
        }
        if ($resource->name !== $user->name) {
            $updates['name'] = $user->name;
        }
        if ($updates) {
            $resource->update($updates);
        }
    }
}
