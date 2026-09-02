<?php

namespace Tests\Feature;

use App\Models\Resource;
use App\Models\ResourcePool;
use App\Models\User;
use App\Services\Booking\StaffResourceSync;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffResourceSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function staffPool(array $overrides = []): ResourcePool
    {
        return ResourcePool::factory()->create(array_merge(['kind' => 'staff'], $overrides));
    }

    public function test_opting_in_creates_an_available_officer_resource_in_every_staff_pool(): void
    {
        $poolA = $this->staffPool();
        $poolB = $this->staffPool();
        $equipment = ResourcePool::factory()->create(['kind' => 'equipment']);

        $user = User::factory()->create(['name' => 'Dana Officer', 'bookable_as_officer' => true]);
        app(StaffResourceSync::class)->syncUser($user);

        foreach ([$poolA, $poolB] as $pool) {
            $this->assertDatabaseHas('resources', [
                'resource_pool_id' => $pool->id,
                'user_id' => $user->id,
                'name' => 'Dana Officer',
                'status' => 'available',
            ]);
        }

        $this->assertDatabaseMissing('resources', [
            'resource_pool_id' => $equipment->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_opting_out_disables_the_resource_but_keeps_the_row(): void
    {
        $pool = $this->staffPool();
        $user = User::factory()->create(['bookable_as_officer' => true]);
        $sync = app(StaffResourceSync::class);

        $sync->syncUser($user);
        $resource = Resource::where('user_id', $user->id)->firstOrFail();

        $user->update(['bookable_as_officer' => false]);
        $sync->syncUser($user->fresh());

        $this->assertSame('disabled', $resource->fresh()->status);
        $this->assertDatabaseHas('resources', ['id' => $resource->id]);
    }

    public function test_re_opting_in_re_enables_the_existing_resource(): void
    {
        $pool = $this->staffPool();
        $user = User::factory()->create(['bookable_as_officer' => true]);
        $sync = app(StaffResourceSync::class);

        $sync->syncUser($user);
        $resource = Resource::where('user_id', $user->id)->firstOrFail();

        $user->update(['bookable_as_officer' => false]);
        $sync->syncUser($user->fresh());

        $user->update(['bookable_as_officer' => true]);
        $sync->syncUser($user->fresh());

        $this->assertSame('available', $resource->fresh()->status);
        $this->assertSame(1, Resource::where('user_id', $user->id)->count());
    }

    public function test_designating_a_new_staff_pool_backfills_from_opted_in_users(): void
    {
        $optedIn = User::factory()->create(['bookable_as_officer' => true]);
        $optedOut = User::factory()->create(['bookable_as_officer' => false]);
        $disabled = User::factory()->disabled()->create(['bookable_as_officer' => true]);

        $pool = $this->staffPool();
        app(StaffResourceSync::class)->syncPool($pool);

        $this->assertDatabaseHas('resources', ['resource_pool_id' => $pool->id, 'user_id' => $optedIn->id]);
        $this->assertDatabaseMissing('resources', ['resource_pool_id' => $pool->id, 'user_id' => $optedOut->id]);
        $this->assertDatabaseMissing('resources', ['resource_pool_id' => $pool->id, 'user_id' => $disabled->id]);
    }

    public function test_the_resource_name_tracks_the_user_name(): void
    {
        $pool = $this->staffPool();
        $user = User::factory()->create(['name' => 'Old Name', 'bookable_as_officer' => true]);
        $sync = app(StaffResourceSync::class);

        $sync->syncUser($user);
        $user->update(['name' => 'New Name']);
        $sync->syncUser($user->fresh());

        $this->assertDatabaseHas('resources', ['user_id' => $user->id, 'name' => 'New Name']);
    }

    public function test_a_disabled_user_is_never_bookable(): void
    {
        $pool = $this->staffPool();
        $user = User::factory()->disabled()->create(['bookable_as_officer' => true]);

        app(StaffResourceSync::class)->syncUser($user);

        $this->assertDatabaseMissing('resources', ['user_id' => $user->id, 'status' => 'available']);
    }
}
