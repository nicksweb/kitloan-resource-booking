<x-layouts.guest>
    @if (session('error'))
        <div class="mb-4 rounded-md bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-inset ring-red-600/20">
            {{ session('error') }}
        </div>
    @endif

    @php($embedded = session('embedded'))

    @if ($embedded && config('oidc.enabled') && ! session('silent_failed') && ! session('error'))
        {{-- Embedded and no Kitloan session yet: try to pick up an existing
             identity-provider session silently before showing a button. --}}
        <div x-data x-init="window.location.assign(@js(route('auth.silent')))"
             class="flex items-center justify-center gap-2 text-sm text-gray-500">
            <svg class="h-4 w-4 animate-spin text-gray-400" viewBox="0 0 24 24" fill="none">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
            </svg>
            Signing you in&hellip;
        </div>
        <noscript>
            <a href="{{ route('auth.redirect') }}" class="mt-4 flex w-full items-center justify-center gap-2 rounded-md bg-indigo-600 px-3.5 py-2.5 text-sm font-semibold text-white">Sign in with your school account</a>
        </noscript>
    @else
        <a href="{{ route('auth.redirect', request()->query('redirect') ? ['redirect' => request()->query('redirect')] : []) }}"
           class="flex w-full items-center justify-center gap-2 rounded-md bg-indigo-600 px-3.5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
            Sign in with your school account
        </a>
        <p class="mt-6 text-center text-xs text-gray-500">
            You'll be redirected to your organisation's sign-in page.
        </p>

        @if (config('auth.local_login.enabled'))
            <p class="mt-4 text-center text-xs">
                <a href="{{ route('auth.local.show') }}" class="text-gray-400 hover:text-gray-600">Administrator emergency sign-in</a>
            </p>
        @endif
    @endif
</x-layouts.guest>
