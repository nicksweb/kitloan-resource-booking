<x-layouts.guest>
    <div class="mb-4 text-sm text-gray-600">
        Enter the 6-digit code from your authenticator app to finish signing in.
    </div>

    @if ($errors->any())
        <div class="mb-4 rounded-md bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-inset ring-red-600/20">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('two-factor.challenge.verify') }}" class="space-y-4">
        @csrf
        <div>
            <label class="block text-sm font-medium text-gray-700">Authentication code</label>
            <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" required autofocus
                   placeholder="123456"
                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm tracking-widest focus:border-indigo-500 focus:ring-indigo-500">
            <p class="mt-1 text-xs text-gray-400">Lost your device? Enter one of your recovery codes instead.</p>
        </div>
        <button type="submit"
                class="flex w-full items-center justify-center gap-2 rounded-md bg-gray-800 px-3.5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-gray-700">
            Verify
        </button>
    </form>

    <p class="mt-6 text-center text-xs">
        <a href="{{ route('auth.login') }}" class="text-gray-400 hover:text-gray-600">Cancel</a>
    </p>
</x-layouts.guest>
