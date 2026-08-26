<?php

namespace App\Console\Commands;

use App\Models\AuditEvent;
use App\Services\Audit\AuditLogger;
use App\Settings\SettingsRepository;
use Illuminate\Console\Command;

/**
 * Daily housekeeping: delete audit entries older than the configured
 * retention window (Administration -> Settings -> Housekeeping). A retention
 * of 0 keeps everything, and the command is a no-op.
 */
class PruneAuditLog extends Command
{
    protected $signature = 'audit:prune';

    protected $description = 'Delete audit-log entries older than the configured retention window';

    public function handle(SettingsRepository $settings, AuditLogger $auditLogger): int
    {
        $months = (int) $settings->get('audit_retention_months', 0);

        if ($months <= 0) {
            $this->info('Audit retention is disabled (0 months) — nothing to prune.');

            return self::SUCCESS;
        }

        $cutoff = now()->subMonthsNoOverflow($months);
        $deleted = AuditEvent::where('created_at', '<', $cutoff)
            ->where('event_type', '!=', 'audit.pruned')
            ->delete();

        if ($deleted > 0) {
            $auditLogger->log(
                'audit.pruned',
                "Retention sweep removed {$deleted} audit entr".($deleted === 1 ? 'y' : 'ies')." older than {$months} months",
            );
        }

        $this->info("Pruned {$deleted} audit entr".($deleted === 1 ? 'y' : 'ies').'.');

        return self::SUCCESS;
    }
}
