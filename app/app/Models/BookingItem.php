<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['booking_id', 'resource_pool_id', 'quantity_requested'])]
class BookingItem extends Model
{
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function resourcePool(): BelongsTo
    {
        return $this->belongsTo(ResourcePool::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(BookingResourceAllocation::class);
    }

    public function activeAllocations(): HasMany
    {
        return $this->allocations()->whereNull('released_at');
    }

    public function isFullyAllocated(): bool
    {
        if ($this->resourcePool->isQuantityTracked()) {
            return true;
        }

        return $this->activeAllocations()->count() >= $this->quantity_requested;
    }
}
