<?php

namespace Tests\Feature;

use App\Livewire\Admin\BookingTypesIndex;
use App\Livewire\Admin\LocationsIndex;
use App\Livewire\Admin\ResourcePoolResources;
use App\Livewire\Admin\ResourcePoolsIndex;
use App\Models\BookingType;
use App\Models\Location;
use App\Models\Resource;
use App\Models\ResourcePool;
use App\Models\User;
use App\Services\Booking\BookingService;
use App\Services\Config\ConfigTransferService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminCatalogManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrator');

        return $admin;
    }

    public function test_a_resource_pool_can_be_soft_deleted(): void
    {
        $pool = ResourcePool::factory()->create(['name' => 'Old Cameras']);

        Livewire::actingAs($this->admin())
            ->test(ResourcePoolsIndex::class)
            ->call('delete', $pool->id);

        $this->assertSoftDeleted('resource_pools', ['id' => $pool->id]);
        $this->assertDatabaseHas('audit_events', ['event_type' => 'catalog.resource_pool_deleted']);
    }

    public function test_a_resource_pool_with_upcoming_bookings_cannot_be_deleted(): void
    {
        $pool = ResourcePool::factory()->quantityTracked(10)->create();
        $owner = User::factory()->create();
        app(BookingService::class)->create([
            'resource_pool_id' => $pool->id, 'location_id' => null, 'booking_type_id' => null,
            'start_at' => now()->addDays(2)->setTime(10, 0), 'end_at' => now()->addDays(2)->setTime(11, 0),
            'notes' => null, 'students' => [],
            'items' => [['resource_pool_id' => $pool->id, 'quantity' => 1, 'resource_ids' => null]],
        ], $owner, $owner);

        Livewire::actingAs($this->admin())
            ->test(ResourcePoolsIndex::class)
            ->call('delete', $pool->id);

        $this->assertNotSoftDeleted('resource_pools', ['id' => $pool->id]);
    }

    public function test_a_location_can_be_soft_deleted(): void
    {
        $location = Location::factory()->create(['code' => 'X9']);

        Livewire::actingAs($this->admin())
            ->test(LocationsIndex::class)
            ->call('delete', $location->id);

        $this->assertSoftDeleted('locations', ['id' => $location->id]);
    }

    public function test_an_individual_resource_can_be_soft_deleted(): void
    {
        $pool = ResourcePool::factory()->create();
        $laptop = Resource::factory()->create(['resource_pool_id' => $pool->id, 'name' => 'Laptop 99']);

        Livewire::actingAs($this->admin())
            ->test(ResourcePoolResources::class, ['resourcePool' => $pool])
            ->call('deleteResource', $laptop->id);

        $this->assertSoftDeleted('resources', ['id' => $laptop->id]);
        $this->assertDatabaseHas('audit_events', ['event_type' => 'catalog.resource_deleted']);
    }

    public function test_a_resource_allocated_to_an_upcoming_booking_cannot_be_deleted(): void
    {
        $pool = ResourcePool::factory()->create();
        $laptop = Resource::factory()->create(['resource_pool_id' => $pool->id]);
        $owner = User::factory()->create();
        app(BookingService::class)->create([
            'resource_pool_id' => $pool->id, 'location_id' => null, 'booking_type_id' => null,
            'start_at' => now()->addDays(2)->setTime(10, 0), 'end_at' => now()->addDays(2)->setTime(11, 0),
            'notes' => null, 'students' => [],
            'items' => [['resource_pool_id' => $pool->id, 'quantity' => 1, 'resource_ids' => [$laptop->id]]],
        ], $owner, $owner);

        Livewire::actingAs($this->admin())
            ->test(ResourcePoolResources::class, ['resourcePool' => $pool])
            ->call('deleteResource', $laptop->id);

        $this->assertNotSoftDeleted('resources', ['id' => $laptop->id]);
    }

    public function test_a_booking_type_can_be_soft_deleted(): void
    {
        $type = BookingType::factory()->create(['name' => 'Retired Exam']);

        Livewire::actingAs($this->admin())
            ->test(BookingTypesIndex::class)
            ->call('delete', $type->id);

        $this->assertSoftDeleted('booking_types', ['id' => $type->id]);
    }

    public function test_campus_can_be_bulk_renamed(): void
    {
        Location::factory()->count(3)->create(['campus' => 'Senior School']);
        Location::factory()->create(['campus' => 'Junior School']);

        Livewire::actingAs($this->admin())
            ->test(LocationsIndex::class)
            ->set('campusRenameFrom', 'Senior School')
            ->set('campusRenameTo', 'Senior Campus')
            ->call('renameCampus')
            ->assertHasNoErrors();

        $this->assertSame(3, Location::where('campus', 'Senior Campus')->count());
        $this->assertSame(0, Location::where('campus', 'Senior School')->count());
        $this->assertSame(1, Location::where('campus', 'Junior School')->count());
    }

    public function test_campus_rename_with_no_matches_reports_an_error(): void
    {
        Livewire::actingAs($this->admin())
            ->test(LocationsIndex::class)
            ->set('campusRenameFrom', 'Nowhere')
            ->set('campusRenameTo', 'Somewhere')
            ->call('renameCampus')
            ->assertHasErrors('campusRenameFrom');
    }

    public function test_catalog_list_rows_are_click_to_edit_without_swallowing_the_action_buttons(): void
    {
        ResourcePool::factory()->create(['name' => 'Exam Laptops']);
        Location::factory()->create(['code' => 'R1']);

        Livewire::actingAs($this->admin())
            ->test(ResourcePoolsIndex::class)
            ->assertSeeHtml('class="cursor-pointer transition-colors hover:bg-indigo-50/60"')
            ->assertSeeHtml('wire:click.stop="delete(');

        Livewire::actingAs($this->admin())
            ->test(LocationsIndex::class)
            ->assertSeeHtml('class="cursor-pointer transition-colors hover:bg-indigo-50/60"')
            ->assertSeeHtml('wire:click.stop="toggleEnabled(');
    }

    public function test_resource_pool_json_export_round_trips_through_import(): void
    {
        $pool = ResourcePool::factory()->create(['name' => 'Exam Laptops', 'slug' => 'exam-laptops']);
        Resource::factory()->create(['resource_pool_id' => $pool->id, 'name' => 'Laptop 01', 'asset_number' => 'KL-1']);

        $transfer = app(ConfigTransferService::class);
        $bundle = $transfer->export(['resource_pools']);

        // Wipe and re-import.
        Resource::query()->forceDelete();
        ResourcePool::query()->forceDelete();
        $this->assertSame(0, ResourcePool::count());

        $result = $transfer->import($bundle, ['resource_pools']);

        $this->assertTrue($result['ok']);
        $this->assertSame(1, ResourcePool::where('slug', 'exam-laptops')->count());
        $this->assertDatabaseHas('resources', ['name' => 'Laptop 01', 'asset_number' => 'KL-1']);
    }

    public function test_the_shipped_example_resource_pools_file_imports_cleanly(): void
    {
        $json = json_decode(file_get_contents(resource_path('examples/resource-pools.json')), true);

        $result = app(ConfigTransferService::class)->import($json, ['resource_pools']);

        $this->assertTrue($result['ok']);
        $this->assertDatabaseHas('resource_pools', ['slug' => 'exam-laptops', 'allocation_mode' => 'individual']);
        $this->assertDatabaseHas('resource_pools', ['slug' => 'usb-c-chargers', 'allocation_mode' => 'quantity', 'quantity_total' => 40]);
        $this->assertDatabaseHas('resources', ['asset_number' => 'KL-0003', 'status' => 'maintenance']);
    }
}
