<x-layouts.app :title="'Snipe-IT Integration'">
    <x-admin.nav />
    <h1 class="text-2xl font-semibold tracking-tight text-gray-900">Snipe-IT Integration</h1>

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
