<x-layouts.guest>
    <div class="mb-4 rounded-md {{ $required ? 'bg-amber-50 text-amber-800 ring-amber-600/20' : 'bg-gray-50 text-gray-700 ring-gray-600/20' }} px-4 py-3 text-sm ring-1 ring-inset">
        @if ($required)
            Your account signs in with a local password and has an administrator role, so it must be protected
            by two-factor authentication. Set it up now to continue.
        @else
            Add two-factor authentication to your account.
        @endif
    </div>

    @if ($errors->any())
        <div class="mb-4 rounded-md bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-inset ring-red-600/20">
            {{ $errors->first() }}
        </div>
    @endif

    <ol class="space-y-4 text-sm text-gray-700">
        <li>
            <p class="font-medium">1. Scan this QR code with your authenticator app</p>
            <div class="mt-2 flex justify-center rounded-md border border-gray-200 bg-white p-3">
                <div class="h-48 w-48">{!! $qrSvg !!}</div>
            </div>
            <p class="mt-2 text-xs text-gray-500">
                Can't scan? Enter this key manually:
                <code class="rounded bg-gray-100 px-1 break-all">{{ $secret }}</code>
            </p>
        </li>
        <li>
            <p class="font-medium">2. Save your recovery codes</p>
            <p class="text-xs text-gray-500">Each can be used once if you lose your device. Store them somewhere safe — they won't be shown again.</p>
            <div class="mt-2 grid grid-cols-2 gap-1 rounded-md bg-gray-50 p-3 font-mono text-xs">
                @foreach ($recoveryCodes as $rc)
                    <span>{{ $rc }}</span>
                @endforeach
            </div>
        </li>
        <li>
            <p class="font-medium">3. Enter a code from the app to confirm</p>
            <form method="POST" action="{{ route('two-factor.setup.confirm') }}" class="mt-2 space-y-3">
                @csrf
                <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" required
                       placeholder="123456"
                       class="block w-full rounded-md border-gray-300 shadow-sm text-sm tracking-widest focus:border-indigo-500 focus:ring-indigo-500">
                <button type="submit"
                        class="flex w-full items-center justify-center gap-2 rounded-md bg-indigo-600 px-3.5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                    Confirm and turn on
                </button>
            </form>
        </li>
    </ol>

    <form method="POST" action="{{ route('auth.logout') }}" class="mt-6 text-center">
        @csrf
        <button type="submit" class="text-xs text-gray-400 hover:text-gray-600">Sign out</button>
    </form>
</x-layouts.guest>
