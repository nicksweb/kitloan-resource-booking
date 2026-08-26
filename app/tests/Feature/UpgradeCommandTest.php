<?php

namespace Tests\Feature;

use App\Services\Config\ConfigTransferService;
use App\Settings\SettingsRepository;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpgradeCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_upgrade_records_the_installed_version_and_is_idempotent(): void
    {
        $settings = app(SettingsRepository::class);
        $key = config('version.stored_version_key');

        $this->artisan('kitloan:upgrade')->assertSuccessful();
        $settings->forgetCache();
        $this->assertSame(config('version.app'), $settings->get($key));

        // Re-running changes nothing and still succeeds.
        $this->artisan('kitloan:upgrade')->assertSuccessful();
    }

    public function test_upgrade_refuses_an_instance_that_is_too_old_to_jump_directly(): void
    {
        app(SettingsRepository::class)->set(config('version.stored_version_key'), '0.9.0');

        $this->artisan('kitloan:upgrade')->assertFailed();
    }

    public function test_settings_round_trip_through_config_export_and_import(): void
    {
        $settings = app(SettingsRepository::class);
        $settings->set('site_name', 'Original Name');
        $settings->set('min_auto_approval_lead_hours', 6, 'integer');

        $transfer = app(ConfigTransferService::class);
        $bundle = $transfer->export(['settings']);

        $settings->set('site_name', 'Changed Later');
        $settings->forgetCache();

        $result = $transfer->import($bundle, ['settings']);

        $this->assertTrue($result['ok']);
        $settings->forgetCache();
        $this->assertSame('Original Name', $settings->get('site_name'));
    }

    public function test_config_import_rejects_a_foreign_file(): void
    {
        $result = app(ConfigTransferService::class)->import(['not' => 'a kitloan export'], ['settings']);

        $this->assertFalse($result['ok']);
    }

    public function test_config_import_ignores_unknown_setting_keys(): void
    {
        $bundle = [
            'kitloan' => ['format_version' => 1, 'app_version' => '1.2.0'],
            'settings' => [
                'site_name' => ['value' => 'From Import', 'type' => 'string'],
                'totally_made_up_key' => ['value' => 'evil', 'type' => 'string'],
            ],
        ];

        $result = app(ConfigTransferService::class)->import($bundle, ['settings']);

        $this->assertTrue($result['ok']);
        $this->assertDatabaseMissing('settings', ['key' => 'totally_made_up_key']);
        $this->assertNotEmpty($result['sections']['settings']['skipped']);
    }
}
