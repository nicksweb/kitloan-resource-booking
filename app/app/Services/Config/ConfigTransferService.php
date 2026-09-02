<?php

namespace App\Services\Config;

use App\Models\ApprovalRule;
use App\Models\BookingType;
use App\Models\Location;
use App\Models\MessageTemplate;
use App\Models\Resource;
use App\Models\ResourcePool;
use App\Models\SchedulePeriod;
use App\Models\Setting;
use App\Settings\SettingsRepository;
use Database\Seeders\MessageTemplateSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Exports and re-imports the non-secret, admin-editable configuration of a
 * Kitloan instance as a single JSON bundle: application settings plus the
 * catalog tables (locations, resource pools + their resources, booking types,
 * schedule periods, approval rules).
 *
 * Everything here is data an administrator can already see and change through
 * Administration → *. Secrets (OIDC / SMTP / Snipe-IT credentials, APP_KEY)
 * live in the environment, never in these tables, and are never touched.
 *
 * Import is upsert-by-natural-key and idempotent: re-importing the same bundle
 * is a no-op, and importing an older export never deletes rows that have since
 * been added. Nothing is ever deleted by an import.
 */
class ConfigTransferService
{
    /** Bundle format version — bump only on an incompatible shape change. */
    public const FORMAT_VERSION = 1;

    /** @var list<string> */
    public const SECTIONS = [
        'settings', 'locations', 'resource_pools', 'booking_types', 'schedule_periods', 'approval_rules', 'message_templates',
    ];

    public function __construct(private readonly SettingsRepository $settings) {}

    /**
     * Setting keys that may be written by an import. Anything else in the
     * bundle's "settings" block is reported as skipped rather than applied —
     * this is the guard against a hand-edited bundle poking arbitrary rows.
     * `installed_app_version` is deliberately excluded (the upgrade command
     * owns it).
     *
     * @return list<string>
     */
    public function importableSettingKeys(): array
    {
        return array_values(array_diff(
            array_merge(
                array_keys((array) config('booking.defaults')),
                ['site_logo_path', 'local_login_enabled', 'embedding_enabled', 'embedding_allowed_origins'],
            ),
            [config('version.stored_version_key')],
        ));
    }

    /**
     * @param  list<string>  $sections
     * @return array<string, mixed>
     */
    public function export(array $sections = self::SECTIONS): array
    {
        $sections = array_values(array_intersect($sections, self::SECTIONS));

        $bundle = [
            'kitloan' => [
                'format_version' => self::FORMAT_VERSION,
                'app_version' => config('version.app'),
                'exported_at' => now()->toIso8601String(),
                'sections' => $sections,
            ],
        ];

        foreach ($sections as $section) {
            $bundle[$section] = $this->{'export'.Str::studly($section)}();
        }

        return $bundle;
    }

    /**
     * @param  array<string, mixed>  $bundle
     * @param  list<string>  $sections
     * @return array{ok: bool, error?: string, sections: array<string, array{created: int, updated: int, skipped: list<string>}>}
     */
    public function import(array $bundle, array $sections = self::SECTIONS): array
    {
        if (! isset($bundle['kitloan']['format_version'])) {
            return ['ok' => false, 'error' => 'This file is not a Kitloan configuration export.', 'sections' => []];
        }

        if ((int) $bundle['kitloan']['format_version'] !== self::FORMAT_VERSION) {
            return [
                'ok' => false,
                'error' => "Unsupported export format (v{$bundle['kitloan']['format_version']}); this instance reads v".self::FORMAT_VERSION.'.',
                'sections' => [],
            ];
        }

        $sections = array_values(array_intersect($sections, self::SECTIONS));
        $report = [];

        foreach ($sections as $section) {
            if (! array_key_exists($section, $bundle)) {
                continue;
            }
            $report[$section] = $this->{'import'.Str::studly($section)}((array) $bundle[$section]);
        }

        $this->settings->forgetCache();

        return ['ok' => true, 'sections' => $report];
    }

    // ---- exporters -------------------------------------------------------

