<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'name', 'description', 'enabled', 'instructions_for_user',
    'instructions_for_it', 'requires_approval', 'display_order',
])]
class BookingType extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'requires_approval' => 'boolean',
        ];
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
