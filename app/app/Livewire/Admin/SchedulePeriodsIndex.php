<?php

namespace App\Livewire\Admin;

use App\Models\SchedulePeriod;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class SchedulePeriodsIndex extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $groupName = '';

    public string $name = '';

    public string $startTime = '';

    public string $endTime = '';

    public bool $enabled = true;

    public function render()
    {
        return view('livewire.admin.schedule-periods-index', [
            'periods' => SchedulePeriod::ordered()->get()->groupBy('group_name'),
        ]);
    }

    public function create(?string $groupName = null): void
    {
        $this->reset(['editingId', 'name', 'startTime', 'endTime']);
        $this->groupName = $groupName ?? '';
        $this->enabled = true;
        $this->showForm = true;
    }

    public function edit(SchedulePeriod $period): void
    {
        $this->editingId = $period->id;
        $this->groupName = $period->group_name;
        $this->name = $period->name;
        $this->startTime = $period->start_time->format('H:i');
        $this->endTime = $period->end_time->format('H:i');
        $this->enabled = $period->enabled;
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'groupName' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'startTime' => ['required', 'date_format:H:i'],
            'endTime' => ['required', 'date_format:H:i', 'after:startTime'],
        ]);

        $payload = [
            'group_name' => $data['groupName'],
            'name' => $data['name'],
            'start_time' => $data['startTime'],
            'end_time' => $data['endTime'],
            'enabled' => $this->enabled,
        ];

        if ($this->editingId) {
            SchedulePeriod::findOrFail($this->editingId)->update($payload);
        } else {
            $payload['display_order'] = SchedulePeriod::where('group_name', $data['groupName'])->max('display_order') + 1;
            SchedulePeriod::create($payload);
        }

        $this->showForm = false;
        session()->flash('success', 'Period saved.');
    }

    public function toggleEnabled(SchedulePeriod $period): void
    {
        $period->update(['enabled' => ! $period->enabled]);
    }

    public function delete(SchedulePeriod $period): void
    {
        $period->delete();
        session()->flash('success', 'Period removed.');
    }
}
