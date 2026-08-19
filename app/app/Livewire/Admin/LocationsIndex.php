<?php

namespace App\Livewire\Admin;

use App\Models\Location;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
class LocationsIndex extends Component
{
    use WithFileUploads;

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $code = '';

    public string $name = '';

    public string $campus = '';

    public string $building = '';

    public string $description = '';

    public bool $enabled = true;

    public bool $showImport = false;

    public $csvFile = null;

    /** @var array{created: int, updated: int, skipped: array<int, string>}|null */
    public ?array $importResults = null;

    public function render()
    {
        return view('livewire.admin.locations-index', ['locations' => Location::ordered()->get()]);
    }

    public function create(): void
    {
        $this->reset(['editingId', 'code', 'name', 'campus', 'building', 'description']);
        $this->enabled = true;
        $this->showForm = true;
    }

    public function edit(Location $location): void
    {
        $this->editingId = $location->id;
        $this->code = $location->code;
        $this->name = $location->name;
        $this->campus = (string) $location->campus;
        $this->building = (string) $location->building;
        $this->description = (string) $location->description;
        $this->enabled = $location->enabled;
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('locations', 'code')->ignore($this->editingId)],
            'name' => ['required', 'string', 'max:255'],
            'campus' => ['nullable', 'string', 'max:255'],
            'building' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);
        $data['enabled'] = $this->enabled;

        if ($this->editingId) {
            Location::findOrFail($this->editingId)->update($data);
        } else {
            $data['display_order'] = Location::max('display_order') + 1;
            Location::create($data);
        }

        $this->showForm = false;
        session()->flash('success', 'Location saved.');
    }

    public function toggleEnabled(Location $location): void
    {
        $location->update(['enabled' => ! $location->enabled]);
    }

    public function openImport(): void
    {
        $this->reset(['csvFile', 'importResults']);
        $this->showImport = true;
    }

    /**
     * Expects a header row with (at least) `code` and `name` columns, plus
     * optional `campus`/`building`/`description` — matched case-insensitively
     * and order-independently. Upserts by code, so re-importing an updated
     * export is safe and idempotent. Invalid rows are skipped and reported,
     * not fatal to the whole import.
     */
    public function importCsv(): void
    {
        $this->validate(['csvFile' => ['required', 'file', 'mimes:csv,txt', 'max:2048']]);

        $handle = fopen($this->csvFile->getRealPath(), 'r');
        if (! $handle) {
            $this->addError('csvFile', 'Could not read the uploaded file.');

            return;
        }

        $header = fgetcsv($handle);
        if (! $header) {
            fclose($handle);
            $this->addError('csvFile', 'The file appears to be empty.');

            return;
        }

        $header = array_map(fn ($h) => strtolower(trim((string) $h)), $header);
        $codeIndex = array_search('code', $header, true);
        $nameIndex = array_search('name', $header, true);

        if ($codeIndex === false || $nameIndex === false) {
            fclose($handle);
            $this->addError('csvFile', 'The header row must include at least "code" and "name" columns.');

            return;
        }

        $campusIndex = array_search('campus', $header, true);
        $buildingIndex = array_search('building', $header, true);
        $descriptionIndex = array_search('description', $header, true);

        $created = 0;
        $updated = 0;
        $skipped = [];
        $rowNumber = 1;
        $nextOrder = Location::max('display_order') + 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;

            $code = trim((string) ($row[$codeIndex] ?? ''));
            $name = trim((string) ($row[$nameIndex] ?? ''));

            if ($code === '' || $name === '') {
                $skipped[] = "Row {$rowNumber}: missing code or name.";

                continue;
            }

            if (strlen($code) > 50) {
                $skipped[] = "Row {$rowNumber}: code \"{$code}\" is too long (max 50 characters).";

                continue;
            }

            $existed = Location::where('code', $code)->exists();

            Location::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'campus' => $campusIndex !== false ? (trim((string) ($row[$campusIndex] ?? '')) ?: null) : null,
                    'building' => $buildingIndex !== false ? (trim((string) ($row[$buildingIndex] ?? '')) ?: null) : null,
                    'description' => $descriptionIndex !== false ? (trim((string) ($row[$descriptionIndex] ?? '')) ?: null) : null,
                    'enabled' => true,
                    'display_order' => $existed ? Location::where('code', $code)->value('display_order') : $nextOrder++,
                ]
            );

            $existed ? $updated++ : $created++;
        }

        fclose($handle);

        $this->importResults = ['created' => $created, 'updated' => $updated, 'skipped' => $skipped];
        $this->csvFile = null;

        if ($created || $updated) {
            session()->flash('success', "Imported {$created} new and updated {$updated} existing location(s).");
        }
    }
}
