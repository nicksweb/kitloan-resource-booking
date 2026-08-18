<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['name', 'resource_pool_id', 'rule_type', 'threshold_value', 'enabled', 'display_order'])]
class ApprovalRule extends Model
{
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }

    public function resourcePool(): BelongsTo
    {
        return $this->belongsTo(ResourcePool::class);
    }

    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }
}
