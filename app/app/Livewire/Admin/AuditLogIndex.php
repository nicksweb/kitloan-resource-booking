<?php

namespace App\Livewire\Admin;

use App\Models\AuditEvent;
use App\Services\Audit\AuditLogger;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
class AuditLogIndex extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $type = '';

    public bool $showClear = false;

    /** Days-old threshold for the purge, or "all". */
    public string $clearRange = '365';

    public function render()
    {
        $events = AuditEvent::query()
            ->with(['actor', 'booking'])
            ->when($this->type, fn ($q) => $q->where('event_type', $this->type))
            ->when($this->search, fn ($q) => $q->where(fn ($q) => $q
                ->where('description', 'like', "%{$this->search}%")
                ->orWhereHas('booking', fn ($q) => $q->where('reference', 'like', "%{$this->search}%"))))
            ->latest('created_at')
            ->paginate(30);

        return view('livewire.admin.audit-log-index', [
            'events' => $events,
            'eventTypes' => AuditEvent::query()->distinct()->orderBy('event_type')->pluck('event_type'),
        ]);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingType(): void
    {
        $this->resetPage();
    }

    /**
     * Purge audit entries older than the chosen threshold (or all of them),
     * then record a single marker event so the purge itself is on the record.
     */
    public function clear(AuditLogger $auditLogger): void
    {
        $query = AuditEvent::query();
        $label = 'all entries';

        if ($this->clearRange !== 'all') {
            $days = (int) $this->clearRange;
            $cutoff = now()->subDays($days);
            $query->where('created_at', '<', $cutoff);
            $label = "entries older than {$days} days";
        }

        $deleted = $query->delete();

        $auditLogger->log(
            'audit.cleared',
            auth()->user()->name." cleared {$label} from the audit log ({$deleted} removed)",
            auth()->user(),
        );

        $this->showClear = false;
        $this->resetPage();
        session()->flash('success', "Removed {$deleted} audit entr".($deleted === 1 ? 'y' : 'ies').'.');
    }
}