    /** @return array<string, array{value: ?string, type: string}> */
    private function exportSettings(): array
    {
        return Setting::query()
            ->whereIn('key', $this->importableSettingKeys())
            ->orderBy('key')
            ->get()
            ->mapWithKeys(fn (Setting $s) => [$s->key => ['value' => $s->value, 'type' => $s->type]])
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function exportLocations(): array
    {
        return Location::withTrashed()->orderBy('display_order')->get()
            ->map(fn (Location $l) => $l->only([
                'code', 'name', 'campus', 'building', 'description', 'enabled', 'display_order',
            ]) + ['deleted' => $l->trashed()])
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function exportResourcePools(): array
    {
        return ResourcePool::withTrashed()->with(['resources' => fn ($q) => $q->withTrashed()])
            ->orderBy('display_order')->get()
            ->map(fn (ResourcePool $p) => $p->only([
                'name', 'slug', 'description', 'enabled', 'icon', 'display_order',
                'allocation_mode', 'kind', 'approval_route', 'quantity_total', 'minimum_lead_time_minutes',
                'preparation_buffer_minutes', 'return_buffer_minutes', 'allow_weekends',
                'allow_out_of_hours', 'requires_room', 'allows_student', 'requires_student',
                'requires_booking_type', 'auto_approval_enabled', 'booking_reference_prefix',
            ]) + [
                'deleted' => $p->trashed(),
                // Staff-pool "resources" are people, derived on each instance from
                // users.bookable_as_officer — never exported as resource rows.
                'resources' => $p->isStaffPool() ? [] : $p->resources->map(fn (Resource $r) => $r->only([
                    'name', 'asset_number', 'serial', 'status', 'source', 'display_order', 'notes',
                ]) + ['deleted' => $r->trashed()])->all(),
            ])
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function exportBookingTypes(): array
    {
        return BookingType::withTrashed()->orderBy('display_order')->get()
            ->map(fn (BookingType $t) => $t->only([
                'name', 'description', 'enabled', 'instructions_for_user',
                'instructions_for_it', 'requires_approval', 'display_order',
            ]) + ['deleted' => $t->trashed()])
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function exportSchedulePeriods(): array
    {
        return SchedulePeriod::orderBy('group_name')->orderBy('display_order')->get()
            ->map(fn (SchedulePeriod $p) => [
                'group_name' => $p->group_name,
                'name' => $p->name,
                'start_time' => $p->start_time->format('H:i'),
                'end_time' => $p->end_time->format('H:i'),
                'enabled' => $p->enabled,
                'display_order' => $p->display_order,
            ])
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function exportApprovalRules(): array
    {
        return ApprovalRule::with('resourcePool')->orderBy('display_order')->get()
            ->map(fn (ApprovalRule $r) => [
                'name' => $r->name,
                'resource_pool_slug' => $r->resourcePool?->slug,
                'rule_type' => $r->rule_type,
                'threshold_value' => $r->threshold_value,
                'enabled' => $r->enabled,
                'display_order' => $r->display_order,
            ])
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function exportMessageTemplates(): array
    {
        return MessageTemplate::orderBy('key')->get()
            ->map(fn ($t) => [
                'key' => $t->key,
                'subject' => $t->subject,
                'intro' => $t->intro,
                'enabled' => $t->enabled,
            ])
            ->all();
    }

    // ---- importers -----------------------------------------------------

    /** @return array{created: int, updated: int, skipped: list<string>} */
    private function importSettings(array $rows): array
    {
        $allowed = $this->importableSettingKeys();
        $created = 0;
        $updated = 0;
        $skipped = [];

        foreach ($rows as $key => $row) {
            if (! in_array($key, $allowed, true)) {
                $skipped[] = "Unknown setting \"{$key}\" — ignored.";

                continue;
            }

            $existed = Setting::where('key', $key)->exists();
            Setting::updateOrCreate(['key' => $key], [
                'value' => is_array($row) ? ($row['value'] ?? null) : (string) $row,
                'type' => is_array($row) ? ($row['type'] ?? 'string') : 'string',
            ]);
            $existed ? $updated++ : $created++;
        }

        return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped];
    }

    /** @return array{created: int, updated: int, skipped: list<string>} */
    private function importLocations(array $rows): array
    {
        $created = 0;
        $updated = 0;
        $skipped = [];

        foreach ($rows as $i => $row) {
            $code = trim((string) ($row['code'] ?? ''));
            $name = trim((string) ($row['name'] ?? ''));
            if ($code === '' || $name === '') {
                $skipped[] = 'Location #'.($i + 1).': missing code or name.';

                continue;
            }

            $existing = Location::withTrashed()->where('code', $code)->first();
            $attributes = [
                'name' => $name,
                'campus' => $row['campus'] ?? null,
                'building' => $row['building'] ?? null,
                'description' => $row['description'] ?? null,
                'enabled' => (bool) ($row['enabled'] ?? true),
                'display_order' => (int) ($row['display_order'] ?? (Location::max('display_order') + 1)),
            ];

            if ($existing) {
                $existing->fill($attributes)->save();
                $this->applyTrash($existing, (bool) ($row['deleted'] ?? false));
                $updated++;
            } else {
                $location = Location::create(['code' => $code] + $attributes);
                $this->applyTrash($location, (bool) ($row['deleted'] ?? false));
                $created++;
            }
        }

        return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped];
    }

    /** @return array{created: int, updated: int, skipped: list<string>} */
    private function importResourcePools(array $rows): array
    {
        $created = 0;
        $updated = 0;
        $skipped = [];

        foreach ($rows as $i => $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                $skipped[] = 'Resource pool #'.($i + 1).': missing name.';

                continue;
            }

            $slug = trim((string) ($row['slug'] ?? '')) ?: Str::slug($name).'-'.Str::random(4);
            $existing = ResourcePool::withTrashed()
                ->where('slug', $slug)->orWhere('name', $name)->first();

            $attributes = collect($row)->only([
                'description', 'enabled', 'icon', 'display_order', 'allocation_mode', 'kind', 'approval_route',
                'quantity_total', 'minimum_lead_time_minutes', 'preparation_buffer_minutes',
                'return_buffer_minutes', 'allow_weekends', 'allow_out_of_hours', 'requires_room',
                'allows_student', 'requires_student', 'requires_booking_type',
                'auto_approval_enabled', 'booking_reference_prefix',
            ])->all();

            if ($existing) {
                $existing->fill($attributes + ['name' => $name])->save();
                $pool = $existing;
                $this->applyTrash($pool, (bool) ($row['deleted'] ?? false));
                $updated++;
            } else {
                $pool = ResourcePool::create($attributes + [
                    'name' => $name,
                    'slug' => $slug,
                    'display_order' => (int) ($row['display_order'] ?? (ResourcePool::max('display_order') + 1)),
                ]);
                $this->applyTrash($pool, (bool) ($row['deleted'] ?? false));
                $created++;
            }

            foreach ((array) ($row['resources'] ?? []) as $resourceRow) {
                $rName = trim((string) ($resourceRow['name'] ?? ''));
                if ($rName === '') {
                    $skipped[] = "Pool \"{$name}\": a resource row had no name.";

                    continue;
                }
                $assetNumber = trim((string) ($resourceRow['asset_number'] ?? ''));
                $match = $pool->resources()->withTrashed()
                    ->when($assetNumber !== '', fn ($q) => $q->where('asset_number', $assetNumber))
                    ->when($assetNumber === '', fn ($q) => $q->where('name', $rName))
                    ->first();

                $resourceAttrs = collect($resourceRow)->only([
                    'name', 'asset_number', 'serial', 'status', 'source', 'display_order', 'notes',
                ])->all();

                if ($match) {
                    $match->fill($resourceAttrs)->save();
                    $this->applyTrash($match, (bool) ($resourceRow['deleted'] ?? false));
                } else {
                    $new = $pool->resources()->create($resourceAttrs + ['source' => $resourceRow['source'] ?? 'manual']);
                    $this->applyTrash($new, (bool) ($resourceRow['deleted'] ?? false));
                }
            }
        }

        return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped];
    }

    /** @return array{created: int, updated: int, skipped: list<string>} */
    private function importBookingTypes(array $rows): array
    {
        $created = 0;
        $updated = 0;
        $skipped = [];

        foreach ($rows as $i => $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                $skipped[] = 'Booking type #'.($i + 1).': missing name.';

                continue;
            }

            $existing = BookingType::withTrashed()->where('name', $name)->first();
            $attributes = collect($row)->only([
                'description', 'enabled', 'instructions_for_user',
                'instructions_for_it', 'requires_approval', 'display_order',
            ])->all();

            if ($existing) {
                $existing->fill($attributes + ['name' => $name])->save();
                $this->applyTrash($existing, (bool) ($row['deleted'] ?? false));
                $updated++;
            } else {
                $type = BookingType::create($attributes + ['name' => $name]);
                $this->applyTrash($type, (bool) ($row['deleted'] ?? false));
                $created++;
            }
        }

        return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped];
    }

