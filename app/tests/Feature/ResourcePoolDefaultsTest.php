<?php

namespace Tests\Feature;

use App\Livewire\Admin\ResourcePoolsIndex;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ResourcePoolDefaultsTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_pool_form_defaults_to_a_15_minute_buffer_either_side(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('administrator');

        Livewire::actingAs($admin)
            ->test(ResourcePoolsIndex::class)
            ->call('create')
            ->assertSet('preparationBufferMinutes', 15)
            ->assertSet('returnBufferMinutes', 15);
    }
}
