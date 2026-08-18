<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'resource_id', 'external_source', 'external_id', 'asset_tag', 'serial',
    'name', 'model', 'status', 'location', 'missing_since', 'last_synced_at', 'external_metadata',
])]
class ExternalAssetLink extends Model
{
    protected function casts(): array
    {
        return [
            'external_metadata' => 'array',
            'last_synced_at' => 'datetime',
            'missing_since' => 'datetime',
        ];
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    public function snipeItAssetUrl(): ?string
    {
        $base = config('snipeit.url');

        if (! $base || $this->external_source !== 'snipeit') {
            return null;
        }

        return "{$base}/hardware/{$this->external_id}";
    }
}
