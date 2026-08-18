<?php

namespace Tests\Feature;

use App\Livewire\Admin\UsersIndex;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_an_administrator_cannot_remove_their_own_administrator_role(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrator');

        $this->actingAs($admin);
        Livewire::test(UsersIndex::class)
            ->call('edit', $admin->id)
            ->set('role', 'user')
            ->call('save')
            ->assertHasErrors('role');

        $this->assertTrue($admin->fresh()->hasRole('administrator'));
    }

    public function test_an_administrator_cannot_disable_their_own_account_via_the_edit_form(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrator');

        $this->actingAs($admin);
        Livewire::test(UsersIndex::class)
            ->call('edit', $admin->id)
            ->set('enabled', false)
            ->call('save')
            ->assertHasErrors('enabled');

        $this->assertTrue($admin->fresh()->enabled);
    }

    public function test_an_administrator_can_demote_a_different_administrator(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrator');
        $otherAdmin = User::factory()->create();
        $otherAdmin->assignRole('administrator');

        $this->actingAs($admin);
        Livewire::test(UsersIndex::class)
            ->call('edit', $otherAdmin->id)
            ->set('role', 'it_operator')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue($otherAdmin->fresh()->hasRole('it_operator'));
        $this->assertFalse($otherAdmin->fresh()->hasRole('administrator'));
    }
}
