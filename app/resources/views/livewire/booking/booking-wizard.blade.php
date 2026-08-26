<div>
    <div class="flex items-center gap-3">
        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-indigo-50 text-indigo-600">
            <x-pool-icon :icon="$pool->icon" class="w-5 h-5" />
        </span>
        <h1 class="text-2xl font-semibold tracking-tight text-gray-900">{{ $pool->name }} Booking</h1>
    </div>

    @if ($conflictError)
        <div class="mt-4 rounded-md bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-inset ring-red-600/20">
            {{ $conflictError }}
        </div>
    @endif

    <div class="mt-6 grid grid-cols-1 gap-8 lg:grid-cols-3">
        {{-- Left: booking details --}}
        <div class="lg:col-span-1 space-y-5 rounded-xl border border-gray-200 bg-white p-5 h-fit">
            <div class="grid grid-cols-1 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Date</label>
                    <input type="date" wire:model.live="date" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                @if ($this->periods->isNotEmpty())
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Quick fill from period</label>
                        <select wire:model.live="quickPeriodId" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Select a period&hellip;</option>
                            @foreach ($this->periods as $groupName => $groupPeriods)
                                <optgroup label="{{ $groupName }}">
                                    @foreach ($groupPeriods as $period)
                                        <option value="{{ $period->id }}">{{ $period->name }} ({{ $period->start_time->format('g:i A') }}&ndash;{{ $period->end_time->format('g:i A') }})</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Start</label>
                        <input type="time" wire:model.live="startTime" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Finish</label>
                        <input type="time" wire:model.live="endTime" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                </div>

                @if ($pool->requires_room)
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Room</label>
                        <x-searchable-select
                            wire:model="locationId"
                            :options="$this->locations->map(fn ($l) => ['value' => $l->id, 'label' => $l->code.' — '.$l->name])"
                            placeholder="Select a room…"
                            search-placeholder="Search rooms…"
                        />
                        @error('locationId') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endif

                @if ($pool->requires_booking_type)
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Exam Type</label>
                        <select wire:model="bookingTypeId" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Select&hellip;</option>
                            @foreach ($this->bookingTypes as $type)
                                <option value="{{ $type->id }}">{{ $type->name }}</option>
                            @endforeach
                        </select>
                        @error('bookingTypeId') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endif

                @if ($pool->allows_student)
                    <div>
                        <label class="block text-sm font-medium text-gray-700">
                            Student{{ $pool->requires_student ? '' : ' (optional)' }}
                        </label>
                        <textarea wire:model="studentNamesRaw" rows="2" placeholder="One student per line" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                        @error('studentNamesRaw') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endif

                <div>
                    <label class="block text-sm font-medium text-gray-700">Notes (optional)</label>
                    <textarea wire:model="notes" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                </div>

                @if ($pool->isIndividuallyTracked())
                    <div>
                        <span class="block text-sm font-medium text-gray-700">How many do you need?</span>
                        <div class="mt-1 flex rounded-md shadow-sm">
                            <button type="button" wire:click="$set('useSpecificSelection', false)"
                                    class="flex-1 rounded-l-md border px-3 py-2 text-sm font-medium {{ ! $useSpecificSelection ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-700 border-gray-300' }}">
                                Request a quantity
                            </button>
                            <button type="button" wire:click="$set('useSpecificSelection', true)"
                                    class="flex-1 rounded-r-md border-y border-r px-3 py-2 text-sm font-medium {{ $useSpecificSelection ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-700 border-gray-300' }}">
                                Pick specific items
                            </button>
                        </div>
                    </div>

                    @if (! $useSpecificSelection)
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Number Required</label>
                            <input type="number" min="1" wire:model.live="quantityRequested" class="mt-1 block w-32 rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @error('quantityRequested') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    @endif
                @else
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Number Required</label>
                        <input type="number" min="1" wire:model.live="quantityRequested" class="mt-1 block w-32 rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @if (! is_null($this->availableQuantity))
                            <p class="mt-1 text-xs text-gray-500">{{ $this->availableQuantity }} available for this time.</p>
                        @endif
                        @error('quantityRequested') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endif
            </div>

            {{-- Additional resources --}}
            @if ($this->otherPools->isNotEmpty())
                <div class="border-t border-gray-100 pt-4">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-gray-700">Additional Resources</span>
                        <button type="button" wire:click="addAdditionalItem" class="text-xs font-medium text-indigo-600 hover:text-indigo-500">+ Add</button>
                    </div>
                    @foreach ($additionalItems as $index => $extra)
                        <div class="mt-2 flex gap-2">
                            <select wire:model="additionalItems.{{ $index }}.resource_pool_id" class="flex-1 rounded-md border-gray-300 text-sm">
                                <option value="">Select&hellip;</option>
                                @foreach ($this->otherPools as $other)
                                    <option value="{{ $other->id }}">{{ $other->name }}</option>
                                @endforeach
                            </select>
                            <input type="number" min="1" wire:model="additionalItems.{{ $index }}.quantity" class="w-20 rounded-md border-gray-300 text-sm">
                            <button type="button" wire:click="removeAdditionalItem({{ $index }})" class="text-gray-400 hover:text-red-500">&times;</button>
                        </div>
                    @endforeach
                </div>
            @endif

            @php($preview = $this->approvalPreview)
            @if (!empty($preview['windowErrors']))
                <div class="rounded-md bg-red-50 px-3 py-2 text-xs text-red-700 ring-1 ring-inset ring-red-600/20">
                    @foreach ($preview['windowErrors'] as $err) <p>{{ $err }}</p> @endforeach
                </div>
            @elseif (!empty($preview['approvalReasons']))
                <div class="rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-700 ring-1 ring-inset ring-amber-600/20">
                    <p class="font-medium">This booking will need IT approval:</p>
                    @foreach ($preview['approvalReasons'] as $reason) <p>&bull; {{ $reason }}</p> @endforeach
                </div>
            @else
                <div class="rounded-md bg-emerald-50 px-3 py-2 text-xs text-emerald-700 ring-1 ring-inset ring-emerald-600/20">
                    This booking will be automatically approved.
                </div>
            @endif

            <button type="button" wire:click="submit" wire:loading.attr="disabled"
                    class="w-full rounded-md bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-60">
                <span wire:loading.remove wire:target="submit">
                    Book {{ $pool->isIndividuallyTracked() ? $pool->name : $pool->name }}
                </span>
                <span wire:loading wire:target="submit">Submitting&hellip;</span>
            </button>
        </div>

        {{-- Right: resource grid / quantity summary --}}
        <div class="lg:col-span-2">
            @if ($pool->isIndividuallyTracked())
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-medium text-gray-700">Available {{ $pool->name }}</h2>
                    @if ($useSpecificSelection)
                        <span class="text-sm text-gray-500">Selected: {{ count($selectedResourceIds) }}</span>
                    @endif
                </div>

                <div class="mt-3 grid grid-cols-3 gap-3 sm:grid-cols-4 md:grid-cols-5">
                    @forelse ($this->resourceGrid ?? [] as $entry)
                        @php($resource = $entry['resource'])
                        <button type="button"
                                @if ($useSpecificSelection && $entry['available']) wire:click="toggleResource({{ $resource->id }})" @endif
                                @disabled(! $useSpecificSelection || ! $entry['available'])
                                title="{{ $resource->name }} &mdash; {{ ucfirst($resource->status) }}"
                                class="group relative flex flex-col items-center gap-1.5 rounded-lg border p-3 text-center transition
                                    {{ $entry['selected'] ? 'border-indigo-600 bg-indigo-50 ring-2 ring-indigo-600' : ($entry['available'] ? 'border-gray-200 bg-white hover:border-indigo-300 cursor-pointer' : 'border-gray-100 bg-gray-50 opacity-50 cursor-not-allowed') }}">
                            <x-pool-icon :icon="$pool->icon" class="w-6 h-6 {{ $entry['available'] ? 'text-gray-700' : 'text-gray-300' }}" />
                            <span class="text-xs font-medium text-gray-700">{{ $resource->name }}</span>
                            <x-status-badge :status="$entry['selected'] ? 'available' : ($entry['available'] ? 'available' : $resource->status)" class="text-[10px] px-1.5 py-0">
                                {{ $entry['selected'] ? 'Selected' : ($entry['available'] ? 'Available' : ucfirst($resource->status)) }}
                            </x-status-badge>
                        </button>
                    @empty
                        <p class="col-span-full text-sm text-gray-500">No resources configured in this pool yet.</p>
                    @endforelse
                </div>

                @unless ($useSpecificSelection)
                    <p class="mt-4 text-sm text-gray-500">
                        The system will automatically choose {{ $quantityRequested }} available {{ Str::plural(strtolower($pool->name), $quantityRequested) }} for you at submission.
                        Grid shown above reflects current availability.
                    </p>
                @endunless
            @else
                <div class="rounded-xl border border-gray-200 bg-white p-8 text-center">
                    <x-pool-icon :icon="$pool->icon" class="mx-auto w-10 h-10 text-gray-400" />
                    <p class="mt-3 text-2xl font-semibold text-gray-900">{{ $this->availableQuantity ?? '—' }}</p>
                    <p class="text-sm text-gray-500">available for the selected time</p>
                </div>
            @endif
        </div>
    </div>
</div>
