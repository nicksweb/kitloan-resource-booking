<?php

namespace App\Console\Commands;

use App\Services\Audit\AuditLogger;
use App\Services\Backup\BackupService;
use App\Settings\SettingsRepository;
use Illuminate\Console\Command;

/**
 * Writes an encrypted backup archive (database + uploads + config bundle) to
 * the backup directory and prunes it to the configured retention count. Run
 * nightly by the scheduler when Administration → Settings → Backups is on;
 * `--force` writes one regardless.
 */
class CreateBackup extends Command
{
    protected $signature = 'kitloan:backup {--force : Write an archive even when scheduled backups are disabled}';

    protected $description = 'Write an encrypted database + uploads backup archive';

    public function handle(BackupService $backups, SettingsRepository $settings, AuditLogger $audit): int
    {
        if (! $settings->get('scheduled_backups_enabled', false) && ! $this->option('force')) {
            $this->info('Scheduled backups are disabled — nothing to do. (Use --force to write one anyway.)');

            return self::SUCCESS;
        }

        if ($backups->passphrase() === null) {
            $this->error('No backup passphrase configured — set KITLOAN_BACKUP_PASSPHRASE or Administration → Settings → Backups.');

            return self::FAILURE;
        }

        try {
            $path = $backups->writeArchive();
        } catch (\Throwable $e) {
            $this->error('Backup failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $sizeKb = number_format(filesize($path) / 1024, 1);
        $audit->log('backup.created', 'Encrypted backup written: '.basename($path)." ({$sizeKb} KB)");
        $this->info("Backup written: {$path} ({$sizeKb} KB)");

        return self::SUCCESS;
    }
}
