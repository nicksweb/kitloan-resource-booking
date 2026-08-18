<?php

namespace App\Livewire\Booking;

use App\Exceptions\BookingConflictException;
use App\Models\BookingType;
use App\Models\Location;
use App\Models\Resource;
use App\Models\ResourcePool;
use App\Services\Booking\ApprovalEvaluator;
use App\Services\Booking\AvailabilityService;
use App\Services\Booking\BookingService;
use Illuminate\Support\Carbon;
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

    public ?int $bookingTypeId = null;

    public string $studentNamesRaw = '';

    public string $notes = '';

    /** @var array<int> */
    public array $selectedResourceIds = [];

    public int $quantityRequested = 1;

    public bool $useSpecificSelection = false;

    /** @var array<int, array{resource_pool_id: int, quantity: int}> */
    public array $additionalItems = [];

    public ?string $conflictError = null;

    public function mount(ResourcePool $resourcePool): void
    {
        abort_unless($resourcePool->enabled, 404);

        $this->pool = $resourcePool;
        $this->date = now(config('app.timezone'))->addDay()->format('Y-m-d');
        $this->startTime = now(config('app.timezone'))->addHour()->startOfHour()->format('H:i');
        $this->endTime = now(config('app.timezone'))->addHours(2)->startOfHour()->format('H:i');
        $this->useSpecificSelection = $resourcePool->isIndividuallyTracked();
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
        $period = \App\Models\SchedulePeriod::find($periodId);
        if (! $period) {
            return;
        }

        $this->startTime = $period->start_time->format('H:i');
        $this->endTime = $period->end_time->format('H:i');
    }

    #[Title('Book Resources')]
    public function render()
    {
        return view('livewire.booking.booking-wizard');
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
        return \App\Models\SchedulePeriod::enabled()->ordered()->get()->groupBy('group_name');
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
    public function resourceGrid(): ?\Illuminate\Support\Collection
    {
        if (! $this->pool->isIndividuallyTracked() || ! $this->window()) {
            return null;
        }

        [$start, $end] = $this->window();
        $availability = app(AvailabilityService::class);
        $availableIds = $availability->availableResourceIds($this->pool, $start, $end)->all();

        return Resource::query()
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

        // booked_by is whoever the session is currently authenticated as
        // (the impersonated user, if impersonating); created_by is the real
        // actor — an admin impersonating someone to book on their behalf
        // should show up as such in the audit trail, not as the user itself.
        $actor = app(\App\Services\Auth\ImpersonationManager::class)->actor() ?? auth()->user();

        try {
            $booking = $bookingService->create([
                'resource_pool_id' => $this->pool->id,
                'location_id' => $this->locationId,
                'booking_type_id' => $this->bookingTypeId,
                'start_at' => $start,
                'end_at' => $end,
                'notes' => $this->notes ?: null,
                'students' => $students,
                'items' => $items,
            ], auth()->user(), $actor);
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
            'locationId' => [$this->pool->requires_room ? 'required' : 'nullable', 'exists:locations,id'],
            'bookingTypeId' => [$this->pool->requires_booking_type ? 'required' : 'nullable', 'exists:booking_types,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'studentNamesRaw' => [$this->pool->requires_student ? 'required' : 'nullable', 'string', 'max:2000'],
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
