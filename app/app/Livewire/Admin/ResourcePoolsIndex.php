<?php

namespace App\Livewire\Admin;

use App\Models\Booking;
use App\Models\ResourcePool;
use App\Services\Audit\AuditLogger;
use App\Services\Booking\StaffResourceSync;
use App\Services\Config\ConfigTransferService;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
class ResourcePoolsIndex extends Component
{
    use WithFileUploads;

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $description = '';

    public string $icon = 'laptop';

    public string $allocationMode = 'individual';

    /** equipment | staff */
    public string $kind = 'equipment';

    /** team | assigned_officer — only meaningful for a staff pool */
    public string $approvalRoute = 'team';

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

    public bool $showImport = false;

    public $importFile = null;

    /** @var array{created: int, updated: int, skipped: list<string>}|null */
    public ?array $importResults = null;

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
        $this->kind = 'equipment';
        $this->approvalRoute = 'team';
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
        $this->kind = $pool->kind ?: 'equipment';
        $this->approvalRoute = $pool->approval_route ?: 'team';
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

    public function save(StaffResourceSync $staffSync): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'icon' => ['required', 'string'],
            'kind' => ['required', 'in:equipment,staff'],
            'approvalRoute' => ['required', 'in:team,assigned_officer'],
            'allocationMode' => ['required', 'in:individual,quantity'],
            'quantityTotal' => ['nullable', 'integer', 'min:0', 'required_if:allocationMode,quantity'],
            'minimumLeadTimeMinutes' => ['required', 'integer', 'min:0'],
            'preparationBufferMinutes' => ['required', 'integer', 'min:0'],
            'returnBufferMinutes' => ['required', 'integer', 'min:0'],
            'bookingReferencePrefix' => ['required', 'string', 'max:8'],
        ]);

        // A staff pool is always individually allocated and never involves students.
        $isStaff = $data['kind'] === 'staff';

        $payload = [
            'name' => $data['name'],
            'description' => $data['description'],
            'icon' => $data['icon'],
            'kind' => $data['kind'],
            'approval_route' => $data['approvalRoute'],
            'allocation_mode' => $isStaff ? 'individual' : $data['allocationMode'],
            'quantity_total' => $isStaff ? null : $data['quantityTotal'],
            'minimum_lead_time_minutes' => $data['minimumLeadTimeMinutes'],
            'preparation_buffer_minutes' => $data['preparationBufferMinutes'],
            'return_buffer_minutes' => $data['returnBufferMinutes'],
            'allow_weekends' => $this->allowWeekends,
            'allow_out_of_hours' => $this->allowOutOfHours,
            'requires_room' => $this->requiresRoom,
            'allows_student' => $isStaff ? false : $this->allowsStudent,
            'requires_student' => $isStaff ? false : $this->requiresStudent,
            'requires_booking_type' => $this->requiresBookingType,
            'auto_approval_enabled' => $this->autoApprovalEnabled,
            'booking_reference_prefix' => $data['bookingReferencePrefix'],
            'enabled' => $this->enabled,
        ];

        if ($this->editingId) {
            $pool = ResourcePool::findOrFail($this->editingId);
            // kind is locked after creation.
            unset($payload['kind'], $payload['allocation_mode']);
            $pool->update($payload);
        } else {
            $payload['slug'] = Str::slug($data['name']).'-'.Str::random(4);
            $payload['display_order'] = ResourcePool::max('display_order') + 1;
            $pool = ResourcePool::create($payload);
        }

        if ($pool->isStaffPool()) {
            $staffSync->syncPool($pool->fresh());
        }

        $this->showForm = false;
        session()->flash('success', 'Resource pool saved.');
    }

    public function toggleEnabled(ResourcePool $pool): void
    {
        $pool->update(['enabled' => ! $pool->enabled]);
    }

    /**
     * Soft-delete a pool. Refused while it still has active bookings in the
     * future (as the primary pool or an additional item) — disable it instead,
     * which hides it from new bookings without disturbing existing ones. The
     * pool's resources are left in place; they reappear if the pool is
     * restored from the database.
     */
    public function delete(ResourcePool $pool, AuditLogger $auditLogger): void
    {
        $hasActiveBookings = Booking::query()
            ->where('lifecycle_status', 'active')
            ->where('end_at', '>=', now())
            ->where(fn ($q) => $q->where('resource_pool_id', $pool->id)
                ->orWhereHas('items', fn ($i) => $i->where('resource_pool_id', $pool->id)))
            ->exists();

        if ($hasActiveBookings) {
            session()->flash('error', "\"{$pool->name}\" still has upcoming bookings — disable it instead of deleting.");

            return;
        }

        $pool->delete();

        $auditLogger->log(
            'catalog.resource_pool_deleted',
            auth()->user()->name." deleted resource pool \"{$pool->name}\"",
            auth()->user(),
        );

        session()->flash('success', "Resource pool \"{$pool->name}\" deleted.");
    }

    public function export(ConfigTransferService $transfer)
    {
        $bundle = $transfer->export(['resource_pools']);
        $filename = 'kitloan-resource-pools-'.now()->format('Y-m-d').'.json';

        return response()->streamDownload(
            fn () => print (json_encode($bundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)),
            $filename,
            ['Content-Type' => 'application/json'],
        );
    }

    public function openImport(): void
    {
        $this->reset(['importFile', 'importResults']);
        $this->showImport = true;
    }

    /**
     * Imports the "resource_pools" section of a Kitloan config export (see
     * ConfigTransferService). Pools are matched by slug or name and upserted
     * along with their nested resources; nothing is deleted.
     */
    public function import(ConfigTransferService $transfer): void
    {
        $this->validate(['importFile' => ['required', 'file', 'mimes:json,txt', 'max:5120']]);

        $decoded = json_decode(file_get_contents($this->importFile->getRealPath()), true);
        if (! is_array($decoded)) {
            $this->addError('importFile', 'That file is not valid JSON.');

            return;
        }

        $result = $transfer->import($decoded, ['resource_pools']);
        if (! $result['ok']) {
            $this->addError('importFile', $result['error']);

            return;
        }

        $this->importResults = $result['sections']['resource_pools'] ?? ['created' => 0, 'updated' => 0, 'skipped' => []];
        $this->importFile = null;

        session()->flash('success', "Imported {$this->importResults['created']} new and updated {$this->importResults['updated']} existing resource pool(s).");
    }
}
