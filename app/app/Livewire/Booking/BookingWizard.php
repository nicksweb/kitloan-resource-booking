<?php

namespace App\Livewire\Booking;

use App\Exceptions\BookingConflictException;
use App\Models\Booking;
use App\Models\BookingType;
use App\Models\Location;
use App\Models\Resource;
use App\Models\ResourcePool;
use App\Models\SchedulePeriod;
use App\Models\User;
use App\Services\Auth\ImpersonationManager;
use App\Services\Booking\ApprovalEvaluator;
use App\Services\Booking\AvailabilityService;
use App\Services\Booking\BookingService;
use App\Settings\SettingsRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
class BookingWizard extends Component
{
    public ResourcePool $pool;

    public string $date;

    public string $startTime;

    public string $endTime;

    public ?int $locationId = null;

    /** room | pickup | other */
    public string $roomChoice = 'room';

    public ?int $bookingTypeId = null;

    public string $studentNamesRaw = '';

    public string $notes = '';

    public string $helpdeskUrl = '';

    /** @var array<int> */
    public array $selectedResourceIds = [];

    public int $quantityRequested = 1;

    public bool $useSpecificSelection = false;

    /** @var array<int, array{resource_pool_id: int, quantity: int}> */
    public array $additionalItems = [];

    public ?string $conflictError = null;

    /**
     * Scratch binding for the "quick fill from period" <select>. Bound with
     * wire:model.live so picking an option round-trips to updatedQuickPeriodId()
     * server-side — no Alpine `$wire` call from the blade (that was the cause of
     * the "$wire is not defined" error when the select sat outside any Alpine
     * component scope).
     */
    public ?int $quickPeriodId = null;

    /**
     * Whose booking this is. Defaults to the current user; IT/admin can point
     * it at anyone (see canBookForOthers()). created_by always stays the real
     * actor for the audit trail.
     */
    public ?int $bookedByUserId = null;

    public function mount(ResourcePool $resourcePool, SettingsRepository $settings): void
    {
        abort_unless($resourcePool->enabled, 404);

        $this->pool = $resourcePool;
        $this->bookedByUserId = auth()->id();
        $this->useSpecificSelection = $resourcePool->isIndividuallyTracked();

        [$this->date, $this->startTime] = $this->defaultWindow($resourcePool, $settings);
        $this->endTime = Carbon::createFromFormat('H:i', $this->startTime)->addHour()->format('H:i');
    }

    /**
     * Picks a sensible starting point for a new booking instead of always
     * "an hour from now" — which is a genuinely useless default at, say,
     * 11pm. Within school hours (with enough of the day left for a 1-hour
     * booking), that's still "now + 1 hour". Outside school hours, jumps to
     * the next school day's opening time instead — skipping weekends if the
     * pool doesn't allow them.
     *
     * @return array{0: string, 1: string} [date (Y-m-d), startTime (H:i)]
     */
    private function defaultWindow(ResourcePool $resourcePool, SettingsRepository $settings): array
    {
        $now = Carbon::now(config('app.timezone'));
        $schoolStart = (string) $settings->get('school_day_start', '07:00');
        $schoolFinish = (string) $settings->get('school_day_finish', '17:00');

        [$startHour, $startMinute] = array_map('intval', explode(':', $schoolStart));
        [$finishHour, $finishMinute] = array_map('intval', explode(':', $schoolFinish));

        $todayStart = $now->copy()->setTime($startHour, $startMinute, 0);
        $todayFinish = $now->copy()->setTime($finishHour, $finishMinute, 0);

        if ($now->lt($todayStart)) {
            $date = $now->copy();
            $start = $todayStart;
        } elseif ($now->lt($todayFinish->copy()->subHour())) {
            $date = $now->copy();
            $start = $now->copy()->addHour()->startOfHour();
        } else {
            $date = $now->copy()->addDay()->startOfDay();
            $start = $date->copy()->setTime($startHour, $startMinute, 0);
        }

        if (! $resourcePool->allow_weekends) {
            while ($date->isWeekend()) {
                $date->addDay();
                $start->setDate($date->year, $date->month, $date->day);
            }
        }

        return [$date->format('Y-m-d'), $start->format('H:i')];
    }

    /**
     * Whenever the start time changes, jump the finish time to an hour
     * later — most bookings are short and single-period, so this saves a
     * second field edit for the common case. Fires only on user-driven
     * changes (not during mount()), so it never touches a value the user
     * hasn't actually interacted with yet.
     */
    public function updatedStartTime(string $value): void
    {
        try {
            $this->endTime = Carbon::createFromFormat('H:i', $value)->addHour()->format('H:i');
        } catch (\Throwable) {
            // Malformed/incomplete input while typing — leave endTime alone.
        }
    }

