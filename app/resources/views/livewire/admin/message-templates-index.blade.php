<div>
    <x-admin.nav />
    <div class="max-w-3xl">
    <h1 class="text-2xl font-semibold tracking-tight text-gray-900">Email Templates</h1>
    <p class="mt-1 text-sm text-gray-500">
        Edit the subject line and opening text of each notification email. The booking details table and the
        calendar attachment are always included. The <strong>shared policy notice</strong> is appended to every
        requestor email — a good place for "return equipment to IT" instructions.
    </p>

    <div class="mt-4 rounded-lg bg-gray-50 p-3 text-xs text-gray-600">
        <span class="font-medium text-gray-700">Placeholders</span> (type them in the subject or text, they're filled in per booking):
        <span class="mt-1 flex flex-wrap gap-1">
            @foreach ($tokens as $token)
                <code class="rounded bg-white px-1.5 py-0.5 ring-1 ring-gray-200">&#123;&#123; {{ $token }} &#125;&#125;</code>
            @endforeach
        </span>
    </div>

    <div class="mt-6 space-y-3">
        @foreach ($rows as $row)
            <div class="rounded-xl border border-gray-200 bg-white">
                <div class="flex items-center justify-between px-5 py-3">
                    <div>
                        <p class="text-sm font-medium text-gray-900">{{ $row['label'] }}</p>
                        <p class="text-xs text-gray-400">
                            <code>{{ $row['key'] }}</code>
                            @unless ($row['model']?->enabled ?? true) &middot; <span class="text-amber-600">disabled</span> @endunless
                        </p>
                    </div>
                    <div class="flex items-center gap-3 text-sm">
                        <button wire:click="resetToDefault('{{ $row['key'] }}')" wire:confirm="Restore this template's default wording?" class="text-xs text-gray-400 hover:underline">Reset</button>
                        <button wire:click="edit('{{ $row['key'] }}')" class="text-indigo-600 hover:underline">Edit</button>
                    </div>
                </div>

                @if ($editingKey === $row['key'])
                    <div class="border-t border-gray-100 px-5 py-4 space-y-3">
                        @if ($row['has_subject'])
                            <div>
                                <label class="block text-xs font-medium text-gray-700">Subject</label>
                                <input type="text" wire:model="subject" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                                @error('subject')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>
                        @endif
                        <div>
                            <label class="block text-xs font-medium text-gray-700">{{ $row['key'] === 'booking.policy_notice' ? 'Notice text' : 'Opening text' }}</label>
                            <textarea wire:model="intro" rows="3" class="mt-1 block w-full rounded-md border-gray-300 text-sm"></textarea>
                            <p class="mt-1 text-xs text-gray-400">Markdown is supported. Leave blank to fall back to the built-in wording.</p>
                            @error('intro')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" wire:model="enabled" class="rounded border-gray-300 text-indigo-600">
                            {{ $row['key'] === 'booking.policy_notice' ? 'Include this notice' : 'Use this template' }}
                        </label>
                        <div class="flex justify-end gap-2">
                            <button wire:click="$set('editingKey', null)" class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300">Cancel</button>
                            <button wire:click="save" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white">Save</button>
                        </div>
                    </div>
                @endif
            </div>
        @endforeach
    </div>
    </div>
</div>
