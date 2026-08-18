<?php

namespace App\Livewire\Admin;

use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
class UsersIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $email = '';

    public string $role = 'user';

    public bool $enabled = true;

    public bool $receivesDailySummary = true;

    public function render()
    {
        $users = User::query()
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%")->orWhere('email', 'like', "%{$this->search}%"))
            ->with('roles')
            ->orderBy('name')
            ->paginate(20);

        return view('livewire.admin.users-index', ['users' => $users]);
    }

    public function create(): void
    {
        $this->reset(['editingId', 'name', 'email']);
        $this->role = 'user';
        $this->enabled = true;
        $this->receivesDailySummary = true;
        $this->showForm = true;
    }

    public function edit(User $user): void
    {
        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->role = $user->roles->first()?->name ?? 'user';
        $this->enabled = $user->enabled;
        $this->receivesDailySummary = $user->receives_daily_summary;
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$this->editingId],
            'role' => ['required', 'in:user,it_operator,administrator'],
        ]);

        $editingSelf = $this->editingId && (int) $this->editingId === auth()->id();

        if ($editingSelf && $data['role'] !== 'administrator') {
            $this->addError('role', "You can't remove your own administrator role. Have another administrator do this for you.");

            return;
        }

        if ($editingSelf && ! $this->enabled) {
            $this->addError('enabled', "You can't disable your own account.");

            return;
        }

        $attributes = [
            'name' => $data['name'],
            'email' => $data['email'],
            'enabled' => $this->enabled,
            'receives_daily_summary' => $this->receivesDailySummary,
        ];

        if ($this->editingId) {
            $user = User::findOrFail($this->editingId);
            $user->update($attributes);
        } else {
            $user = User::create($attributes);
        }

        $user->syncRoles([$data['role']]);

        $this->showForm = false;
        session()->flash('success', 'User saved.');
    }

    public function toggleEnabled(User $user): void
    {
        if ($user->id === auth()->id()) {
            session()->flash('error', "You can't disable your own account.");

            return;
        }

        $user->update(['enabled' => ! $user->enabled]);
    }
}
