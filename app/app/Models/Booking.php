<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

#[Fillable([
    'reference', 'resource_pool_id', 'location_id', 'room_choice', 'booking_type_id',
    'booked_by_user_id', 'created_by_user_id', 'start_at', 'end_at', 'notes', 'helpdesk_url',
    'approval_status', 'allocation_status', 'lifecycle_status', 'auto_approved',
    'conflict_override', 'override_reason',
])]
class Booking extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'auto_approved' => 'boolean',
            'conflict_override' => 'boolean',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function resourcePool(): BelongsTo
    {
        return $this->belongsTo(ResourcePool::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function bookingType(): BelongsTo
    {
        return $this->belongsTo(BookingType::class);
    }

    public function bookedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'booked_by_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by_user_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    public function students(): HasMany
    {
        return $this->hasMany(BookingStudent::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BookingItem::class);
    }

    public function primaryItem(): ?BookingItem
    {
        return $this->items->firstWhere('resource_pool_id', $this->resource_pool_id);
    }

    public function isUpcoming(): bool
    {
        return $this->start_at->isFuture() && $this->lifecycle_status === 'active';
    }

    /**
     * The distinct people currently allocated to this booking — non-empty only
     * for a staff (IT officer) pool.
     *
     * @return Collection<int, User>
     */
    public function officers(): Collection
    {
        return $this->items
            ->flatMap->allocations
            ->whereNull('released_at')
            ->map(fn ($a) => $a->resource?->user)
            ->filter()
            ->unique('id')
            ->values();
    }

    public function hasOfficer(User $user): bool
    {
        return $this->officers()->contains('id', $user->id);
    }

    /** Human-readable room, honouring the pick-up / TBC choices. */
    public function roomLabel(): string
    {
        return match ($this->room_choice) {
            'pickup' => 'Pick-up from IT',
            'other' => 'Location TBC — see notes',
            default => $this->location?->name ?? '—',
        };
    }

    /** Short room label for tables / calendar titles. */
    public function roomCode(): string
    {
        return match ($this->room_choice) {
            'pickup' => 'Pick-up',
            'other' => 'TBC',
            default => $this->location?->code ?? '—',
        };
    }

    public function isCancellable(): bool
    {
        return $this->lifecycle_status === 'active' && $this->start_at->isFuture();
    }

    public function isEditableByOwner(): bool
    {
        return $this->lifecycle_status === 'active'
            && $this->approval_status !== 'rejected'
            && $this->start_at->isFuture();
    }

    public function scopeUpcoming($query)
    {
        return $query->where('lifecycle_status', 'active')->where('start_at', '>=', now());
    }

    public function scopePending($query)
    {
        return $query->where('approval_status', 'pending')->where('lifecycle_status', 'active');
    }
}
