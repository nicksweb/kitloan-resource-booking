<?php

namespace App\Livewire\Admin;

use App\Mail\TestEmail;
use App\Services\Config\ConfigTransferService;
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

    public bool $embeddingEnabled;

    public string $embeddingAllowedOrigins;

    public int $auditRetentionMonths;

    public string $codeVersion = '';

    public ?string $installedVersion = null;

    public bool $showConfigImport = false;

    public $configImportFile = null;

    /** @var array<int, string> */
    public array $configImportSections = ['settings'];

    /** @var array{ok: bool, error?: string, sections: array<string, mixed>}|null */
    public ?array $configImportResults = null;

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
        $this->embeddingEnabled = (bool) $settings->get('embedding_enabled', false);
        $this->embeddingAllowedOrigins = (string) $settings->get('embedding_allowed_origins', '');
        $this->auditRetentionMonths = (int) $settings->get('audit_retention_months', 0);
        $this->codeVersion = (string) config('version.app');
        $this->installedVersion = $settings->get(config('version.stored_version_key'));
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
            'auditRetentionMonths' => ['required', 'integer', 'min:0', 'max:120'],
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
        $settings->set('embedding_enabled', $this->embeddingEnabled, 'boolean');
        $settings->set('embedding_allowed_origins', trim($this->normaliseOrigins($this->embeddingAllowedOrigins)));
        $settings->set('audit_retention_months', $data['auditRetentionMonths'], 'integer');

        session()->flash('success', 'Settings saved.');
    }

    public function removeLogo(SettingsRepository $settings): void
    {
        if ($this->currentLogoPath) {
            Storage::disk('public')->delete($this->currentLogoPath);
        }

        $settings->set('site_logo_path', '');
        $this->currentLogoPath = null;
        $this->logo = null;

        session()->flash('success', 'Logo removed — the default mark is back.');
    }

    /** One origin per line, blanks and obvious junk dropped. */
    private function normaliseOrigins(string $raw): string
    {
        return collect(preg_split('/[\s,]+/', $raw))
            ->map(fn ($o) => trim((string) $o))
            ->filter(fn ($o) => $o !== '' && preg_match('#^https?://[^/\s]+$#', $o))
            ->unique()
            ->implode("\n");
    }

    // ---- configuration export / import --------------------------------

    public function exportSettings(ConfigTransferService $transfer)
    {
        return $this->downloadBundle($transfer->export(['settings']), 'kitloan-settings');
    }

    public function exportFullConfig(ConfigTransferService $transfer)
    {
        return $this->downloadBundle($transfer->export(), 'kitloan-config');
    }

    private function downloadBundle(array $bundle, string $prefix)
    {
        return response()->streamDownload(
            fn () => print (json_encode($bundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)),
            $prefix.'-'.now()->format('Y-m-d').'.json',
            ['Content-Type' => 'application/json'],
        );
    }

    public function openConfigImport(): void
    {
        $this->reset(['configImportFile', 'configImportResults']);
        $this->configImportSections = ['settings'];
        $this->showConfigImport = true;
    }

    public function importConfig(ConfigTransferService $transfer): void
    {
        $this->validate([
            'configImportFile' => ['required', 'file', 'mimes:json,txt', 'max:10240'],
            'configImportSections' => ['required', 'array', 'min:1'],
            'configImportSections.*' => ['in:'.implode(',', ConfigTransferService::SECTIONS)],
        ]);

        $decoded = json_decode(file_get_contents($this->configImportFile->getRealPath()), true);
        if (! is_array($decoded)) {
            $this->addError('configImportFile', 'That file is not valid JSON.');

            return;
        }

        $result = $transfer->import($decoded, $this->configImportSections);
        $this->configImportResults = $result;

        if (! $result['ok']) {
            $this->addError('configImportFile', $result['error'] ?? 'Import failed.');

            return;
        }

        // Reload the on-screen values from what was just imported.
        $this->mount(app(SettingsRepository::class));
        $this->configImportFile = null;
        session()->flash('success', 'Configuration imported.');
    }
}
