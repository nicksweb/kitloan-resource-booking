<?php

namespace Tests\Feature;

use App\Models\ResourcePool;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImpersonationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_an_administrator_can_impersonate_a_regular_user(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrator');
        $teacher = User::factory()->create();
        $teacher->assignRole('user');

        $this->actingAs($admin)
            ->post(route('admin.users.impersonate', $teacher))
            ->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($teacher);
    }

    public function test_administrators_cannot_be_impersonated(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrator');
        $otherAdmin = User::factory()->create();
        $otherAdmin->assignRole('administrator');

        $this->actingAs($admin)
            ->post(route('admin.users.impersonate', $otherAdmin))
            ->assertForbidden();

        $this->assertAuthenticatedAs($admin);
    }

    public function test_a_non_administrator_cannot_start_impersonation(): void
    {
        $operator = User::factory()->create();
        $operator->assignRole('it_operator');
        $teacher = User::factory()->create();
        $teacher->assignRole('user');

        $this->actingAs($operator)
            ->post(route('admin.users.impersonate', $teacher))
            ->assertForbidden();
    }

    public function test_stopping_impersonation_restores_the_original_admin_session(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrator');
        $teacher = User::factory()->create();
        $teacher->assignRole('user');

        $this->actingAs($admin)->post(route('admin.users.impersonate', $teacher));
        $this->assertAuthenticatedAs($teacher);

        $this->post(route('impersonation.stop'))->assertRedirect(route('admin.users.index'));
        $this->assertAuthenticatedAs($admin);
    }

    public function test_a_booking_created_while_impersonating_records_the_real_admin_as_creator(): void
    {
        $pool = ResourcePool::factory()->quantityTracked(5)->create(['minimum_lead_time_minutes' => 0]);
        $admin = User::factory()->create();
        $admin->assignRole('administrator');
        $teacher = User::factory()->create();
        $teacher->assignRole('user');

        $this->actingAs($admin)->post(route('admin.users.impersonate', $teacher));

        $booking = app(\App\Services\Booking\BookingService::class)->create([
            'resource_pool_id' => $pool->id, 'location_id' => null, 'booking_type_id' => null,
            'start_at' => now()->addHour(), 'end_at' => now()->addHours(2),
            'notes' => null, 'students' => [],
            'items' => [['resource_pool_id' => $pool->id, 'quantity' => 1, 'resource_ids' => null]],
        ], auth()->user(), app(\App\Services\Auth\ImpersonationManager::class)->actor());

        $this->assertSame($teacher->id, $booking->booked_by_user_id);
        $this->assertSame($admin->id, $booking->created_by_user_id);
    }
}
