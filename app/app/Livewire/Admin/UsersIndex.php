<?php

namespace App\Livewire\Admin;

use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
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

    public bool $showLocalPasswordForm = false;

    public ?int $localPasswordUserId = null;

    public string $newLocalPassword = '';

    public string $newLocalPasswordConfirmation = '';

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
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->editingId)],
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

    public function openLocalPasswordForm(User $user): void
    {
        if (! $user->hasRole('administrator')) {
            session()->flash('error', 'Local login is restricted to administrators — assign that role first.');

            return;
        }

        $this->localPasswordUserId = $user->id;
        $this->newLocalPassword = '';
        $this->newLocalPasswordConfirmation = '';
        $this->showLocalPasswordForm = true;
    }

    public function saveLocalPassword(AuditLogger $auditLogger): void
    {
        $data = $this->validate([
            'newLocalPassword' => ['required', 'string', 'min:12'],
            'newLocalPasswordConfirmation' => ['required', 'string'],
        ]);

        if ($data['newLocalPassword'] !== $data['newLocalPasswordConfirmation']) {
            $this->addError('newLocalPasswordConfirmation', 'Passwords do not match.');

            return;
        }

        $user = User::findOrFail($this->localPasswordUserId);

        if (! $user->hasRole('administrator')) {
            $this->addError('newLocalPassword', 'Local login is restricted to administrators.');

            return;
        }

        // forceFill(), not update() — 'password' is deliberately absent from
        // User's #[Fillable(...)] list, so update() would silently discard it.
        $user->forceFill(['password' => Hash::make($data['newLocalPassword'])])->save();

        $auditLogger->log(
            'auth.local_password_set',
            "Local emergency-login password set for {$user->email} via admin panel by ".auth()->user()->email,
            auth()->user(),
        );

        $this->showLocalPasswordForm = false;
        $this->newLocalPassword = '';
        $this->newLocalPasswordConfirmation = '';
        session()->flash('success', "Local login password updated for {$user->email}.");
    }

    public function clearLocalPassword(User $user, AuditLogger $auditLogger): void
    {
        $user->forceFill(['password' => null])->save();

        $auditLogger->log(
            'auth.local_password_cleared',
            "Local emergency-login password cleared for {$user->email} via admin panel by ".auth()->user()->email,
            auth()->user(),
        );

        session()->flash('success', "Local login password cleared for {$user->email}.");
    }
}
