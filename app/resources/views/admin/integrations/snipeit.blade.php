<x-layouts.app :title="'Snipe-IT Integration'">
    <x-admin.nav />
    <h1 class="text-2xl font-semibold tracking-tight text-gray-900">Snipe-IT Integration</h1>

    @unless ($enabled)
        <div class="mt-6 max-w-xl rounded-xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">
            <p class="font-semibold">Not configured on this instance.</p>
            <p class="mt-1">Snipe-IT credentials are a secret, so they live in the environment, not the database. Add these to <code>.env</code> and redeploy:</p>
            <pre class="mt-2 overflow-x-auto rounded bg-amber-100/70 p-3 text-xs">SNIPEIT_ENABLED=true
SNIPEIT_URL=https://snipeit.example.edu
SNIPEIT_API_TOKEN=your-api-token</pre>
            <p class="mt-2">Until then the "Import from Snipe-IT" button on a resource pool is shown but disabled.</p>
        </div>
    @endunless

    <div class="mt-6 max-w-xl rounded-xl border border-gray-200 bg-white p-6">
        <dl class="space-y-3 text-sm">
            <div class="flex justify-between"><dt class="text-gray-500">Status</dt><dd><x-status-badge :status="$enabled ? 'available' : 'disabled'">{{ $enabled ? 'Enabled' : 'Disabled' }}</x-status-badge></dd></div>
            <div class="flex justify-between"><dt class="text-gray-500">Server</dt><dd class="font-medium text-gray-900">{{ $url ?: '—' }}</dd></div>
            <div class="flex justify-between"><dt class="text-gray-500">Linked assets</dt><dd class="font-medium text-gray-900">{{ $linkedCount }}</dd></div>
            <div class="flex justify-between">
                <dt class="text-gray-500">Last successful sync</dt>
                <dd class="font-medium text-gray-900">
                    @if ($lastLog)
                        {{ $lastLog->finished_at?->format('j F Y H:i') ?? 'running…' }}
                        <x-status-badge :status="$lastLog->status === 'success' ? 'available' : ($lastLog->status === 'failed' ? 'failed' : 'pending')">{{ ucfirst($lastLog->status) }}</x-status-badge>
                    @else
                        Never
                    @endif
                </dd>
            </div>
        </dl>

        <div class="mt-6 flex gap-2">
            <form method="POST" action="{{ route('admin.integrations.snipeit.test') }}">
                @csrf
                <button class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50">Test Connection</button>
            </form>
            <form method="POST" action="{{ route('admin.integrations.snipeit.sync') }}">
                @csrf
                <button class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Synchronise Now</button>
            </form>
            <a href="{{ route('admin.resource-pools.index') }}" class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50">Import Assets &rarr;</a>
        </div>
    </div>
</x-layouts.app>