    public function applyPeriod(int $periodId): void
    {
        $period = SchedulePeriod::find($periodId);
        if (! $period) {
            return;
        }

        $this->startTime = $period->start_time->format('H:i');
        $this->endTime = $period->end_time->format('H:i');
    }

    public function updatedQuickPeriodId($value): void
    {
        if ($value) {
            $this->applyPeriod((int) $value);
        }

        // Snap the control back to its placeholder — it's a one-shot action,
        // not a field whose value we keep.
        $this->quickPeriodId = null;
    }

    #[Title('Book Resources')]
    public function render()
    {
        return view('livewire.booking.booking-wizard');
    }

    #[Computed]
    public function canBookForOthers(): bool
    {
        return auth()->user()->can('createForOthers', Booking::class);
    }

    #[Computed]
    public function bookableUsers()
    {
        if (! $this->canBookForOthers()) {
            return collect();
        }

        return User::query()->orderBy('name')->get(['id', 'name', 'email'])
            ->map(fn (User $u) => ['value' => $u->id, 'label' => "{$u->name} — {$u->email}"]);
    }

    #[Computed]
    public function locations()
    {
        return Location::enabled()->ordered()->get();
    }

    #[Computed]
    public function bookingTypes()
    {
        return BookingType::enabled()->ordered()->get();
    }

    #[Computed]
    public function periods()
    {
        return SchedulePeriod::enabled()->ordered()->get()->groupBy('group_name');
    }

    #[Computed]
    public function otherPools()
    {
        return ResourcePool::enabled()->where('id', '!=', $this->pool->id)->ordered()->get();
    }

