<div>
    <div class="flex items-center justify-between print:hidden">
        <h1 class="text-2xl font-semibold tracking-tight text-gray-900">Logistics — {{ \Illuminate\Support\Carbon::parse($date)->format('l, j F Y') }}</h1>
        <button onclick="window.print()" class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50">Print</button>
    </div>

    <div class="mt-4 flex flex-wrap items-end gap-3 rounded-xl border border-gray-200 bg-white p-4 print:hidden">
        <div>
            <label class="block text-xs font-medium text-gray-700">Day</label>
            <input type="date" wire:model.live="date" class="mt-1 rounded-md border-gray-300 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-700">Resource Pool</label>
            <select wire:model.live="resourcePoolId" class="mt-1 rounded-md border-gray-300 text-sm">
                <option value="">All</option>
                @foreach ($pools as $pool)<option value="{{ $pool->id }}">{{ $pool->name }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-700">Room</label>
            <select wire:model.live="locationId" class="mt-1 rounded-md border-gray-300 text-sm">
                <option value="">All</option>
                @foreach ($locations as $location)<option value="{{ $location->id }}">{{ $location->code }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-700">Exam Type</label>
            <select wire:model.live="bookingTypeId" class="mt-1 rounded-md border-gray-300 text-sm">
                <option value="">All</option>
                @foreach ($bookingTypes as $type)<option value="{{ $type->id }}">{{ $type->name }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-700">Approval</label>
            <select wire:model.live="approvalStatus" class="mt-1 rounded-md border-gray-300 text-sm">
                <option value="">All</option>
                <option value="approved">Approved</option>
                <option value="pending">Pending</option>
                <option value="rejected">Rejected</option>
            </select>
        </div>
    </div>

    <div class="mt-6 overflow-x-auto rounded-xl border border-gray-200 bg-white">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead>
                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                    <th class="px-4 py-3">Time</th>
                    <th class="px-4 py-3">Room</th>
                    <th class="px-4 py-3">Qty</th>
                    <th class="px-4 py-3">Type</th>
                    <th class="px-4 py-3">Assets</th>
                    <th class="px-4 py-3 print:hidden">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($bookings as $booking)
                    <tr>
                        <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-900">{{ $booking->start_at->format('H:i') }}</td>
                        <td class="px-4 py-3">{{ $booking->location?->code ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $booking->items->sum('quantity_requested') }}</td>
                        <td class="px-4 py-3">{{ $booking->bookingType?->name ?? $booking->resourcePool->name }}</td>
                        <td class="px-4 py-3">
                            @php($assets = $booking->items->flatMap->allocations->where('released_at', null)->pluck('resource.name'))
                            {{ $assets->isNotEmpty() ? $assets->join(', ') : 'TBA' }}
                        </td>
                        <td class="px-4 py-3 print:hidden"><x-status-badge :status="$booking->approval_status" /></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-6 text-center text-gray-500">Nothing scheduled.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
