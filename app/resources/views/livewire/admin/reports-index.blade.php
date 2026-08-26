<div>
    <x-admin.nav />
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold tracking-tight text-gray-900">Reports</h1>
        <button wire:click="export" class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50">Export CSV</button>
    </div>

    <div class="mt-4 flex flex-wrap items-end gap-3 rounded-xl border border-gray-200 bg-white p-4">
        <div>
            <label class="block text-xs font-medium text-gray-700">From</label>
            <input type="date" wire:model.live="from" class="mt-1 rounded-md border-gray-300 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-700">To</label>
            <input type="date" wire:model.live="to" class="mt-1 rounded-md border-gray-300 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-700">Resource pool</label>
            <select wire:model.live="poolId" class="mt-1 rounded-md border-gray-300 text-sm">
                <option value="">All pools</option>
                @foreach ($pools as $pool)
                    <option value="{{ $pool->id }}">{{ $pool->name }}</option>
                @endforeach
            </select>
        </div>
        <label class="flex items-center gap-2 pb-2 text-sm text-gray-700">
            <input type="checkbox" wire:model.live="withCancelled" class="rounded border-gray-300 text-indigo-600">
            Include cancelled
        </label>
    </div>

    <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <p class="text-xs text-gray-500">Bookings</p>
            <p class="mt-1 text-2xl font-semibold text-gray-900">{{ $total }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <p class="text-xs text-gray-500">Units requested</p>
            <p class="mt-1 text-2xl font-semibold text-gray-900">{{ $totalUnits }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <p class="text-xs text-gray-500">Auto / manual / rejected</p>
            <p class="mt-1 text-lg font-semibold text-gray-900">{{ $approval['auto'] }} / {{ $approval['manual'] }} / {{ $approval['rejected'] }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <p class="text-xs text-gray-500">Avg. hrs to approval</p>
            <p class="mt-1 text-2xl font-semibold text-gray-900">{{ $approval['avg_hours_to_approval'] ?? '—' }}</p>
        </div>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="rounded-xl border border-gray-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-gray-900">Volume by month</h2>
            <table class="mt-3 w-full text-sm">
                <thead><tr class="text-left text-xs uppercase text-gray-400"><th class="py-1">Month</th><th class="py-1 text-right">Bookings</th><th class="py-1 text-right">Units</th></tr></thead>
                <tbody>
                    @forelse ($volume as $month => $v)
                        <tr class="border-t border-gray-100"><td class="py-1">{{ \Illuminate\Support\Carbon::parse($month.'-01')->format('M Y') }}</td><td class="py-1 text-right">{{ $v['count'] }}</td><td class="py-1 text-right">{{ $v['units'] }}</td></tr>
                    @empty
                        <tr><td colspan="3" class="py-3 text-gray-400">No bookings in this range.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-gray-900">Utilisation by pool</h2>
            <table class="mt-3 w-full text-sm">
                <thead><tr class="text-left text-xs uppercase text-gray-400"><th class="py-1">Pool</th><th class="py-1 text-right">Resource-days</th><th class="py-1 text-right">Capacity-days</th><th class="py-1 text-right">%</th></tr></thead>
                <tbody>
                    @forelse ($utilisation as $u)
                        <tr class="border-t border-gray-100">
                            <td class="py-1">{{ $u['pool'] }}</td>
                            <td class="py-1 text-right">{{ $u['resource_days'] }}</td>
                            <td class="py-1 text-right">{{ $u['capacity_days'] }}</td>
                            <td class="py-1 text-right font-medium">{{ $u['utilisation'] !== null ? $u['utilisation'].'%' : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-3 text-gray-400">No data.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <p class="mt-2 text-xs text-gray-400">Resource-days = units &times; days booked. Capacity-days = pool size &times; weekdays in range.</p>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-gray-900">Busiest days</h2>
            <table class="mt-3 w-full text-sm">
                <tbody>
                    @forelse ($busiestDays as $d)
                        <tr class="border-t border-gray-100"><td class="py-1">{{ \Illuminate\Support\Carbon::parse($d['date'])->format('D j M Y') }}</td><td class="py-1 text-right font-medium">{{ $d['units'] }} units</td></tr>
                    @empty
                        <tr><td class="py-3 text-gray-400">No data.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-gray-900">Top requestors</h2>
            <table class="mt-3 w-full text-sm">
                <tbody>
                    @forelse ($topRequestors as $r)
                        <tr class="border-t border-gray-100"><td class="py-1">{{ $r['label'] }}</td><td class="py-1 text-right">{{ $r['count'] }} bookings &middot; {{ $r['units'] }} units</td></tr>
                    @empty
                        <tr><td class="py-3 text-gray-400">No data.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-gray-900">Top rooms</h2>
            <table class="mt-3 w-full text-sm">
                <tbody>
                    @forelse ($topRooms as $r)
                        <tr class="border-t border-gray-100"><td class="py-1">{{ $r['label'] }}</td><td class="py-1 text-right">{{ $r['count'] }} bookings</td></tr>
                    @empty
                        <tr><td class="py-3 text-gray-400">No data.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-gray-900">Approvals</h2>
            <dl class="mt-3 space-y-1 text-sm">
                <div class="flex justify-between"><dt class="text-gray-500">Auto-approved</dt><dd>{{ $approval['auto'] }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Manually approved</dt><dd>{{ $approval['manual'] }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Rejected</dt><dd>{{ $approval['rejected'] }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Still pending</dt><dd>{{ $approval['pending'] }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Rejection rate</dt><dd>{{ $approval['rejection_rate'] !== null ? $approval['rejection_rate'].'%' : '—' }}</dd></div>
            </dl>
        </div>
    </div>
</div>
