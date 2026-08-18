<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'name', 'slug', 'description', 'enabled', 'icon', 'display_order',
    'allocation_mode', 'quantity_total',
    'minimum_lead_time_minutes', 'preparation_buffer_minutes', 'return_buffer_minutes',
    'allow_weekends', 'allow_out_of_hours',
    'requires_room', 'allows_student', 'requires_student', 'requires_booking_type',
    'auto_approval_enabled', 'booking_reference_prefix',
])]
class ResourcePool extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'allow_weekends' => 'boolean',
            'allow_out_of_hours' => 'boolean',
            'requires_room' => 'boolean',
            'allows_student' => 'boolean',
            'requires_student' => 'boolean',
            'requires_booking_type' => 'boolean',
            'auto_approval_enabled' => 'boolean',
        ];
    }

    public function resources(): HasMany
    {
        return $this->hasMany(Resource::class);
    }

    public function bookingItems(): HasMany
    {
        return $this->hasMany(BookingItem::class);
    }

    public function approvalRules(): HasMany
    {
        return $this->hasMany(ApprovalRule::class);
    }

    public function isIndividuallyTracked(): bool
    {
        return $this->allocation_mode === 'individual';
    }

    public function isQuantityTracked(): bool
    {
        return $this->allocation_mode === 'quantity';
    }

    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order')->orderBy('name');
    }
}
