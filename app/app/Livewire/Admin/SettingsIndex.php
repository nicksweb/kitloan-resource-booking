<?php

namespace App\Livewire\Admin;

use App\Mail\TestEmail;
use App\Settings\SettingsRepository;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
class SettingsIndex extends Component
{
    use WithFileUploads;

    public string $testEmailAddress = '';

    public $logo = null;

    public ?string $currentLogoPath = null;

    public string $siteName;

    public string $timezone;

    public int $minAutoApprovalLeadHours;

    public string $schoolDayStart;

    public string $schoolDayFinish;

    public bool $allowWeekends;

    public bool $weekendRequiresApproval;

    public bool $outOfHoursRequiresApproval;

    public string $referencePrefix;

    public ?string $itNotificationAddress;

    public ?string $helpdeskReplyToAddress;

    public bool $localLoginEnabled;

    public function mount(SettingsRepository $settings): void
    {
        $this->siteName = (string) $settings->get('site_name');
        $this->timezone = (string) $settings->get('timezone', 'Australia/Brisbane');
        $this->minAutoApprovalLeadHours = (int) $settings->get('min_auto_approval_lead_hours', 6);
        $this->schoolDayStart = (string) $settings->get('school_day_start', '07:00');
        $this->schoolDayFinish = (string) $settings->get('school_day_finish', '17:00');
        $this->allowWeekends = (bool) $settings->get('allow_weekends', false);
        $this->weekendRequiresApproval = (bool) $settings->get('weekend_requires_approval', true);
        $this->outOfHoursRequiresApproval = (bool) $settings->get('out_of_hours_requires_approval', true);
        $this->referencePrefix = (string) $settings->get('reference_prefix', 'EX');
        $this->itNotificationAddress = $settings->get('it_notification_address');
        $this->helpdeskReplyToAddress = $settings->get('helpdesk_reply_to_address');
        $this->currentLogoPath = $settings->get('site_logo_path');
        $this->testEmailAddress = auth()->user()->email;
        $this->localLoginEnabled = (bool) $settings->get('local_login_enabled', true);
    }

    public function render()
    {
        return view('livewire.admin.settings-index');
    }

    /**
     * Sent synchronously (bypassing the queue) so a broken mail server
     * surfaces its real error here immediately, instead of silently landing
     * in failed_jobs minutes later.
     */
    public function sendTestEmail(): void
    {
        $this->validate(['testEmailAddress' => ['required', 'email']]);

        try {
            Mail::to($this->testEmailAddress)->send(new TestEmail(auth()->user()));
        } catch (\Throwable $e) {
            $this->addError('testEmailAddress', 'Send failed: '.$e->getMessage());

            return;
        }

        session()->flash('success', "Test email sent to {$this->testEmailAddress}.");
    }

    public function save(SettingsRepository $settings): void
    {
        $data = $this->validate([
            'siteName' => ['required', 'string', 'max:255'],
            'timezone' => ['required', 'timezone'],
            'minAutoApprovalLeadHours' => ['required', 'integer', 'min:0'],
            'schoolDayStart' => ['required', 'date_format:H:i'],
            'schoolDayFinish' => ['required', 'date_format:H:i', 'after:schoolDayStart'],
            'referencePrefix' => ['required', 'string', 'max:8'],
            'itNotificationAddress' => ['nullable', 'email'],
            'helpdeskReplyToAddress' => ['nullable', 'email'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,svg', 'max:2048'],
        ]);

        if ($this->logo) {
            // store() generates a random filename — never trust/use the
            // user-supplied original name for the path on disk.
            $path = $this->logo->store('logos', 'public');

            if ($this->currentLogoPath) {
                Storage::disk('public')->delete($this->currentLogoPath);
            }

            $settings->set('site_logo_path', $path);
            $this->currentLogoPath = $path;
            $this->logo = null;
        }

        $settings->set('site_name', $data['siteName']);
        $settings->set('timezone', $data['timezone']);
        $settings->set('min_auto_approval_lead_hours', $data['minAutoApprovalLeadHours'], 'integer');
        $settings->set('school_day_start', $data['schoolDayStart']);
        $settings->set('school_day_finish', $data['schoolDayFinish']);
        $settings->set('allow_weekends', $this->allowWeekends, 'boolean');
        $settings->set('weekend_requires_approval', $this->weekendRequiresApproval, 'boolean');
        $settings->set('out_of_hours_requires_approval', $this->outOfHoursRequiresApproval, 'boolean');
        $settings->set('reference_prefix', $data['referencePrefix']);
        $settings->set('it_notification_address', $data['itNotificationAddress'] ?? '');
        $settings->set('helpdesk_reply_to_address', $data['helpdeskReplyToAddress'] ?? '');
        $settings->set('local_login_enabled', $this->localLoginEnabled, 'boolean');

        session()->flash('success', 'Settings saved.');
    }
}
