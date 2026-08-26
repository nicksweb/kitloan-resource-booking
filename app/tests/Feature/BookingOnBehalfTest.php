<?php

namespace Tests\Feature;

use App\Livewire\Booking\BookingEdit;
use App\Livewire\Booking\BookingWizard;
use App\Models\Booking;
use App\Models\Resource;
use App\Models\ResourcePool;
use App\Models\User;
use App\Services\Booking\BookingService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BookingOnBehalfTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_an_it_operator_can_book_with_another_user_as_the_requestor(): void
    {
        $operator = User::factory()->create();
        $operator->assignRole('it_operator');
        $teacher = User::factory()->create(['name' => 'Ms Teacher']);
        $pool = ResourcePool::factory()->quantityTracked(10)->create(['requires_room' => false]);

        Livewire::actingAs($operator)
            ->test(BookingWizard::class, ['resourcePool' => $pool])
            ->assertSet('bookedByUserId', $operator->id)
            ->set('bookedByUserId', $teacher->id)
            ->set('quantityRequested', 2)
            ->call('submit')
            ->assertHasNoErrors();

        $booking = Booking::firstOrFail();
        $this->assertSame($teacher->id, $booking->booked_by_user_id);
        $this->assertSame($operator->id, $booking->created_by_user_id);
        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'booking.created',
        ]);
        $this->assertStringContainsString('on behalf of Ms Teacher',
            \App\Models\AuditEvent::where('event_type', 'booking.created')->value('description'));
    }

    public function test_a_plain_user_cannot_set_the_requestor_to_someone_else(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        $victim = User::factory()->create();
        $pool = ResourcePool::factory()->quantityTracked(10)->create(['requires_room' => false]);

        Livewire::actingAs($user)
            ->test(BookingWizard::class, ['resourcePool' => $pool])
            // Even if the field is forced client-side, the server ignores it.
            ->set('bookedByUserId', $victim->id)
            ->set('quantityRequested', 1)
            ->call('submit')
            ->assertHasNoErrors();

        $this->assertSame($user->id, Booking::firstOrFail()->booked_by_user_id);
    }

    public function test_an_it_operator_can_reassign_the_requestor_when_amending(): void
    {
        $operator = User::factory()->create();
        $operator->assignRole('it_operator');
        $owner = User::factory()->create();
        $newOwner = User::factory()->create(['name' => 'New Owner']);
        $pool = ResourcePool::factory()->create();
        $laptop = Resource::factory()->create(['resource_pool_id' => $pool->id]);

        $booking = app(BookingService::class)->create([
            'resource_pool_id' => $pool->id, 'location_id' => null, 'booking_type_id' => null,
            'start_at' => now()->addDays(2)->setTime(10, 0), 'end_at' => now()->addDays(2)->setTime(11, 0),
            'notes' => null, 'students' => [],
            'items' => [['resource_pool_id' => $pool->id, 'quantity' => 1, 'resource_ids' => [$laptop->id]]],
        ], $owner, $owner);

        Livewire::actingAs($operator)
            ->test(BookingEdit::class, ['booking' => $booking])
            ->set('bookedByUserId', $newOwner->id)
            ->call('submit')
            ->assertHasNoErrors();

        $this->assertSame($newOwner->id, $booking->fresh()->booked_by_user_id);
        $this->assertStringContainsString('reassigned to New Owner',
            \App\Models\AuditEvent::where('event_type', 'booking.updated')->latest('id')->value('description'));
    }
}
