<div class="max-w-2xl">
    <x-admin.nav />
    <h1 class="text-2xl font-semibold tracking-tight text-gray-900">Settings</h1>

    <div class="mt-6 space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-gray-900">General</h2>
            <div class="mt-3 space-y-3">
                <div><label class="block text-xs font-medium text-gray-700">Site Name</label><input type="text" wire:model="siteName" class="mt-1 block w-full rounded-md border-gray-300 text-sm"></div>
                <div><label class="block text-xs font-medium text-gray-700">Timezone</label><input type="text" wire:model="timezone" class="mt-1 block w-full rounded-md border-gray-300 text-sm">@error('timezone')<p class="text-xs text-red-600">{{ $message }}</p>@enderror</div>
                <div>
                    <label class="block text-xs font-medium text-gray-700">Site Logo</label>
                    @if ($currentLogoPath)
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($currentLogoPath) }}" alt="Current logo" class="mt-2 h-10">
                    @endif
                    <input type="file" wire:model="logo" accept="image/png,image/jpeg,image/svg+xml" class="mt-1 block w-full text-sm">
                    <div wire:loading wire:target="logo" class="text-xs text-gray-500">Uploading&hellip;</div>
                    @error('logo')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-gray-900">Booking</h2>
            <div class="mt-3 grid grid-cols-2 gap-3">
                <div><label class="block text-xs font-medium text-gray-700">Auto-approval lead time (hours)</label><input type="number" min="0" wire:model="minAutoApprovalLeadHours" class="mt-1 block w-full rounded-md border-gray-300 text-sm"></div>
                <div><label class="block text-xs font-medium text-gray-700">Reference Prefix</label><input type="text" wire:model="referencePrefix" class="mt-1 block w-full rounded-md border-gray-300 text-sm uppercase"></div>
                <div><label class="block text-xs font-medium text-gray-700">School Day Start</label><input type="time" wire:model="schoolDayStart" class="mt-1 block w-full rounded-md border-gray-300 text-sm"></div>
                <div><label class="block text-xs font-medium text-gray-700">School Day Finish</label><input type="time" wire:model="schoolDayFinish" class="mt-1 block w-full rounded-md border-gray-300 text-sm">@error('schoolDayFinish')<p class="text-xs text-red-600">{{ $message }}</p>@enderror</div>
            </div>
            <div class="mt-3 space-y-2">
                <label class="flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" wire:model="allowWeekends" class="rounded border-gray-300 text-indigo-600"> Weekends automatically permitted</label>
                <label class="flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" wire:model="weekendRequiresApproval" class="rounded border-gray-300 text-indigo-600"> Weekend bookings require approval</label>
                <label class="flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" wire:model="outOfHoursRequiresApproval" class="rounded border-gray-300 text-indigo-600"> Outside-hours bookings require approval</label>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-gray-900">Notifications</h2>
            <p class="mt-1 text-xs text-gray-500">Sender address and SMTP credentials are configured via environment variables, not here.</p>
            <div class="mt-3 space-y-3">
                <div><label class="block text-xs font-medium text-gray-700">IT Notification Address</label><input type="email" wire:model="itNotificationAddress" class="mt-1 block w-full rounded-md border-gray-300 text-sm">@error('itNotificationAddress')<p class="text-xs text-red-600">{{ $message }}</p>@enderror</div>
                <div><label class="block text-xs font-medium text-gray-700">Helpdesk / Reply-To Address</label><input type="email" wire:model="helpdeskReplyToAddress" class="mt-1 block w-full rounded-md border-gray-300 text-sm">@error('helpdeskReplyToAddress')<p class="text-xs text-red-600">{{ $message }}</p>@enderror</div>
            </div>

            <div class="mt-4 border-t border-gray-100 pt-4">
                <label class="block text-xs font-medium text-gray-700">Send Test Email</label>
                <div class="mt-1 flex gap-2">
                    <input type="email" wire:model="testEmailAddress" placeholder="you@example.com" class="block w-full rounded-md border-gray-300 text-sm">
                    <button type="button" wire:click="sendTestEmail" wire:loading.attr="disabled" wire:target="sendTestEmail"
                            class="shrink-0 rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 disabled:opacity-60">
                        <span wire:loading.remove wire:target="sendTestEmail">Send Test Email</span>
                        <span wire:loading wire:target="sendTestEmail">Sending&hellip;</span>
                    </button>
                </div>
                @error('testEmailAddress') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                <p class="mt-1 text-xs text-gray-500">Sent immediately (not queued), so a broken mail server shows its real error right here.</p>
            </div>
        </div>

        <button wire:click="save" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Save Settings</button>
    </div>
</div>
