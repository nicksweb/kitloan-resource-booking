<?php

namespace App\Console\Commands;

use App\Services\Audit\AuditLogger;
use App\Services\Backup\BackupService;
use Illuminate\Console\Command;

/**
 * Restores the database (and, unless --skip-files, the uploads) from an
 * encrypted archive produced by `kitloan:backup` or the Settings → Backups
 * download. Destructive: every dumped table is wiped and reloaded. Restore
 * onto an instance running the same release the archive was taken on.
 */
class RestoreBackup extends Command
{
    protected $signature = 'kitloan:restore
        {file : Path to a .klbackup archive}
        {--force : Skip the confirmation prompt}
        {--skip-files : Do not restore uploaded files}';

    protected $description = 'Restore the database and uploads from an encrypted backup archive';

    public function handle(BackupService $backups, AuditLogger $audit): int
    {
        $file = (string) $this->argument('file');

        if (! is_file($file)) {
            $this->error("No such file: {$file}");

            return self::FAILURE;
        }

        if ($backups->passphrase() === null) {
            $this->error('No backup passphrase configured — set KITLOAN_BACKUP_PASSPHRASE or Administration → Settings → Backups.');

            return self::FAILURE;
        }

        $bytes = (string) file_get_contents($file);

        try {
            $meta = $backups->inspect($bytes);
        } catch (\Throwable $e) {
            $this->error('Cannot read archive: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->warn('This REPLACES the current database with the contents of the archive.');
        $this->line('  file:        '.basename($file));
        $this->line('  created:     '.($meta['created_at'] ?? '?'));
        $this->line('  app version: '.($meta['app_version'] ?? '?').'   (this instance: '.config('version.app').')');

        if (($meta['app_version'] ?? null) !== (string) config('version.app')) {
            $this->warn('  version mismatch — restore onto the matching release, or expect schema drift.');
        }

        if (! $this->option('force') && ! $this->confirm('Wipe and reload every table now?')) {
            $this->info('Aborted — nothing changed.');

            return self::SUCCESS;
        }

        try {
            $result = $backups->restoreArchive($bytes, ! $this->option('skip-files'));
        } catch (\Throwable $e) {
            $this->error('Restore failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->call('cache:clear');

        $audit->log(
            'backup.restored',
            'Database restored from '.basename($file)." ({$result['rows']} rows across {$result['tables']} tables, {$result['files']} file(s))",
        );

        $this->info("Restored {$result['rows']} rows across {$result['tables']} tables; {$result['files']} file(s). Caches cleared.");

        return self::SUCCESS;
    }
}
