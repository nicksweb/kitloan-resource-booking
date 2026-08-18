<?php

namespace App\Livewire\Admin;

use App\Models\BookingType;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class BookingTypesIndex extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $description = '';

    public string $instructionsForUser = '';

    public string $instructionsForIt = '';

    public bool $requiresApproval = false;

    public bool $enabled = true;

    public function render()
    {
        return view('livewire.admin.booking-types-index', ['types' => BookingType::ordered()->get()]);
    }

    public function create(): void
    {
        $this->reset(['editingId', 'name', 'description', 'instructionsForUser', 'instructionsForIt', 'requiresApproval']);
        $this->enabled = true;
        $this->showForm = true;
    }

    public function edit(BookingType $type): void
    {
        $this->editingId = $type->id;
        $this->name = $type->name;
        $this->description = (string) $type->description;
        $this->instructionsForUser = (string) $type->instructions_for_user;
        $this->instructionsForIt = (string) $type->instructions_for_it;
        $this->requiresApproval = $type->requires_approval;
        $this->enabled = $type->enabled;
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'instructionsForUser' => ['nullable', 'string'],
            'instructionsForIt' => ['nullable', 'string'],
        ]);

        $payload = [
            'name' => $data['name'],
            'description' => $data['description'],
            'instructions_for_user' => $data['instructionsForUser'],
            'instructions_for_it' => $data['instructionsForIt'],
            'requires_approval' => $this->requiresApproval,
            'enabled' => $this->enabled,
        ];

        if ($this->editingId) {
            BookingType::findOrFail($this->editingId)->update($payload);
        } else {
            $payload['display_order'] = BookingType::max('display_order') + 1;
            BookingType::create($payload);
        }

        $this->showForm = false;
        session()->flash('success', 'Exam type saved.');
    }

    public function toggleEnabled(BookingType $type): void
    {
        $type->update(['enabled' => ! $type->enabled]);
    }
}
