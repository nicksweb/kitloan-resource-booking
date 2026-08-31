@props([
    'locations',       // Collection of Location models
    'roomChoice',      // current value of the parent component's $roomChoice
])

{{-- Bound to the parent Livewire component's $roomChoice + $locationId. --}}
<div>
    <label class="block text-sm font-medium text-gray-700">Room</label>

    <div class="mt-1 flex rounded-md shadow-sm">
        @foreach (['room' => 'Room', 'pickup' => 'Pick-up from IT', 'other' => 'Other'] as $value => $label)
            <button type="button" wire:click="$set('roomChoice', '{{ $value }}')"
                class="flex-1 border px-2 py-2 text-xs font-medium first:rounded-l-md last:rounded-r-md -ml-px first:ml-0
                    @if ($roomChoice === $value) bg-indigo-600 text-white border-indigo-600 relative z-10
                    @else bg-white text-gray-700 border-gray-300 hover:bg-gray-50 @endif">
                {{ $label }}
            </button>
        @endforeach
    </div>

    @if ($roomChoice === 'room')
        <div class="mt-2">
            <x-searchable-select
                wire:model="locationId"
                :options="$locations->map(fn ($l) => ['value' => $l->id, 'label' => $l->code.' — '.$l->name])"
                placeholder="Select a room…"
                search-placeholder="Search rooms…"
            />
        </div>
    @elseif ($roomChoice === 'pickup')
        <p class="mt-2 text-xs text-gray-500">The requestor will collect the equipment from IT.</p>
    @else
        <p class="mt-2 text-xs text-gray-500">Room to be confirmed — add it to Notes below once you know it.</p>
    @endif

    @error('roomChoice') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    @error('locationId') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
</div>
