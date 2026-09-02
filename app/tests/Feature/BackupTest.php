<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Resource;
use App\Models\ResourcePool;
use App\Models\User;
use App\Services\Backup\BackupService;
use App\Services\Booking\BookingService;
use App\Settings\SettingsRepository;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackupTest extends TestCase
{
    use RefreshDatabase;

    private string $backupDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Storage::fake('public');

        $this->backupDir = sys_get_temp_dir().'/kl-backup-test-'.bin2hex(random_bytes(4));
        config([
            'backup.path' => $this->backupDir,
            'backup.passphrase' => 'correct horse battery staple',
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->backupDir);
        parent::tearDown();
    }

    private function seedBooking(): Booking
    {
        $pool = ResourcePool::factory()->create(['name' => 'Exam Laptops', 'minimum_lead_time_minutes' => 0]);
        $laptop = Resource::factory()->create(['resource_pool_id' => $pool->id, 'name' => 'Laptop 07']);
        $owner = User::factory()->create(['name' => 'Dana Owner']);

        $start = now()->addDays(3)->setTime(10, 0);
        while ($start->isWeekend()) {
            $start->addDay();
        }

        return app(BookingService::class)->create([
            'resource_pool_id' => $pool->id, 'location_id' => null, 'booking_type_id' => null,
            'start_at' => $start, 'end_at' => $start->copy()->addHour(),
            'notes' => 'backup me', 'students' => [],
            'items' => [['resource_pool_id' => $pool->id, 'quantity' => 1, 'resource_ids' => [$laptop->id]]],
        ], $owner, $owner);
    }

    public function test_creating_an_archive_needs_a_passphrase(): void
    {
        config(['backup.passphrase' => null]);

        $this->expectException(\RuntimeException::class);
        app(BackupService::class)->createArchive();
    }

    public function test_the_archive_uses_the_openssl_salted_container(): void
    {
        $this->seedBooking();

        $bytes = app(BackupService::class)->createArchive();

        $this->assertSame('Salted__', substr($bytes, 0, 8));
        // Opaque ciphertext — the plaintext table names must not be visible.
        $this->assertStringNotContainsString('resource_pools', $bytes);
    }

    public function test_a_full_round_trip_restores_database_and_files(): void
    {
        $booking = $this->seedBooking();
        app(SettingsRepository::class)->set('site_name', 'Before Restore');
        Storage::disk('public')->put('logos/brand.png', 'PNG-BYTES');

        $service = app(BackupService::class);
        $bytes = $service->createArchive();

        // Mutate everything the archive captured.
        $booking->delete();
        $booking->forceDelete();
        app(SettingsRepository::class)->set('site_name', 'After Mutation');
        Storage::disk('public')->delete('logos/brand.png');
        $this->assertDatabaseMissing('bookings', ['reference' => $booking->reference]);

        $result = $service->restoreArchive($bytes);

        $this->assertGreaterThan(0, $result['rows']);
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'reference' => $booking->reference, 'notes' => 'backup me']);
        $this->assertDatabaseHas('resources', ['name' => 'Laptop 07']);
        $this->assertSame('Before Restore', app(SettingsRepository::class)->get('site_name'));
        $this->assertSame('PNG-BYTES', Storage::disk('public')->get('logos/brand.png'));
    }

    public function test_restoring_with_the_wrong_passphrase_fails_cleanly(): void
    {
        $this->seedBooking();
        $bytes = app(BackupService::class)->createArchive();

        config(['backup.passphrase' => 'a different passphrase entirely']);

        $this->expectException(\RuntimeException::class);
        app(BackupService::class)->restoreArchive($bytes);
    }

    public function test_inspect_reads_metadata_without_applying_anything(): void
    {
        $this->seedBooking();
        $bytes = app(BackupService::class)->createArchive();

        $meta = app(BackupService::class)->inspect($bytes);

        $this->assertSame(BackupService::FORMAT_VERSION, $meta['format']);
        $this->assertSame((string) config('version.app'), $meta['app_version']);
        $this->assertArrayHasKey('created_at', $meta);
    }

    public function test_a_new_row_after_restore_gets_a_non_colliding_id(): void
    {
        // The logical reload keeps explicit ids; the next insert must not clash
        // with them (pg: sequence resync; sqlite: rowid counter tracks max).
        $this->seedBooking();
        $keptId = ResourcePool::where('name', 'Exam Laptops')->value('id');
        $bytes = app(BackupService::class)->createArchive();

        app(BackupService::class)->restoreArchive($bytes);

        $fresh = ResourcePool::factory()->create();
        $this->assertGreaterThan($keptId, $fresh->id);
    }

    public function test_the_command_is_a_no_op_until_scheduled_backups_are_enabled(): void
    {
        $this->seedBooking();

        $this->artisan('kitloan:backup')->assertSuccessful();
        $this->assertEmpty(app(BackupService::class)->listArchives());

        $this->artisan('kitloan:backup --force')->assertSuccessful();
        $this->assertCount(1, app(BackupService::class)->listArchives());
    }

    public function test_the_command_fails_without_a_passphrase(): void
    {
        config(['backup.passphrase' => null]);

        $this->artisan('kitloan:backup --force')->assertFailed();
    }

    public function test_retention_prunes_the_oldest_archives(): void
    {
        app(SettingsRepository::class)->set('scheduled_backups_enabled', true, 'boolean');
        app(SettingsRepository::class)->set('backup_retention_count', 2, 'integer');
        $this->seedBooking();

        File::ensureDirectoryExists($this->backupDir);
        foreach (['a', 'b', 'c'] as $i => $name) {
            $p = $this->backupDir."/kitloan-backup-old-{$name}.klbackup";
            File::put($p, 'stale');
            touch($p, now()->subDays(10 - $i)->timestamp);
        }

        $this->artisan('kitloan:backup')->assertSuccessful();

        $archives = app(BackupService::class)->listArchives();
        $this->assertCount(2, $archives);
        // The freshly written one is kept, the two oldest stale files are gone.
        $this->assertStringContainsString('kitloan-backup-20', $archives[0]['name']);
    }

    public function test_the_restore_command_round_trips_from_a_file(): void
    {
        $booking = $this->seedBooking();
        $path = app(BackupService::class)->writeArchive();

        $booking->forceDelete();
        $this->assertDatabaseMissing('bookings', ['id' => $booking->id]);

        $this->artisan("kitloan:restore {$path} --force")->assertSuccessful();

        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'notes' => 'backup me']);
    }

    public function test_the_restore_command_rejects_a_missing_file(): void
    {
        $this->artisan('kitloan:restore /no/such/archive.klbackup --force')->assertFailed();
    }
}
