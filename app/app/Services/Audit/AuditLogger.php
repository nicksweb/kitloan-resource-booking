<?php

namespace App\Services\Audit;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Support\Facades\Request;

class AuditLogger
{
    public function log(
        string $eventType,
        string $description,
        ?User $actor = null,
        ?int $bookingId = null,
        ?int $resourceId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
    ): AuditEvent {
        return AuditEvent::create([
            'event_type' => $eventType,
            'actor_user_id' => $actor?->id,
            'booking_id' => $bookingId,
            'resource_id' => $resourceId,
            'ip_address' => Request::ip(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'description' => $description,
            'created_at' => now(),
        ]);
    }
}
