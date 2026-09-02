<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'resource_pool_id', 'user_id', 'name', 'asset_number', 'serial',
    'status', 'source', 'display_order', 'notes',
])]
class Resource extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUSES = ['available', 'unavailable', 'maintenance', 'missing', 'retired', 'disabled'];

    public function resourcePool(): BelongsTo
    {
        return $this->belongsTo(ResourcePool::class);
    }

    /** The person this resource stands for, on a staff pool. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function externalAssetLink(): HasOne
    {
        return $this->hasOne(ExternalAssetLink::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(BookingResourceAllocation::class);
    }

    public function isBookable(): bool
    {
        return $this->status === 'available';
    }

    public function scopeBookable($query)
    {
        return $query->where('status', 'available');
    }
}
