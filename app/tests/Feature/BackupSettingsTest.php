<?php

namespace Tests\Feature;

use App\Livewire\Admin\SettingsIndex;
use App\Models\Setting;
use App\Models\User;
use App\Services\Backup\BackupService;
use App\Settings\SettingsRepository;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BackupSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(SettingsSeeder::class);
        config(['backup.passphrase' => null]); // no env passphrase — exercise the settings path
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrator');

        return $admin;
    }

    public function test_scheduled_backups_cannot_be_enabled_without_a_passphrase(): void
    {
        Livewire::actingAs($this->admin())
            ->test(SettingsIndex::class)
            ->set('scheduledBackupsEnabled', true)
            ->call('save')
            ->assertHasErrors('scheduledBackupsEnabled');

        $this->assertFalse((bool) app(SettingsRepository::class)->get('scheduled_backups_enabled'));
    }

    public function test_the_passphrase_is_stored_encrypted_and_the_field_is_write_only(): void
    {
        Livewire::actingAs($this->admin())
            ->test(SettingsIndex::class)
            ->set('backupPassphrase', 'a-strong-secret')
            ->set('scheduledBackupsEnabled', true)
            ->set('backupRetentionCount', 5)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('backupPassphrase', ''); // cleared after save

        $this->assertSame('a-strong-secret', app(BackupService::class)->passphrase());
        $this->assertSame('settings', app(BackupService::class)->passphraseSource());
        // Not stored in the clear.
        $raw = Setting::where('key', 'backup_passphrase')->value('value');
        $this->assertNotSame('a-strong-secret', $raw);
        $this->assertTrue((bool) app(SettingsRepository::class)->get('scheduled_backups_enabled'));
        $this->assertSame(5, (int) app(SettingsRepository::class)->get('backup_retention_count'));
    }

    public function test_saving_a_blank_passphrase_keeps_the_existing_one(): void
    {
        app(BackupService::class)->storePassphrase('original-secret');

        Livewire::actingAs($this->admin())
            ->test(SettingsIndex::class)
            ->set('backupPassphrase', '')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('original-secret', app(BackupService::class)->passphrase());
    }

    public function test_download_backup_reports_a_missing_passphrase_instead_of_erroring(): void
    {
        Livewire::actingAs($this->admin())
            ->test(SettingsIndex::class)
            ->call('downloadBackup')
            ->assertHasErrors('backupPassphrase');
    }
}
