<?php

namespace App\Livewire\Admin;

use App\Models\AuditEvent;
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

    public function render()
    {
        $events = AuditEvent::query()
            ->with(['actor', 'booking'])
            ->when($this->search, fn ($q) => $q->where('description', 'like', "%{$this->search}%")
                ->orWhereHas('booking', fn ($q) => $q->where('reference', 'like', "%{$this->search}%")))
            ->latest('created_at')
            ->paginate(30);

        return view('livewire.admin.audit-log-index', ['events' => $events]);
    }
}
