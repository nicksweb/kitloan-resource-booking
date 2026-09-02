<div class="max-w-3xl">
    <x-admin.nav />
    <h1 class="text-2xl font-semibold tracking-tight text-gray-900">Settings</h1>

    <div class="mt-6 space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-gray-900">About this instance</h2>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex justify-between">
                    <dt class="text-gray-500">Kitloan version</dt>
                    <dd class="font-medium text-gray-900">v{{ $codeVersion }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">Last completed upgrade</dt>
                    <dd class="font-medium text-gray-900">
                        @if ($installedVersion)
                            v{{ $installedVersion }}
                            @if ($installedVersion !== $codeVersion)
                                <span class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-xs text-amber-800">run <code>kitloan:upgrade</code></span>
                            @endif
                        @else
                            <span class="text-gray-400">not recorded yet</span>
                        @endif
                    </dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">Release notes</dt>
                    <dd><a href="https://github.com/nicksweb/kitloan-resource-booking/blob/main/CHANGELOG.md" target="_blank" rel="noopener" class="text-indigo-600 hover:underline">CHANGELOG</a></dd>
                </div>
            </dl>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-gray-900">General</h2>
            <div class="mt-3 space-y-3">
                <div><label class="block text-xs font-medium text-gray-700">Site Name</label><input type="text" wire:model="siteName" class="mt-1 block w-full rounded-md border-gray-300 text-sm"></div>
                <div><label class="block text-xs font-medium text-gray-700">Timezone</label><input type="text" wire:model="timezone" class="mt-1 block w-full rounded-md border-gray-300 text-sm">@error('timezone')<p class="text-xs text-red-600">{{ $message }}</p>@enderror</div>
                <div>
                    <label class="block text-xs font-medium text-gray-700">Site Logo</label>
                    @if ($currentLogoPath)
                        <div class="mt-2 flex items-center gap-3">
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($currentLogoPath) }}" alt="Current logo" class="h-8 rounded bg-gray-50 p-1 ring-1 ring-gray-200">
                            <button type="button" wire:click="removeLogo" wire:confirm="Remove the logo and go back to the default mark?" class="text-xs text-red-600 hover:underline">Remove logo</button>
                        </div>
                    @endif
                    <input type="file" wire:model="logo" accept="image/png,image/jpeg,image/svg+xml" class="mt-1 block w-full text-sm">
                    <div wire:loading wire:target="logo" class="text-xs text-gray-500">Uploading&hellip;</div>
                    <p class="mt-1 text-xs text-gray-400">PNG or SVG, roughly 240&times;64 px (shown ~32 px tall), max 2&nbsp;MB. With no logo set, the built-in mark is used.</p>
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

        <div class="rounded-xl border border-gray-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-gray-900">Local (Break-Glass) Login</h2>
            <p class="mt-1 text-xs text-gray-500">Emergency fallback sign-in for administrators if SSO is unavailable. Manage which admins have a local password under Administration &rarr; Users.</p>

            @if (! config('auth.local_login.enabled'))
                <p class="mt-3 rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-800">This deployment has local login switched off at the infrastructure level (<code>LOCAL_LOGIN_ENABLED</code> is not set in <code>.env</code>). The toggle below has no effect until that's turned on and the app is redeployed.</p>
            @endif

            <label class="mt-3 flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" wire:model="localLoginEnabled" class="rounded border-gray-300 text-indigo-600">
                Local login currently available
            </label>
            <p class="mt-1 text-xs text-gray-400">Turn this off day-to-day (e.g. once SSO is confirmed healthy again) without needing server access. Turning it back on does not require a redeploy.</p>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-gray-900">Embedding</h2>
            <p class="mt-1 text-xs text-gray-500">
                Allow named sites (an intranet, a portal) to show Kitloan inside an <code>&lt;iframe&gt;</code>.
                When on, an embedded page also attempts a silent SSO sign-in before showing a login button, so a
                visitor already signed in to your identity provider elsewhere lands straight on their bookings.
            </p>

            <label class="mt-3 flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" wire:model="embeddingEnabled" class="rounded border-gray-300 text-indigo-600">
                Allow embedding on the sites listed below
            </label>

            <div class="mt-3">
                <label class="block text-xs font-medium text-gray-700">Allowed parent origins</label>
                <textarea wire:model="embeddingAllowedOrigins" rows="3" placeholder="https://intranet.example.edu&#10;https://portal.example.edu"
                          class="mt-1 block w-full rounded-md border-gray-300 text-sm font-mono"></textarea>
                <p class="mt-1 text-xs text-gray-400">One origin per line (scheme + host, no path). Leave the toggle off to forbid all embedding.</p>
            </div>

            <div class="mt-3 rounded-md bg-gray-50 p-3 text-xs text-gray-600">
                Embed snippet:
                <code class="block mt-1 break-all">&lt;iframe src="{{ rtrim(config('app.url'), '/') }}/?embed=1" style="width:100%;height:800px;border:0"&gt;&lt;/iframe&gt;</code>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-gray-900">Housekeeping</h2>
            <div class="mt-3">
                <label class="block text-xs font-medium text-gray-700">Audit-log retention (months)</label>
                <input type="number" min="0" max="120" wire:model="auditRetentionMonths" class="mt-1 block w-24 rounded-md border-gray-300 text-sm">
                @error('auditRetentionMonths')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                <p class="mt-1 text-xs text-gray-400">Entries older than this are deleted nightly. <strong>0</strong> keeps everything. You can also purge on demand from Administration&nbsp;&rarr;&nbsp;Audit&nbsp;Log.</p>
            </div>
        </div>

        <button wire:click="save" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Save Settings</button>

        <div class="rounded-xl border border-gray-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-gray-900">Configuration export / import</h2>
            <p class="mt-1 text-xs text-gray-500">
                Move non-secret configuration between instances as a JSON bundle. Secrets (SSO / SMTP / Snipe-IT
                credentials) live in the environment and are never included. Import is upsert-only &mdash; it never deletes anything.
            </p>
            <div class="mt-3 flex flex-wrap gap-2">
                <button wire:click="exportSettings" class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50">Export settings</button>
                <button wire:click="exportFullConfig" class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50">Export full configuration</button>
                <button wire:click="openConfigImport" class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50">Import&hellip;</button>
            </div>
        </div>
    </div>

    @if ($showConfigImport)
        <div class="fixed inset-0 z-10 flex items-center justify-center bg-gray-900/50 p-4">
            <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl max-h-[90vh] overflow-y-auto">
                <h2 class="text-lg font-semibold text-gray-900">Import configuration</h2>
                <p class="mt-1 text-xs text-gray-500">Upload a bundle produced by one of the Export buttons. Choose which sections to apply.</p>

                <div class="mt-4">
                    <input type="file" wire:model="configImportFile" accept=".json,application/json" class="block w-full text-sm">
                    <div wire:loading wire:target="configImportFile" class="mt-1 text-xs text-gray-500">Uploading&hellip;</div>
                    @error('configImportFile') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="mt-4 grid grid-cols-2 gap-2">
                    @foreach (\App\Services\Config\ConfigTransferService::SECTIONS as $section)
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" value="{{ $section }}" wire:model="configImportSections" class="rounded border-gray-300 text-indigo-600">
                            {{ ucfirst(str_replace('_', ' ', $section)) }}
                        </label>
                    @endforeach
                </div>
                @error('configImportSections') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror

                @if ($configImportResults && ($configImportResults['ok'] ?? false))
                    <div class="mt-4 rounded-md bg-gray-50 p-3 text-xs">
                        @foreach ($configImportResults['sections'] as $name => $r)
                            <p class="font-medium text-gray-700">{{ ucfirst(str_replace('_', ' ', $name)) }}: {{ $r['created'] }} created, {{ $r['updated'] }} updated, {{ count($r['skipped']) }} skipped.</p>
                            @if (!empty($r['skipped']))
                                <ul class="mb-2 mt-0.5 space-y-0.5 text-amber-700">
                                    @foreach ($r['skipped'] as $reason)<li>{{ $reason }}</li>@endforeach
                                </ul>
                            @endif
                        @endforeach
                    </div>
                @endif

                <div class="mt-6 flex justify-end gap-2">
                    <button wire:click="$set('showConfigImport', false)" class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300">Close</button>
                    <button wire:click="importConfig" wire:loading.attr="disabled" wire:target="importConfig" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white disabled:opacity-60">
                        <span wire:loading.remove wire:target="importConfig">Import</span>
                        <span wire:loading wire:target="importConfig">Importing&hellip;</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