    /** @return array{created: int, updated: int, skipped: list<string>} */
    private function importSchedulePeriods(array $rows): array
    {
        $created = 0;
        $updated = 0;
        $skipped = [];

        foreach ($rows as $i => $row) {
            $group = trim((string) ($row['group_name'] ?? ''));
            $name = trim((string) ($row['name'] ?? ''));
            if ($group === '' || $name === '') {
                $skipped[] = 'Schedule period #'.($i + 1).': missing group_name or name.';

                continue;
            }

            $existed = SchedulePeriod::where('group_name', $group)->where('name', $name)->exists();
            SchedulePeriod::updateOrCreate(
                ['group_name' => $group, 'name' => $name],
                [
                    'start_time' => $row['start_time'] ?? '09:00',
                    'end_time' => $row['end_time'] ?? '10:00',
                    'enabled' => (bool) ($row['enabled'] ?? true),
                    'display_order' => (int) ($row['display_order'] ?? 0),
                ]
            );
            $existed ? $updated++ : $created++;
        }

        return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped];
    }

    /** @return array{created: int, updated: int, skipped: list<string>} */
    private function importApprovalRules(array $rows): array
    {
        $created = 0;
        $updated = 0;
        $skipped = [];

        foreach ($rows as $i => $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                $skipped[] = 'Approval rule #'.($i + 1).': missing name.';

                continue;
            }

            $poolId = null;
            if (! empty($row['resource_pool_slug'])) {
                $poolId = ResourcePool::where('slug', $row['resource_pool_slug'])->value('id');
                if (! $poolId) {
                    $skipped[] = "Approval rule \"{$name}\": resource pool \"{$row['resource_pool_slug']}\" not found — imported as a global rule.";
                }
            }

            $existed = ApprovalRule::where('name', $name)->exists();
            ApprovalRule::updateOrCreate(['name' => $name], [
                'resource_pool_id' => $poolId,
                'rule_type' => $row['rule_type'] ?? 'min_quantity',
                'threshold_value' => (int) ($row['threshold_value'] ?? 1),
                'enabled' => (bool) ($row['enabled'] ?? true),
                'display_order' => (int) ($row['display_order'] ?? 0),
            ]);
            $existed ? $updated++ : $created++;
        }

        return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped];
    }

    /** @return array{created: int, updated: int, skipped: list<string>} */
    private function importMessageTemplates(array $rows): array
    {
        $created = 0;
        $updated = 0;
        $skipped = [];

        // Only keys the current release knows about — an import can't invent
        // template rows the app never reads.
        $known = array_keys(MessageTemplateSeeder::defaults());

        foreach ($rows as $i => $row) {
            $key = trim((string) ($row['key'] ?? ''));
            if ($key === '' || ! in_array($key, $known, true)) {
                $skipped[] = 'Message template #'.($i + 1).': unknown key "'.$key.'" — ignored.';

                continue;
            }

            $existed = MessageTemplate::where('key', $key)->exists();
            MessageTemplate::updateOrCreate(['key' => $key], [
                'subject' => $row['subject'] ?? null,
                'intro' => $row['intro'] ?? null,
                'enabled' => (bool) ($row['enabled'] ?? true),
            ]);
            $existed ? $updated++ : $created++;
        }

        return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped];
    }

    private function applyTrash(Model $model, bool $shouldBeTrashed): void
    {
        if (! method_exists($model, 'trashed')) {
            return;
        }
        if ($shouldBeTrashed && ! $model->trashed()) {
            $model->delete();
        } elseif (! $shouldBeTrashed && $model->trashed()) {
            $model->restore();
        }
    }
}
