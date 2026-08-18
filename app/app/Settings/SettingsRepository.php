<?php

namespace App\Settings;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Non-secret application settings backed by the `settings` table.
 * Secrets (OIDC, Snipe-IT, SMTP credentials) never live here — see config/*.php,
 * which reads them from the environment only.
 */
class SettingsRepository
{
    private const CACHE_KEY = 'app_settings.all';

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    public function set(string $key, mixed $value, string $type = 'string'): void
    {
        Setting::updateOrCreate(
            ['key' => $key],
            ['value' => $this->encode($value, $type), 'type' => $type]
        );

        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Cached as a plain associative array, not a Collection/Eloquent object —
     * with CACHE_STORE=database the value is PHP-serialized, and a serialized
     * object graph can fail to unserialize cleanly after the app image is
     * rebuilt (class layout/autoloader state isn't guaranteed identical
     * across deploys, and rememberForever has no TTL to naturally expire a
     * stale entry). A plain array has no class-identity to break.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            return Setting::all()->mapWithKeys(
                fn (Setting $setting) => [$setting->key => $this->decode($setting->value, $setting->type)]
            )->all();
        });
    }

    public function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private function decode(?string $value, string $type): mixed
    {
        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOL),
            'integer' => (int) $value,
            'json' => json_decode((string) $value, true),
            default => $value,
        };
    }

    private function encode(mixed $value, string $type): string
    {
        return match ($type) {
            'boolean' => $value ? '1' : '0',
            'json' => json_encode($value),
            default => (string) $value,
        };
    }
}
