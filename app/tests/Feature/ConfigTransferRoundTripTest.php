<?php

namespace Tests\Feature;

use App\Models\ApprovalRule;
use App\Models\BookingType;
use App\Models\Location;
use App\Models\MessageTemplate;
use App\Models\Resource;
use App\Models\ResourcePool;
use App\Models\SchedulePeriod;
use App\Services\Config\ConfigTransferService;
use App\Settings\SettingsRepository;
use Database\Seeders\MessageTemplateSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * End-to-end check that the "export settings / full configuration / import"
 * feature works across every section — the ask that rode alongside the backup
 * work in 1.8.0.
 */
class ConfigTransferRoundTripTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(SettingsSeeder::class);
        $this->seed(MessageTemplateSeeder::class);
    }

    public function test_a_full_bundle_exports_and_re_imports_every_section(): void
    {
        $settings = app(SettingsRepository::class);
        $settings->set('site_name', 'Round Trip High');
        $settings->set('reference_prefix', 'RT');

        $pool = ResourcePool::factory()->create(['name' => 'RT Laptops', 'slug' => 'rt-laptops']);
        Resource::factory()->create(['resource_pool_id' => $pool->id, 'name' => 'RT-1', 'asset_number' => 'RT-0001']);
        Location::factory()->create(['code' => 'RT9', 'name' => 'RT Hall']);
        BookingType::factory()->create(['name' => 'RT Exam']);
        SchedulePeriod::factory()->create(['group_name' => 'RT', 'name' => 'RT P1', 'start_time' => '08:00:00', 'end_time' => '09:00:00']);
        ApprovalRule::create(['name' => 'RT big requests', 'resource_pool_id' => $pool->id, 'rule_type' => 'min_quantity', 'threshold_value' => 5, 'enabled' => true, 'display_order' => 0]);
        MessageTemplate::where('key', 'booking.policy_notice')->update(['intro' => 'RT notice', 'enabled' => true]);

        $transfer = app(ConfigTransferService::class);
        $bundle = $transfer->export();

        $this->assertSame(ConfigTransferService::SECTIONS, $bundle['kitloan']['sections']);

        // Wipe the catalog + drift the settings, then re-import.
        Resource::query()->forceDelete();
        ResourcePool::query()->forceDelete();
        Location::query()->forceDelete();
        BookingType::query()->forceDelete();
        SchedulePeriod::query()->forceDelete();
        ApprovalRule::query()->delete();
        $settings->set('site_name', 'Drifted');
        MessageTemplate::where('key', 'booking.policy_notice')->update(['intro' => 'drifted']);

        $result = $transfer->import($bundle);

        $this->assertTrue($result['ok']);
        $this->assertSame('Round Trip High', $settings->get('site_name'));
        $this->assertDatabaseHas('resource_pools', ['slug' => 'rt-laptops', 'name' => 'RT Laptops']);
        $this->assertDatabaseHas('resources', ['asset_number' => 'RT-0001']);
        $this->assertDatabaseHas('locations', ['code' => 'RT9']);
        $this->assertDatabaseHas('booking_types', ['name' => 'RT Exam']);
        $this->assertDatabaseHas('schedule_periods', ['name' => 'RT P1']);
        $this->assertDatabaseHas('approval_rules', ['name' => 'RT big requests', 'threshold_value' => 5]);
        $this->assertSame('RT notice', MessageTemplate::where('key', 'booking.policy_notice')->value('intro'));
    }

    public function test_re_importing_the_same_bundle_is_idempotent(): void
    {
        Location::factory()->create(['code' => 'IDEM', 'name' => 'Idempotent Room']);

        $transfer = app(ConfigTransferService::class);
        $bundle = $transfer->export();

        $first = $transfer->import($bundle);
        $second = $transfer->import($bundle);

        $this->assertTrue($second['ok']);
        $this->assertSame(1, Location::where('code', 'IDEM')->count());
        // Second pass creates nothing new.
        $this->assertSame(0, $second['sections']['locations']['created']);
    }

    public function test_an_older_export_missing_a_section_still_imports_the_rest(): void
    {
        Location::factory()->create(['code' => 'PART', 'name' => 'Partial Room']);

        $transfer = app(ConfigTransferService::class);
        $bundle = $transfer->export();
        unset($bundle['message_templates']); // simulate a bundle from before that section existed

        $result = $transfer->import($bundle);

        $this->assertTrue($result['ok']);
        $this->assertArrayNotHasKey('message_templates', $result['sections']);
        $this->assertDatabaseHas('locations', ['code' => 'PART']);
    }
}
