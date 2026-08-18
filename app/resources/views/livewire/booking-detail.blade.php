<div x-data="{ rejecting: false }">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-sm font-medium text-indigo-600">{{ $booking->reference }}</p>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight text-gray-900">{{ $booking->resourcePool->name }}</h1>
        </div>
        <div class="flex gap-2">
            <x-status-badge :status="$booking->lifecycle_status === 'cancelled' ? 'cancelled' : $booking->approval_status" />
            @if ($booking->auto_approved)
                <x-status-badge status="info">Auto-approved</x-status-badge>
            @endif
        </div>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-6">
            <div class="rounded-xl border border-gray-200 bg-white p-5">
                <dl class="grid grid-cols-2 gap-4 text-sm sm:grid-cols-3">
                    <div><dt class="text-gray-500">Date</dt><dd class="font-medium text-gray-900">{{ $booking->start_at->format('D j M Y') }}</dd></div>
                    <div><dt class="text-gray-500">Time</dt><dd class="font-medium text-gray-900">{{ $booking->start_at->format('g:i A') }} &ndash; {{ $booking->end_at->format('g:i A') }}</dd></div>
                    <div><dt class="text-gray-500">Room</dt><dd class="font-medium text-gray-900">{{ $booking->location?->name ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">Exam Type</dt><dd class="font-medium text-gray-900">{{ $booking->bookingType?->name ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">Booked by</dt><dd class="font-medium text-gray-900">{{ $booking->bookedBy->name }}</dd></div>
                    @if ($booking->createdBy->id !== $booking->bookedBy->id)
                        <div><dt class="text-gray-500">Created by</dt><dd class="font-medium text-gray-900">{{ $booking->createdBy->name }}</dd></div>
                    @endif
                    <div>
                        <dt class="text-gray-500">Submitted</dt>
                        <dd class="font-medium text-gray-900" title="{{ $booking->created_at->format('D j M Y, g:i A T') }}">{{ $booking->created_at->format('j M Y, g:i A') }}</dd>
                    </div>
                    @if ($booking->approved_at)
                        <div>
                            <dt class="text-gray-500">Approved</dt>
                            <dd class="font-medium text-gray-900">{{ $booking->approved_at->format('j M Y, g:i A') }}{{ $booking->auto_approved ? ' (auto)' : ($booking->approvedBy ? ' by '.$booking->approvedBy->name : '') }}</dd>
                        </div>
                    @endif
                    @if ($booking->rejected_at)
                        <div>
                            <dt class="text-gray-500">Rejected</dt>
                            <dd class="font-medium text-gray-900">{{ $booking->rejected_at->format('j M Y, g:i A') }}{{ $booking->rejectedBy ? ' by '.$booking->rejectedBy->name : '' }}</dd>
                        </div>
                    @endif
                    @if ($booking->cancelled_at)
                        <div>
                            <dt class="text-gray-500">Cancelled</dt>
                            <dd class="font-medium text-gray-900">{{ $booking->cancelled_at->format('j M Y, g:i A') }}{{ $booking->cancelledBy ? ' by '.$booking->cancelledBy->name : '' }}</dd>
                        </div>
                    @endif
                </dl>

                @if ($booking->students->isNotEmpty())
                    <div class="mt-4 border-t border-gray-100 pt-4">
                        <dt class="text-sm text-gray-500">Students</dt>
                        <dd class="mt-1 text-sm font-medium text-gray-900">{{ $booking->students->pluck('student_name')->join(', ') }}</dd>
                    </div>
                @endif

                @if ($booking->notes)
                    <div class="mt-4 border-t border-gray-100 pt-4">
                        <dt class="text-sm text-gray-500">Notes</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $booking->notes }}</dd>
                    </div>
                @endif

                @if ($booking->lifecycle_status === 'cancelled' && $booking->rejection_reason)
                    <div class="mt-4 rounded-md bg-red-50 px-3 py-2 text-sm text-red-800 ring-1 ring-inset ring-red-600/20">
                        <strong>Declined:</strong> {{ $booking->rejection_reason }}
                    </div>
                @endif
            </div>

            @foreach ($booking->items as $item)
                <div class="rounded-xl border border-gray-200 bg-white p-5">
                    <div class="flex items-center justify-between">
                        <h2 class="text-sm font-semibold text-gray-900">{{ $item->quantity_requested }} &times; {{ $item->resourcePool->name }}</h2>
                    </div>

                    @if ($item->resourcePool->isQuantityTracked())
                        <p class="mt-2 text-sm text-gray-500">Quantity reservation — no individual items tracked.</p>
                    @else
                        <ul class="mt-3 divide-y divide-gray-100">
                            @foreach ($item->allocations->where('released_at', null) as $allocation)
                                <li class="flex items-center justify-between py-2 text-sm">
                                    <div>
                                        <span class="font-medium text-gray-900">{{ $allocation->resource->name }}</span>
                                        @if ($link = $allocation->resource->externalAssetLink)
                                            <a href="{{ $link->snipeItAssetUrl() }}" target="_blank" rel="noopener" class="ml-2 text-xs text-indigo-600 hover:underline">View in Snipe-IT</a>
                                        @endif
                                    </div>
                                    @can('reallocate', $booking)
                                        @if ($booking->lifecycle_status === 'active')
                                            <button type="button" wire:click="startSubstitution({{ $allocation->id }})" class="text-xs font-medium text-indigo-600 hover:text-indigo-500">Substitute</button>
                                        @endif
                                    @endcan
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @if ($substitutingAllocationId && $item->allocations->pluck('id')->contains($substitutingAllocationId))
                        <div class="mt-3 rounded-md bg-gray-50 p-3">
                            <label class="block text-xs font-medium text-gray-700">Replace with</label>
                            <select wire:model="replacementResourceId" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                                <option value="">Select a replacement&hellip;</option>
                                @foreach ($this->substitutionOptions as $option)
                                    <option value="{{ $option->id }}">{{ $option->name }}</option>
                                @endforeach
                            </select>
                            <label class="mt-2 block text-xs font-medium text-gray-700">Reason</label>
                            <input type="text" wire:model="substitutionReason" placeholder="e.g. Battery failure" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                            @error('replacementResourceId') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            @error('substitutionReason') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            <div class="mt-2 flex gap-2">
                                <button type="button" wire:click="confirmSubstitution" class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white">Confirm</button>
                                <button type="button" wire:click="$set('substitutingAllocationId', null)" class="rounded-md bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 ring-1 ring-inset ring-gray-300">Cancel</button>
                            </div>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="space-y-4">
            <div class="rounded-xl border border-gray-200 bg-white p-5 space-y-2">
                @can('approve', $booking)
                    @if ($booking->approval_status === 'pending' && $booking->lifecycle_status === 'active')
                        <button type="button" wire:click="approve" class="w-full rounded-md bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Approve</button>
                        <button type="button" @click="rejecting = true" class="w-full rounded-md bg-white px-3 py-2 text-sm font-semibold text-red-600 ring-1 ring-inset ring-red-300 hover:bg-red-50">Reject</button>
                    @endif
                @endcan
                @can('update', $booking)
                    <a href="{{ route('bookings.edit', $booking) }}" class="block w-full rounded-md bg-white px-3 py-2 text-center text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50">Amend Booking</a>
                @endcan
                @can('cancel', $booking)
                    @if ($booking->isCancellable())
                        <button type="button" wire:click="cancel" wire:confirm="Cancel this booking?" class="w-full rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50">Cancel Booking</button>
                    @endif
                @endcan
            </div>

            @can('operate-bookings')
                <div class="rounded-xl border border-gray-200 bg-white p-5 space-y-2">
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500">Resend Notification</h3>
                    <button type="button" wire:click="resendToOwner" wire:loading.attr="disabled" wire:target="resendToOwner"
                            class="w-full rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 disabled:opacity-60">
                        <span wire:loading.remove wire:target="resendToOwner">Resend to {{ $booking->bookedBy->name }}</span>
                        <span wire:loading wire:target="resendToOwner">Sending&hellip;</span>
                    </button>
                    <button type="button" wire:click="resendToIt" wire:loading.attr="disabled" wire:target="resendToIt"
                            class="w-full rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 disabled:opacity-60">
                        <span wire:loading.remove wire:target="resendToIt">Resend to IT</span>
                        <span wire:loading wire:target="resendToIt">Sending&hellip;</span>
                    </button>
                    <p class="text-xs text-gray-500">Sends whatever the booking's current status is (approved bookings include the calendar invite).</p>
                </div>
            @endcan

            <div x-show="rejecting" x-cloak class="rounded-xl border border-red-200 bg-white p-5">
                <h3 class="text-sm font-semibold text-gray-900">Reject {{ $booking->reference }}</h3>
                <label class="mt-2 block text-xs font-medium text-gray-700">Reason (required)</label>
                <textarea x-ref="reason" rows="3" class="mt-1 block w-full rounded-md border-gray-300 text-sm"></textarea>
                @error('reason') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                <div class="mt-2 flex gap-2">
                    <button type="button" @click="$wire.reject($refs.reason.value)" class="rounded-md bg-red-600 px-3 py-1.5 text-xs font-semibold text-white">Reject Booking</button>
                    <button type="button" @click="rejecting = false" class="rounded-md bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 ring-1 ring-inset ring-gray-300">Cancel</button>
                </div>
            </div>
        </div>
    </div>
</div>