    #[Computed]
    public function window(): ?array
    {
        try {
            $start = Carbon::createFromFormat('Y-m-d H:i', "{$this->date} {$this->startTime}", config('app.timezone'));
            $end = Carbon::createFromFormat('Y-m-d H:i', "{$this->date} {$this->endTime}", config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }

        return [$start, $end];
    }

    #[Computed]
    public function resourceGrid(): ?Collection
    {
        if (! $this->pool->isIndividuallyTracked() || ! $this->window()) {
            return null;
        }

        [$start, $end] = $this->window();
        $availability = app(AvailabilityService::class);
        $availableIds = $availability->availableResourceIds($this->pool, $start, $end)->all();

        $grid = Resource::query()
            ->where('resource_pool_id', $this->pool->id)
            ->whereIn('status', ['available', 'unavailable', 'maintenance'])
            ->orderBy('display_order')->orderBy('name')
            ->get()
            ->map(function (Resource $resource) use ($availableIds) {
                $isAvailable = $resource->status === 'available' && in_array($resource->id, $availableIds, true);

                return [
                    'resource' => $resource,
                    'available' => $isAvailable,
                    'selected' => in_array($resource->id, $this->selectedResourceIds, true),
                ];
            });

        // In "request a quantity" mode the user can't hand-pick, so preview
        // which units submission will actually grab — the first N available in
        // allocation order — and mark those as selected. Without this the grid
        // keeps showing a stale hand-selection, which still reads as "only 4
        // chosen" even after the quantity is bumped to 5.
        if (! $this->useSpecificSelection) {
            $remaining = max(0, $this->quantityRequested);

            $grid = $grid->map(function (array $entry) use (&$remaining) {
                $take = $entry['available'] && $remaining > 0;
                $entry['selected'] = $take;
                $remaining -= $take ? 1 : 0;

                return $entry;
            });
        }

        return $grid;
    }

    #[Computed]
    public function availableQuantity(): ?int
    {
        if (! $this->pool->isQuantityTracked() || ! $this->window()) {
            return null;
        }

        [$start, $end] = $this->window();

        return app(AvailabilityService::class)->availableQuantity($this->pool, $start, $end);
    }

    #[Computed]
    public function approvalPreview(): array
    {
        if (! $this->window()) {
            return [];
        }

        [$start, $end] = $this->window();
        $evaluator = app(ApprovalEvaluator::class);
        $windowErrors = $evaluator->validateWindow($this->pool, $start, $end);
        $bookingType = $this->bookingTypeId ? BookingType::find($this->bookingTypeId) : null;
        $quantity = $this->useSpecificSelection ? count($this->selectedResourceIds) : $this->quantityRequested;

        $approvalReasons = $windowErrors ? [] : $evaluator->reasonsRequiringApproval($this->pool, $start, $end, $bookingType, $quantity);

        return [
            'windowErrors' => $windowErrors,
            'approvalReasons' => $approvalReasons,
        ];
    }

    /**
     * Toggling "pick specific items" ⇄ "request a quantity". Coming back to a
     * bare number, carry the hand-selected count across so the quantity field
     * isn't a jarring reset to 1; dropping the computed grid lets it repaint
     * to match whichever mode we're now in.
     */
    public function updatedUseSpecificSelection($value): void
    {
        if (! $value && $this->selectedResourceIds !== []) {
            $this->quantityRequested = max(1, count($this->selectedResourceIds));
        }

        unset($this->resourceGrid);
    }

    public function toggleResource(int $resourceId): void
    {
        if (in_array($resourceId, $this->selectedResourceIds, true)) {
            $this->selectedResourceIds = array_values(array_diff($this->selectedResourceIds, [$resourceId]));

            return;
        }

        $grid = $this->resourceGrid();
        $entry = $grid?->firstWhere('resource.id', $resourceId);

        if ($entry && $entry['available']) {
            $this->selectedResourceIds[] = $resourceId;
        }
    }

    public function addAdditionalItem(): void
    {
        $this->additionalItems[] = ['resource_pool_id' => null, 'quantity' => 1];
    }

    public function removeAdditionalItem(int $index): void
    {
        unset($this->additionalItems[$index]);
        $this->additionalItems = array_values($this->additionalItems);
    }

    public function submit(BookingService $bookingService): void
    {
        $this->conflictError = null;

        $validated = $this->validate($this->rules());

        [$start, $end] = $this->window();

        $items = [[
            'resource_pool_id' => $this->pool->id,
            'quantity' => $this->useSpecificSelection ? count($this->selectedResourceIds) : $this->quantityRequested,
            'resource_ids' => $this->useSpecificSelection ? $this->selectedResourceIds : null,
        ]];

        foreach ($this->additionalItems as $extra) {
            if (! empty($extra['resource_pool_id']) && (int) $extra['quantity'] > 0) {
                $items[] = [
                    'resource_pool_id' => (int) $extra['resource_pool_id'],
                    'quantity' => (int) $extra['quantity'],
                    'resource_ids' => null,
                ];
            }
        }

        $students = collect(preg_split('/\r\n|\r|\n/', $this->studentNamesRaw))
            ->map(fn ($line) => trim($line))
            ->filter()
            ->map(fn ($name) => ['student_name' => $name])
            ->values()
            ->all();

        // created_by is the real actor — an admin impersonating someone, or an
        // operator booking on another user's behalf, should show up as such in
        // the audit trail, not as the requestor.
        $actor = app(ImpersonationManager::class)->actor() ?? auth()->user();

        // booked_by is the requestor: the current session user, unless a
        // privileged user explicitly picked someone else.
        $bookedBy = ($this->canBookForOthers() && $this->bookedByUserId)
            ? User::findOrFail($this->bookedByUserId)
            : auth()->user();

        try {
            $booking = $bookingService->create([
                'resource_pool_id' => $this->pool->id,
                'location_id' => $this->roomChoice === 'room' ? $this->locationId : null,
                'room_choice' => $this->roomChoice,
                'booking_type_id' => $this->bookingTypeId,
                'start_at' => $start,
                'end_at' => $end,
                'notes' => $this->notes ?: null,
                'helpdesk_url' => trim($this->helpdeskUrl) ?: null,
                'students' => $students,
                'items' => $items,
            ], $bookedBy, $actor);
        } catch (BookingConflictException $e) {
            $this->conflictError = $e->getMessage();
            unset($this->resourceGrid, $this->availableQuantity);

            return;
        }

        session()->flash('success', "Booking {$booking->reference} ".($booking->approval_status === 'approved' ? 'confirmed.' : 'submitted and is awaiting approval.'));

        $this->redirectRoute('bookings.show', $booking, navigate: true);
    }

    protected function rules(): array
    {
        $rules = [
            'date' => ['required', 'date'],
            'startTime' => ['required'],
            'endTime' => ['required'],
            'roomChoice' => ['required', 'in:room,pickup,other'],
            'locationId' => [$this->pool->requires_room && $this->roomChoice === 'room' ? 'required' : 'nullable', 'exists:locations,id'],
            'bookingTypeId' => [$this->pool->requires_booking_type ? 'required' : 'nullable', 'exists:booking_types,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'helpdeskUrl' => ['nullable', 'url:http,https', 'max:2000'],
            'studentNamesRaw' => [$this->pool->requires_student ? 'required' : 'nullable', 'string', 'max:2000'],
            'bookedByUserId' => ['nullable', 'integer', 'exists:users,id'],
        ];

        if ($this->useSpecificSelection) {
            $rules['selectedResourceIds'] = ['required', 'array', 'min:1'];
        } else {
            $rules['quantityRequested'] = ['required', 'integer', 'min:1'];
        }

        return $rules;
    }

    protected function validationAttributes(): array
    {
        return [
            'locationId' => 'room',
            'bookingTypeId' => 'exam type',
            'studentNamesRaw' => 'student name',
            'selectedResourceIds' => 'resource selection',
            'quantityRequested' => 'quantity',
        ];
    }
}
