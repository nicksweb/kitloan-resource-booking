<?php

namespace App\Livewire\Admin;

use App\Models\ResourcePool;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class ResourcePoolsIndex extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $description = '';

    public string $icon = 'laptop';

    public string $allocationMode = 'individual';

    public ?int $quantityTotal = null;

    public int $minimumLeadTimeMinutes = 0;

    // Defaults to 15 either side — enough time for IT to physically collect,
    // reset, and redeploy equipment between bookings (or run it to another
    // part of the school). Editable per pool; 0 still makes sense for
    // quantity-tracked pools with nothing physical to hand over.
    public int $preparationBufferMinutes = 15;

    public int $returnBufferMinutes = 15;

    public bool $allowWeekends = false;

    public bool $allowOutOfHours = false;

    public bool $requiresRoom = true;

    public bool $allowsStudent = true;

    public bool $requiresStudent = false;

    public bool $requiresBookingType = true;

    public bool $autoApprovalEnabled = true;

    public string $bookingReferencePrefix = 'BK';

    public bool $enabled = true;

    public function render()
    {
        return view('livewire.admin.resource-pools-index', [
            'pools' => ResourcePool::withCount('resources')->ordered()->get(),
        ]);
    }

    public function create(): void
    {
        $this->reset([
            'editingId', 'name', 'description', 'quantityTotal', 'minimumLeadTimeMinutes',
            'preparationBufferMinutes', 'returnBufferMinutes', 'allowWeekends', 'allowOutOfHours',
        ]);
        $this->icon = 'laptop';
        $this->allocationMode = 'individual';
        $this->requiresRoom = true;
        $this->allowsStudent = true;
        $this->requiresStudent = false;
        $this->requiresBookingType = true;
        $this->autoApprovalEnabled = true;
        $this->bookingReferencePrefix = 'BK';
        $this->enabled = true;
        $this->showForm = true;
    }

    public function edit(ResourcePool $pool): void
    {
        $this->editingId = $pool->id;
        $this->name = $pool->name;
        $this->description = (string) $pool->description;
        $this->icon = $pool->icon;
        $this->allocationMode = $pool->allocation_mode;
        $this->quantityTotal = $pool->quantity_total;
        $this->minimumLeadTimeMinutes = $pool->minimum_lead_time_minutes;
        $this->preparationBufferMinutes = $pool->preparation_buffer_minutes;
        $this->returnBufferMinutes = $pool->return_buffer_minutes;
        $this->allowWeekends = $pool->allow_weekends;
        $this->allowOutOfHours = $pool->allow_out_of_hours;
        $this->requiresRoom = $pool->requires_room;
        $this->allowsStudent = $pool->allows_student;
        $this->requiresStudent = $pool->requires_student;
        $this->requiresBookingType = $pool->requires_booking_type;
        $this->autoApprovalEnabled = $pool->auto_approval_enabled;
        $this->bookingReferencePrefix = $pool->booking_reference_prefix;
        $this->enabled = $pool->enabled;
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'icon' => ['required', 'string'],
            'allocationMode' => ['required', 'in:individual,quantity'],
            'quantityTotal' => ['nullable', 'integer', 'min:0', 'required_if:allocationMode,quantity'],
            'minimumLeadTimeMinutes' => ['required', 'integer', 'min:0'],
            'preparationBufferMinutes' => ['required', 'integer', 'min:0'],
            'returnBufferMinutes' => ['required', 'integer', 'min:0'],
            'bookingReferencePrefix' => ['required', 'string', 'max:8'],
        ]);

        $payload = [
            'name' => $data['name'],
            'description' => $data['description'],
            'icon' => $data['icon'],
            'allocation_mode' => $data['allocationMode'],
            'quantity_total' => $data['quantityTotal'],
            'minimum_lead_time_minutes' => $data['minimumLeadTimeMinutes'],
            'preparation_buffer_minutes' => $data['preparationBufferMinutes'],
            'return_buffer_minutes' => $data['returnBufferMinutes'],
            'allow_weekends' => $this->allowWeekends,
            'allow_out_of_hours' => $this->allowOutOfHours,
            'requires_room' => $this->requiresRoom,
            'allows_student' => $this->allowsStudent,
            'requires_student' => $this->requiresStudent,
            'requires_booking_type' => $this->requiresBookingType,
            'auto_approval_enabled' => $this->autoApprovalEnabled,
            'booking_reference_prefix' => $data['bookingReferencePrefix'],
            'enabled' => $this->enabled,
        ];

        if ($this->editingId) {
            ResourcePool::findOrFail($this->editingId)->update($payload);
        } else {
            $payload['slug'] = Str::slug($data['name']).'-'.Str::random(4);
            $payload['display_order'] = ResourcePool::max('display_order') + 1;
            ResourcePool::create($payload);
        }

        $this->showForm = false;
        session()->flash('success', 'Resource pool saved.');
    }

    public function toggleEnabled(ResourcePool $pool): void
    {
        $pool->update(['enabled' => ! $pool->enabled]);
    }
}
