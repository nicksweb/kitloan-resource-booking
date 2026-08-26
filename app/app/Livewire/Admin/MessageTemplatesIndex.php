<?php

namespace App\Livewire\Admin;

use App\Models\MessageTemplate;
use App\Services\Notifications\TemplateRenderer;
use Database\Seeders\MessageTemplateSeeder;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class MessageTemplatesIndex extends Component
{
    public ?string $editingKey = null;

    public string $subject = '';

    public string $intro = '';

    public bool $enabled = true;

    public function render()
    {
        $defaults = MessageTemplateSeeder::defaults();

        $templates = MessageTemplate::all()->keyBy('key');

        // Present in the seeder's declared order, with its human label.
        $rows = collect($defaults)->map(fn ($meta, $key) => [
            'key' => $key,
            'label' => $meta['label'],
            'audience' => $meta['audience'],
            'model' => $templates->get($key),
            'has_subject' => ($meta['subject'] ?? null) !== null,
        ])->values();

        return view('livewire.admin.message-templates-index', [
            'rows' => $rows,
            'tokens' => app(TemplateRenderer::class)->availableTokens(),
        ]);
    }

    public function edit(string $key): void
    {
        $template = MessageTemplate::where('key', $key)->firstOrFail();
        $this->editingKey = $key;
        $this->subject = (string) $template->subject;
        $this->intro = (string) $template->intro;
        $this->enabled = $template->enabled;
    }

    public function save(): void
    {
        $this->validate([
            'subject' => ['nullable', 'string', 'max:255'],
            'intro' => ['nullable', 'string', 'max:5000'],
        ]);

        MessageTemplate::where('key', $this->editingKey)->firstOrFail()->update([
            'subject' => $this->subject ?: null,
            'intro' => $this->intro ?: null,
            'enabled' => $this->enabled,
            'updated_by_user_id' => auth()->id(),
        ]);

        $this->editingKey = null;
        session()->flash('success', 'Email template saved.');
    }

    public function resetToDefault(string $key): void
    {
        $default = MessageTemplateSeeder::defaults()[$key] ?? null;
        abort_if($default === null, 404);

        MessageTemplate::where('key', $key)->firstOrFail()->update([
            'subject' => $default['subject'] ?? null,
            'intro' => $default['intro'] ?? null,
            'enabled' => $default['enabled'] ?? true,
            'updated_by_user_id' => auth()->id(),
        ]);

        if ($this->editingKey === $key) {
            $this->edit($key);
        }

        session()->flash('success', 'Template restored to its default wording.');
    }
}
