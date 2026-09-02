<!DOCTYPE html>
<html lang="en" class="h-full bg-gray-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ isset($title) ? $title.' - ' : '' }}{{ config('app.name') }}</title>
    <x-theme-script :theme="auth()->user()?->theme ?? 'system'" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full text-gray-900 antialiased">
<div x-data="{ mobileOpen: false }" class="min-h-full">
    @if (app(\App\Services\Auth\ImpersonationManager::class)->isImpersonating())
        <div class="flex items-center justify-center gap-3 bg-amber-500 px-4 py-2 text-sm font-medium text-amber-950">
            <span>You're signed in as {{ auth()->user()->name }} (impersonated by {{ app(\App\Services\Auth\ImpersonationManager::class)->impersonator()?->name }})</span>
            <form method="POST" action="{{ route('impersonation.stop') }}">
                @csrf
                <button type="submit" class="rounded bg-amber-950/10 px-2 py-0.5 font-semibold hover:bg-amber-950/20">Return to my account</button>
            </form>
        </div>
    @endif
    @unless (session('embedded'))
    <nav class="bg-white border-b border-gray-200">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex h-16 justify-between">
                <div class="flex">
                    <a href="{{ route('home') }}" class="flex shrink-0 items-center gap-2 font-semibold text-gray-900">
                        <x-app-logo icon-class="w-7 h-7 text-indigo-600" img-class="h-8 w-auto max-w-[180px]" />
                        <span class="hidden sm:inline">{{ config('app.name') }}</span>
                    </a>
                    <div class="hidden sm:ml-8 sm:flex sm:space-x-6">
                        @php($navLink = fn($route, $label) => ['route' => $route, 'label' => $label])
                        @foreach ([
                            ['route' => 'home', 'label' => 'Home'],
                            ['route' => 'bookings.mine', 'label' => 'My Bookings'],
                            ['route' => 'bookings.index', 'label' => 'All Bookings'],
                        ] as $link)
                            <a href="{{ route($link['route']) }}"
                               class="inline-flex items-center border-b-2 px-1 pt-1 text-sm font-medium {{ request()->routeIs($link['route']) ? 'border-indigo-600 text-gray-900' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' }}">
                                {{ $link['label'] }}
                            </a>
                        @endforeach
                        @can('operate-bookings')
                            <a href="{{ route('it.dashboard') }}"
                               class="inline-flex items-center border-b-2 px-1 pt-1 text-sm font-medium {{ request()->routeIs('it.*') ? 'border-indigo-600 text-gray-900' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' }}">
                                IT Operations
                            </a>
                        @endcan
                        @canany(['manage-catalog', 'manage-users', 'manage-settings', 'view-audit-log'])
                            <a href="{{ route('admin.resource-pools.index') }}"
                               class="inline-flex items-center border-b-2 px-1 pt-1 text-sm font-medium {{ request()->routeIs('admin.*') ? 'border-indigo-600 text-gray-900' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' }}">
                                Administration
                            </a>
                        @endcanany
                    </div>
                </div>
                <div class="hidden sm:ml-6 sm:flex sm:items-center sm:gap-4">
                    <a href="{{ route('profile') }}" class="text-sm font-medium {{ request()->routeIs('profile') ? 'text-gray-900' : 'text-gray-500 hover:text-gray-800' }}">{{ auth()->user()->name }}</a>
                    <form method="POST" action="{{ route('auth.logout') }}">
                        @csrf
                        <button type="submit" class="text-sm font-medium text-gray-500 hover:text-gray-800">Sign out</button>
                    </form>
                </div>
                <div class="flex items-center sm:hidden">
                    <button @click="mobileOpen = !mobileOpen" class="p-2 text-gray-500">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" /></svg>
                    </button>
                </div>
            </div>
        </div>
        <div x-show="mobileOpen" x-cloak class="sm:hidden border-t border-gray-200 px-4 py-3 space-y-1">
            <a href="{{ route('home') }}" class="block py-1 text-sm text-gray-700">Home</a>
            <a href="{{ route('bookings.mine') }}" class="block py-1 text-sm text-gray-700">My Bookings</a>
            <a href="{{ route('bookings.index') }}" class="block py-1 text-sm text-gray-700">All Bookings</a>
            @can('operate-bookings')
                <a href="{{ route('it.dashboard') }}" class="block py-1 text-sm text-gray-700">IT Operations</a>
            @endcan
            @canany(['manage-catalog', 'manage-users', 'manage-settings', 'view-audit-log'])
                <a href="{{ route('admin.resource-pools.index') }}" class="block py-1 text-sm text-gray-700">Administration</a>
            @endcanany
            <a href="{{ route('profile') }}" class="block py-1 text-sm text-gray-700">My Profile</a>
            <form method="POST" action="{{ route('auth.logout') }}" class="pt-2 border-t border-gray-100 mt-2">
                @csrf
                <button type="submit" class="block py-1 text-sm text-gray-500">Sign out ({{ auth()->user()->name }})</button>
            </form>
        </div>
    </nav>
    @endunless

    @if (session('success'))
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 mt-4">
            <div class="rounded-md bg-emerald-50 px-4 py-3 text-sm text-emerald-800 ring-1 ring-inset ring-emerald-600/20">{{ session('success') }}</div>
        </div>
    @endif
    @if (session('error'))
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 mt-4">
            <div class="rounded-md bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-inset ring-red-600/20">{{ session('error') }}</div>
        </div>
    @endif

    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        {{ $slot }}
    </main>

    @unless (session('embedded'))
        <footer class="mx-auto max-w-7xl px-4 pb-8 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-center justify-between gap-2 border-t border-gray-200 pt-4 text-xs text-gray-400">
                <span>{{ config('app.name') }} &middot; Kitloan v{{ config('version.app') }}</span>
                @if (app(\App\Settings\SettingsRepository::class)->get('show_developer_link', true))
                    <a href="https://github.com/nicksweb/kitloan-resource-booking" target="_blank" rel="noopener"
                       class="hover:text-gray-600 hover:underline">Built with Kitloan &mdash; source &amp; docs on GitHub</a>
                @endif
            </div>
        </footer>
    @endunless
</div>
@livewireScripts
</body>
</html>
