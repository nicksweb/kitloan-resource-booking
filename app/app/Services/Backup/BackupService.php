<?php

namespace App\Services\Backup;

use App\Models\Setting;
use App\Services\Config\ConfigTransferService;
use App\Settings\SettingsRepository;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Full-instance backup: a logical dump of every application table (framework
 * runtime tables excluded), the uploads on the `public` disk, and the config
 * bundle — serialised as NDJSON, gzipped, then encrypted with AES-256-CBC in
 * OpenSSL's `Salted__` + PBKDF2 container so an archive can also be opened
 * with the `openssl` CLI alone (see README → Backups).
 *
 * The dump is DB-portable (works identically on Postgres and the SQLite test
 * database): tables are written and reloaded in foreign-key dependency order,
 * so no constraint-disabling or superuser privilege is needed.
 */
class BackupService
{
    public const FORMAT_VERSION = 1;

    public const EXTENSION = 'klbackup';

    /** Framework/runtime tables — state, not data; never dumped or restored. */
    private const SKIP_TABLES = [
        'migrations', 'cache', 'cache_locks', 'sessions',
        'jobs', 'job_batches', 'failed_jobs', 'password_reset_tokens',
    ];

    public function __construct(private readonly ConfigTransferService $config) {}

    // ---- passphrase ----------------------------------------------------

