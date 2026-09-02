<?php

namespace App\Livewire;

use App\Services\Audit\AuditLogger;
use App\Services\Booking\StaffResourceSync;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Profile extends Component
{
    public bool $receivesDailySummary = true;

    public bool $bookableAsOfficer = false;

    /** light | dark | system */
    public string $theme = 'system';

    public function mount(): void
    {
        $user = auth()->user();
        $this->receivesDailySummary = (bool) $user->receives_daily_summary;
        $this->bookableAsOfficer = (bool) $user->bookable_as_officer;
        $this->theme = $user->theme ?: 'system';
    }

    /** Live preview while the radio is being changed; persisted only on save(). */
    public function updatedTheme(string $value): void
    {
        if (in_array($value, ['light', 'dark', 'system'], true)) {
            $this->dispatch('theme-changed', theme: $value);
        }
    }

    #[Title('My Profile')]
    public function render()
    {
        return view('livewire.profile', [
            'canBeOfficer' => auth()->user()->hasAnyRole(['it_operator', 'administrator']),
        ]);
    }

    public function save(StaffResourceSync $sync, AuditLogger $auditLogger): void
    {
        $this->validate(['theme' => ['required', 'in:light,dark,system']]);

        $user = auth()->user();
        $canBeOfficer = $user->hasAnyRole(['it_operator', 'administrator']);

        $attributes = [
            'receives_daily_summary' => $this->receivesDailySummary,
            'theme' => $this->theme,
        ];

        if ($canBeOfficer) {
            $wasBookable = (bool) $user->bookable_as_officer;
            $attributes['bookable_as_officer'] = $this->bookableAsOfficer;
        }

        $user->update($attributes);

        if ($canBeOfficer && ($wasBookable ?? null) !== $this->bookableAsOfficer) {
            $sync->syncUser($user->fresh());
            $auditLogger->log(
                'profile.officer_availability',
                "{$user->name} ".($this->bookableAsOfficer ? 'is now' : 'is no longer').' bookable as an IT officer',
                $user,
            );
        }

        // Let the layout script re-resolve .dark on <html> without a reload,
        // and cache the choice for the (unauthenticated) sign-in screen.
        $this->dispatch('theme-changed', theme: $this->theme);

        session()->flash('success', 'Profile updated.');
    }
}
