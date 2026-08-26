<?php

namespace App\Livewire\Admin;

use App\Models\Booking;
use App\Models\ResourcePool;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
class ReportsIndex extends Component
{
    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    #[Url]
    public ?int $poolId = null;

    #[Url]
    public bool $withCancelled = false;

    public function mount(): void
    {
        $this->from = $this->from ?: now()->subDays(90)->toDateString();
        $this->to = $this->to ?: now()->toDateString();
    }

    public function render()
    {
        [$start, $end] = $this->range();

        $bookings = Booking::query()
            ->whereBetween('start_at', [$start, $end])
            ->when(! $this->withCancelled, fn ($q) => $q->where('lifecycle_status', '!=', 'cancelled'))
            ->when($this->poolId, fn ($q) => $q->where(fn ($q) => $q
                ->where('resource_pool_id', $this->poolId)
                ->orWhereHas('items', fn ($i) => $i->where('resource_pool_id', $this->poolId))))
            ->with(['resourcePool', 'location', 'bookedBy', 'items'])
            ->get();

        return view('livewire.admin.reports-index', [
            'pools' => ResourcePool::withTrashed()->orderBy('name')->get(),
            'total' => $bookings->count(),
            'totalUnits' => $bookings->sum(fn (Booking $b) => $this->primaryQty($b)),
            'volume' => $this->volume($bookings),
            'utilisation' => $this->utilisation($bookings, $start, $end),
            'busiestDays' => $this->busiestDays($bookings),
            'topRequestors' => $this->topBy($bookings, fn (Booking $b) => $b->bookedBy?->name ?? '—'),
            'topRooms' => $this->topBy($bookings, fn (Booking $b) => $b->location?->name ?? 'No room'),
            'approval' => $this->approvalStats($bookings),
        ]);
    }

    public function export()
    {
        [$start, $end] = $this->range();

        $rows = Booking::query()
            ->whereBetween('start_at', [$start, $end])
            ->when(! $this->withCancelled, fn ($q) => $q->where('lifecycle_status', '!=', 'cancelled'))
            ->when($this->poolId, fn ($q) => $q->where('resource_pool_id', $this->poolId))
            ->with(['resourcePool', 'location', 'bookedBy', 'items'])
            ->orderBy('start_at')
            ->get();

        $filename = "kitloan-bookings-{$this->from}_to_{$this->to}.csv";

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['reference', 'date', 'start', 'end', 'pool', 'room', 'campus', 'requestor', 'quantity', 'status', 'auto_approved', 'created_at', 'approved_at']);
            foreach ($rows as $b) {
                fputcsv($out, [
                    $b->reference,
                    $b->start_at->toDateString(),
                    $b->start_at->format('H:i'),
                    $b->end_at->format('H:i'),
                    $b->resourcePool->name,
                    $b->location?->name ?? '',
                    $b->location?->campus ?? '',
                    $b->bookedBy?->name ?? '',
                    $this->primaryQty($b),
                    $b->approval_status,
                    $b->auto_approved ? 'yes' : 'no',
                    $b->created_at?->toDateTimeString(),
                    $b->approved_at?->toDateTimeString(),
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function range(): array
    {
        return [
            Carbon::parse($this->from ?: now()->subDays(90))->startOfDay(),
            Carbon::parse($this->to ?: now())->endOfDay(),
        ];
    }

    private function primaryQty(Booking $b): int
    {
        return (int) ($b->items->firstWhere('resource_pool_id', $b->resource_pool_id)?->quantity_requested
            ?? $b->items->sum('quantity_requested'));
    }

    /** @return array<string, array{count: int, units: int}> */
    private function volume($bookings): array
    {
        return $bookings
            ->groupBy(fn (Booking $b) => $b->start_at->format('Y-m'))
            ->map(fn ($group) => [
                'count' => $group->count(),
                'units' => $group->sum(fn (Booking $b) => $this->primaryQty($b)),
            ])
            ->sortKeys()
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function utilisation($bookings, Carbon $start, Carbon $end): array
    {
        $businessDays = $this->businessDays($start, $end);

        return $bookings
            ->groupBy(fn (Booking $b) => $b->resourcePool->id)
            ->map(function ($group) use ($businessDays) {
                /** @var ResourcePool $pool */
                $pool = $group->first()->resourcePool;
                $capacity = $pool->isQuantityTracked()
                    ? (int) $pool->quantity_total
                    : $pool->resources()->count();

                $resourceDays = $group->sum(function (Booking $b) {
                    $spanDays = max(1, $b->start_at->copy()->startOfDay()->diffInDays($b->end_at->copy()->startOfDay()) + 1);

                    return $this->primaryQty($b) * $spanDays;
                });

                return [
                    'pool' => $pool->name,
                    'bookings' => $group->count(),
                    'resource_days' => $resourceDays,
                    'capacity_days' => $capacity * $businessDays,
                    'utilisation' => $capacity > 0 && $businessDays > 0
                        ? round(100 * $resourceDays / ($capacity * $businessDays), 1)
                        : null,
                ];
            })
            ->sortByDesc('resource_days')
            ->values()
            ->all();
    }

    /** @return list<array{date: string, units: int}> */
    private function busiestDays($bookings): array
    {
        $byDay = [];
        foreach ($bookings as $b) {
            $key = $b->start_at->toDateString();
            $byDay[$key] = ($byDay[$key] ?? 0) + $this->primaryQty($b);
        }
        arsort($byDay);

        return collect($byDay)->take(10)
            ->map(fn ($units, $date) => ['date' => $date, 'units' => $units])
            ->values()->all();
    }

    /** @return list<array{label: string, count: int, units: int}> */
    private function topBy($bookings, callable $key): array
    {
        return $bookings
            ->groupBy($key)
            ->map(fn ($group, $label) => [
                'label' => (string) $label,
                'count' => $group->count(),
                'units' => $group->sum(fn (Booking $b) => $this->primaryQty($b)),
            ])
            ->sortByDesc('count')
            ->take(10)
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function approvalStats($bookings): array
    {
        $auto = $bookings->where('auto_approved', true)->count();
        $manual = $bookings->where('approval_status', 'approved')->where('auto_approved', false)->count();
        $rejected = $bookings->where('approval_status', 'rejected')->count();
        $pending = $bookings->where('approval_status', 'pending')->count();
        $decided = $auto + $manual + $rejected;

        $manualApprovedWithTimes = $bookings
            ->where('approval_status', 'approved')
            ->where('auto_approved', false)
            ->filter(fn (Booking $b) => $b->approved_at && $b->created_at);

        $avgHours = $manualApprovedWithTimes->isNotEmpty()
            ? round($manualApprovedWithTimes->avg(fn (Booking $b) => $b->created_at->diffInMinutes($b->approved_at) / 60), 1)
            : null;

        return [
            'auto' => $auto,
            'manual' => $manual,
            'rejected' => $rejected,
            'pending' => $pending,
            'rejection_rate' => $decided > 0 ? round(100 * $rejected / $decided, 1) : null,
            'avg_hours_to_approval' => $avgHours,
        ];
    }

    private function businessDays(Carbon $start, Carbon $end): int
    {
        $days = 0;
        $cursor = $start->copy()->startOfDay();
        while ($cursor->lte($end)) {
            if (! $cursor->isWeekend()) {
                $days++;
            }
            $cursor->addDay();
        }

        return $days;
    }
}