    /** The effective backup passphrase: env first, then the stored setting. */
    public function passphrase(): ?string
    {
        $env = (string) config('backup.passphrase');
        if ($env !== '') {
            return $env;
        }

        $stored = Setting::where('key', 'backup_passphrase')->value('value');
        if (! $stored) {
            return null;
        }

        try {
            return Crypt::decryptString($stored) ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function passphraseSource(): ?string
    {
        if ((string) config('backup.passphrase') !== '') {
            return 'environment';
        }

        return Setting::where('key', 'backup_passphrase')->value('value') ? 'settings' : null;
    }

    /** Store (or, with null/'', clear) the passphrase setting — encrypted at rest. */
    public function storePassphrase(?string $value): void
    {
        $value = $value === null ? '' : trim($value);

        if ($value === '') {
            Setting::where('key', 'backup_passphrase')->delete();
        } else {
            Setting::updateOrCreate(
                ['key' => 'backup_passphrase'],
                ['value' => Crypt::encryptString($value), 'type' => 'string'],
            );
        }

        app(SettingsRepository::class)->forgetCache();
    }

    // ---- create ------------------------------------------------------------

    /**
     * Build an encrypted archive and return its raw bytes.
     *
     * @throws RuntimeException when no passphrase is configured
     */
    public function createArchive(): string
    {
        $passphrase = $this->passphrase();
        if ($passphrase === null) {
            throw new RuntimeException('No backup passphrase is configured (set KITLOAN_BACKUP_PASSPHRASE or Administration → Settings → Backups).');
        }

        $ndjson = $this->buildNdjson();

        return $this->encrypt(gzencode($ndjson, 9), $passphrase);
    }

    /** Write a timestamped archive to the backup directory and prune to retention. */
    public function writeArchive(): string
    {
        $dir = (string) config('backup.path');
        File::ensureDirectoryExists($dir);

        $path = rtrim($dir, '/').'/kitloan-backup-'.now()->format('Y-m-d-His').'.'.self::EXTENSION;
        File::put($path, $this->createArchive());

        $this->pruneArchives((int) app(SettingsRepository::class)->get('backup_retention_count', 7));

        return $path;
    }

    /** @return list<array{name: string, path: string, size: int, modified: int}> */
    public function listArchives(): array
    {
        $dir = (string) config('backup.path');
        if (! is_dir($dir)) {
            return [];
        }

        return collect(File::files($dir))
            ->filter(fn ($f) => $f->getExtension() === self::EXTENSION)
            ->sortByDesc(fn ($f) => $f->getMTime())
            ->map(fn ($f) => [
                'name' => $f->getFilename(),
                'path' => $f->getPathname(),
                'size' => $f->getSize(),
                'modified' => $f->getMTime(),
            ])
            ->values()
            ->all();
    }

    public function pruneArchives(int $keep): int
    {
        $keep = max(1, $keep);
        $archives = $this->listArchives();
        $stale = array_slice($archives, $keep);

        foreach ($stale as $a) {
            File::delete($a['path']);
        }

        return count($stale);
    }

    // ---- restore ---------------------------------------------------------

    /**
     * Decrypt and apply an archive: wipes and reloads every dumped table, and
     * (optionally) the uploads. The config block is carried for cross-instance
     * partial import and is *not* applied here — the settings table is part of
     * the database dump.
     *
     * @return array{tables: int, rows: int, files: int, app_version: string}
     */
    public function restoreArchive(string $bytes, bool $withFiles = true): array
    {
        $passphrase = $this->passphrase();
        if ($passphrase === null) {
            throw new RuntimeException('No backup passphrase is configured — cannot decrypt the archive.');
        }

        $ndjson = gzdecode($this->decrypt($bytes, $passphrase));
        if ($ndjson === false) {
            throw new RuntimeException('Archive decrypted but is not valid gzip — wrong passphrase or corrupt file.');
        }

        [$meta, $tableRows, $files] = $this->parseNdjson($ndjson);

        $present = array_values(array_filter(array_keys($tableRows), fn ($t) => Schema::hasTable($t)));
        $ordered = $this->orderByDependencies($present);
        $rowCount = 0;

        DB::transaction(function () use ($ordered, $tableRows, &$rowCount) {
            foreach (array_reverse($ordered) as $table) {
                DB::table($table)->delete();
            }
            foreach ($ordered as $table) {
                foreach (array_chunk($tableRows[$table], 500) as $chunk) {
                    DB::table($table)->insert($chunk);
                    $rowCount += count($chunk);
                }
            }
        });

        $this->resyncSequences($ordered);

        $fileCount = 0;
        if ($withFiles) {
            foreach ($files as $f) {
                Storage::disk('public')->put($f['path'], base64_decode($f['b64']));
                $fileCount++;
            }
        }

        app(SettingsRepository::class)->forgetCache();

        return [
            'tables' => count($ordered),
            'rows' => $rowCount,
            'files' => $fileCount,
            'app_version' => (string) ($meta['app_version'] ?? 'unknown'),
        ];
    }

    /** Peek at an archive's metadata without applying it. */
    public function inspect(string $bytes): array
    {
        $passphrase = $this->passphrase();
        if ($passphrase === null) {
            throw new RuntimeException('No backup passphrase is configured — cannot decrypt the archive.');
        }

        $ndjson = gzdecode($this->decrypt($bytes, $passphrase));
        $firstLine = strtok((string) $ndjson, "\n");

        return json_decode((string) $firstLine, true) ?: [];
    }

    // ---- ndjson build / parse -----------------------------------------

    private function buildNdjson(): string
    {
        $tables = $this->orderByDependencies($this->dumpableTables());

        $out = fopen('php://temp/maxmemory:'.(16 * 1024 * 1024), 'r+');

        fwrite($out, json_encode([
            'kind' => 'meta',
            'format' => self::FORMAT_VERSION,
            'app_version' => (string) config('version.app'),
            'created_at' => now()->toIso8601String(),
            'driver' => DB::connection()->getDriverName(),
            'tables' => $tables,
        ]).PHP_EOL);

        foreach ($tables as $table) {
            fwrite($out, json_encode(['kind' => 'table', 'name' => $table]).PHP_EOL);

            $query = DB::table($table);
            if (Schema::hasColumn($table, 'id')) {
                $query->orderBy('id');
            } else {
                // lazy() needs a stable order; pivots have a composite key.
                foreach (Schema::getColumnListing($table) as $column) {
                    $query->orderBy($column);
                }
            }
            $query->lazy(1000)->each(function ($row) use ($out) {
                fwrite($out, json_encode(['kind' => 'row', 'data' => (array) $row]).PHP_EOL);
            });
        }

        foreach (Storage::disk('public')->allFiles() as $path) {
            fwrite($out, json_encode([
                'kind' => 'file',
                'path' => $path,
                'b64' => base64_encode(Storage::disk('public')->get($path)),
            ]).PHP_EOL);
        }

        fwrite($out, json_encode(['kind' => 'config', 'data' => $this->config->export()]).PHP_EOL);

        rewind($out);
        $ndjson = stream_get_contents($out);
        fclose($out);

        return $ndjson;
    }

    /**
     * @return array{0: array<string,mixed>, 1: array<string, list<array<string,mixed>>>, 2: list<array{path:string,b64:string}>}
     */
    private function parseNdjson(string $ndjson): array
    {
        $meta = [];
        $tableRows = [];
        $files = [];
        $current = null;

        foreach (explode("\n", $ndjson) as $line) {
            if ($line === '') {
                continue;
            }
            $rec = json_decode($line, true);
            if (! is_array($rec)) {
                continue;
            }

            switch ($rec['kind'] ?? null) {
                case 'meta':
                    $meta = $rec;
                    break;
                case 'table':
                    $current = $rec['name'];
                    $tableRows[$current] ??= [];
                    break;
                case 'row':
                    if ($current !== null) {
                        $tableRows[$current][] = $rec['data'];
                    }
                    break;
                case 'file':
                    $files[] = ['path' => $rec['path'], 'b64' => $rec['b64']];
                    break;
            }
        }

        if (($meta['format'] ?? null) !== self::FORMAT_VERSION) {
            throw new RuntimeException('Unsupported backup format — this instance reads v'.self::FORMAT_VERSION.'.');
        }

        return [$meta, $tableRows, $files];
    }

    // ---- schema helpers ----------------------------------------------

    /** @return list<string> */
    private function dumpableTables(): array
    {
        $names = array_map(fn ($t) => $t['name'], Schema::getTables());

        return array_values(array_diff($names, self::SKIP_TABLES));
    }

    /**
     * Kahn topological sort: a table's foreign-key parents come before it, so
     * restore can delete in reverse order and insert forward without tripping
     * any constraint.
     *
     * @param  list<string>  $tables
     * @return list<string>
     */
    private function orderByDependencies(array $tables): array
    {
        $set = array_flip($tables);
        $deps = [];
        foreach ($tables as $t) {
            $deps[$t] = collect(Schema::getForeignKeys($t))
                ->map(fn ($fk) => $fk['foreign_table'] ?? null)
                ->filter(fn ($ft) => $ft !== null && $ft !== $t && isset($set[$ft]))
                ->unique()->values()->all();
        }

        $ordered = [];
        $done = [];
        // Deterministic order; guard against a dependency cycle by falling back
        // to source order once no more progress is possible.
        while (count($ordered) < count($tables)) {
            $progress = false;
            foreach ($tables as $t) {
                if (isset($done[$t])) {
                    continue;
                }
                if (! array_diff($deps[$t], array_keys($done))) {
                    $ordered[] = $t;
                    $done[$t] = true;
                    $progress = true;
                }
            }
            if (! $progress) {
                foreach ($tables as $t) {
                    if (! isset($done[$t])) {
                        $ordered[] = $t;
                        $done[$t] = true;
                    }
                }
            }
        }

        return $ordered;
    }

    /** Postgres only: fast-forward each `id` sequence past the reloaded rows. */
    private function resyncSequences(array $tables): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($tables as $table) {
            if (! Schema::hasColumn($table, 'id')) {
                continue;
            }
            $safe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
            DB::statement(
                "SELECT setval(pg_get_serial_sequence(?, 'id'),
                        COALESCE((SELECT MAX(id) FROM \"{$safe}\"), 1),
                        (SELECT MAX(id) IS NOT NULL FROM \"{$safe}\"))",
                [$table],
            );
        }
    }

    // ---- cipher (OpenSSL `Salted__` + PBKDF2, CLI-compatible) --------

    private function encrypt(string $plain, string $passphrase): string
    {
        $salt = random_bytes(8);
        [$key, $iv] = $this->deriveKeyIv($passphrase, $salt);

        $ct = openssl_encrypt($plain, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        if ($ct === false) {
            throw new RuntimeException('Backup encryption failed.');
        }

        return 'Salted__'.$salt.$ct;
    }

    private function decrypt(string $blob, string $passphrase): string
    {
        if (strlen($blob) < 17 || ! str_starts_with($blob, 'Salted__')) {
            throw new RuntimeException('Not a Kitloan backup archive (bad header).');
        }

        [$key, $iv] = $this->deriveKeyIv($passphrase, substr($blob, 8, 8));

        $plain = openssl_decrypt(substr($blob, 16), 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        if ($plain === false) {
            throw new RuntimeException('Could not decrypt the archive — wrong passphrase or corrupt file.');
        }

        return $plain;
    }

    /** @return array{0: string, 1: string} [key(32), iv(16)] */
    private function deriveKeyIv(string $passphrase, string $salt): array
    {
        $material = hash_pbkdf2('sha256', $passphrase, $salt, (int) config('backup.pbkdf2_iterations', 100000), 48, true);

        return [substr($material, 0, 32), substr($material, 32, 16)];
    }
}
