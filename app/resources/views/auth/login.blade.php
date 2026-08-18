<x-layouts.guest>
    @if (session('error'))
        <div class="mb-4 rounded-md bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-inset ring-red-600/20">
            {{ session('error') }}
        </div>
    @endif

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
</x-layouts.guest>
