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

    public function mount(): void
    {
        $user = auth()->user();
        $this->receivesDailySummary = (bool) $user->receives_daily_summary;
        $this->bookableAsOfficer = (bool) $user->bookable_as_officer;
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
        $user = auth()->user();
        $canBeOfficer = $user->hasAnyRole(['it_operator', 'administrator']);

        $attributes = ['receives_daily_summary' => $this->receivesDailySummary];

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

        session()->flash('success', 'Profile updated.');
    }
}
