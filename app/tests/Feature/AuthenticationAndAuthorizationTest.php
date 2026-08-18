<?php

namespace Tests\Feature;

use App\Models\ResourcePool;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationAndAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_unauthenticated_users_cannot_access_booking_data(): void
    {
        $this->get('/')->assertRedirect(route('auth.login'));
        $this->get('/my-bookings')->assertRedirect(route('auth.login'));
        $this->get('/bookings')->assertRedirect(route('auth.login'));
    }

    public function test_unauthenticated_users_cannot_hit_the_internal_api(): void
    {
        $this->getJson('/api/bookings')->assertUnauthorized();
    }

    public function test_normal_users_cannot_access_administration(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $this->actingAs($user)->get(route('admin.resource-pools.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.settings.index'))->assertForbidden();
        $this->actingAs($user)->get(route('it.dashboard'))->assertForbidden();
    }

    public function test_it_operator_can_reach_operations_pages_but_not_user_management(): void
    {
        $operator = User::factory()->create();
        $operator->assignRole('it_operator');

        $this->actingAs($operator)->get(route('it.dashboard'))->assertOk();
        $this->actingAs($operator)->get(route('admin.users.index'))->assertForbidden();
    }

    public function test_administrator_can_reach_every_admin_area(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrator');

        $this->actingAs($admin)->get(route('admin.resource-pools.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.users.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.settings.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.audit-log.index'))->assertOk();
        $this->actingAs($admin)->get(route('it.dashboard'))->assertOk();
    }

    public function test_disabled_users_are_logged_out_on_next_request(): void
    {
        $user = User::factory()->disabled()->create();
        $user->assignRole('user');

        $this->actingAs($user)->get('/')->assertRedirect(route('auth.login'));
        $this->assertGuest();
    }

    public function test_booking_owner_cannot_view_another_users_booking(): void
    {
        $pool = ResourcePool::factory()->create();
        \App\Models\Resource::factory()->count(2)->create(['resource_pool_id' => $pool->id]);

        $owner = User::factory()->create();
        $owner->assignRole('user');
        $stranger = User::factory()->create();
        $stranger->assignRole('user');

        $booking = app(\App\Services\Booking\BookingService::class)->create([
            'resource_pool_id' => $pool->id,
            'location_id' => null,
            'booking_type_id' => null,
            'start_at' => now()->addDay(),
            'end_at' => now()->addDay()->addHour(),
            'notes' => null,
            'students' => [],
            'items' => [['resource_pool_id' => $pool->id, 'quantity' => 1, 'resource_ids' => null]],
        ], $owner, $owner);

        // IDOR check: the stranger cannot view the owner's booking by guessing its reference/URL.
        $this->actingAs($stranger)->get(route('bookings.show', $booking))->assertForbidden();
        $this->actingAs($owner)->get(route('bookings.show', $booking))->assertOk();
    }
}
