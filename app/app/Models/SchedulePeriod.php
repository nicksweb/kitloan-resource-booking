<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['group_name', 'name', 'start_time', 'end_time', 'enabled', 'display_order'])]
class SchedulePeriod extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            // TIME columns — the date component Carbon attaches is unused,
            // only ->format('H:i') is ever called on these.
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'enabled' => 'boolean',
        ];
    }

    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('group_name')->orderBy('display_order')->orderBy('start_time');
    }
}
