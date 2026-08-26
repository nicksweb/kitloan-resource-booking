{{--
    Searchable single-select. Usage:
        <x-searchable-select wire:model="locationId" :options="$options"
            placeholder="Choose a room…" />
    where $options is an array/collection of ['value' => ..., 'label' => ...]
    (or objects with ->value / ->label). Pairs with the `searchableSelect`
    Alpine component in resources/js/app.js.
--}}
@props([
    'options' => [],
    'placeholder' => 'Select…',
    'searchPlaceholder' => 'Type to filter…',
    'allowClear' => true,
])

@php
    $normalisedOptions = collect($options)->map(fn ($o) => [
        'value' => (string) (is_array($o) ? $o['value'] : $o->value),
        'label' => (string) (is_array($o) ? $o['label'] : $o->label),
    ])->values();
@endphp

<div
    x-data="searchableSelect({
        selected: @entangle($attributes->wire('model')),
        options: {{ \Illuminate\Support\Js::from($normalisedOptions) }},
        placeholder: @js($placeholder),
    })"
    @keydown.escape.prevent.stop="close()"
    @keydown.arrow-down.prevent="move(1)"
    @keydown.arrow-up.prevent="move(-1)"
    @keydown.enter.prevent="chooseHighlighted()"
    @click.outside="close()"
    class="relative"
>
    <button
        type="button"
        x-ref="button"
        @click="toggle()"
        class="mt-1 flex w-full items-center justify-between gap-2 rounded-md border border-gray-300 bg-white px-3 py-2 text-left text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
    >
        <span x-text="selectedLabel()" :class="selected ? 'text-gray-900' : 'text-gray-400'" class="truncate"></span>
        <svg class="h-4 w-4 shrink-0 text-gray-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M10 3a.75.75 0 01.55.24l3.25 3.5a.75.75 0 11-1.1 1.02L10 4.852 7.3 7.76a.75.75 0 01-1.1-1.02l3.25-3.5A.75.75 0 0110 3zm-3.76 9.2a.75.75 0 011.06.04L10 15.148l2.7-2.908a.75.75 0 111.1 1.02l-3.25 3.5a.75.75 0 01-1.1 0l-3.25-3.5a.75.75 0 01.04-1.06z" clip-rule="evenodd" />
        </svg>
    </button>

    <div
        x-show="open"
        x-cloak
        x-transition.origin.top.duration.100ms
        class="absolute z-30 mt-1 w-full rounded-md border border-gray-200 bg-white shadow-lg"
    >
        <div class="p-2">
            <input
                type="text"
                x-model="query"
                x-ref="search"
                placeholder="{{ $searchPlaceholder }}"
                class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            >
        </div>
        <ul x-ref="list" class="max-h-56 overflow-auto py-1 text-sm">
            @if ($allowClear)
                <li
                    @click="clear()"
                    class="cursor-pointer px-3 py-1.5 text-gray-400 hover:bg-gray-100"
                >{{ $placeholder }}</li>
            @endif
            <template x-for="(opt, index) in filtered()" :key="opt.value">
                <li
                    x-text="opt.label"
                    @click="choose(opt.value)"
                    @mousemove="highlighted = index"
                    :class="highlighted === index ? 'bg-indigo-600 text-white' : 'text-gray-900'"
                    class="cursor-pointer truncate px-3 py-1.5"
                ></li>
            </template>
            <li x-show="filtered().length === 0" class="px-3 py-1.5 text-gray-400">No matches</li>
        </ul>
    </div>
</div>
