@props(['icon' => 'laptop', 'class' => 'w-6 h-6'])
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" {{ $attributes->merge(['class' => $class]) }}>
    @switch($icon)
        @case('bolt')
            <path d="M13 2 4 14h6l-1 8 9-12h-6l1-8Z" />
            @break
        @case('monitor')
            <rect x="3" y="4" width="18" height="12" rx="1.5" />
            <path d="M8 20h8M12 16v4" />
            @break
        @case('camera')
            <path d="M4 8h3l1.5-2h7L17 8h3a1 1 0 0 1 1 1v9a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1Z" />
            <circle cx="12" cy="13" r="3.5" />
            @break
        @case('device')
            <rect x="6" y="2" width="12" height="20" rx="2" />
            <path d="M11 18h2" />
            @break
        @case('it-officer')
            <path d="M4 13v-1a8 8 0 0 1 16 0v1" />
            <rect x="2.5" y="12.5" width="3.5" height="5.5" rx="1.4" />
            <rect x="18" y="12.5" width="3.5" height="5.5" rx="1.4" />
            <path d="M20 18v.5a3 3 0 0 1-3 3h-2.5" />
            <circle cx="12" cy="8.5" r="2.6" />
            @break
        @default
            <rect x="3" y="4" width="18" height="12" rx="1.5" />
            <path d="M2 19.5h20a1 1 0 0 0 1-1.2l-.4-1.8a1 1 0 0 0-1-.8H2.4a1 1 0 0 0-1 .8l-.4 1.8a1 1 0 0 0 1 1.2Z" />
            <path d="M10 16.5h4" />
    @endswitch
</svg>
