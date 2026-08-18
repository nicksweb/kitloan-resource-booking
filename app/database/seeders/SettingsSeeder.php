<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    /**
     * Seeds initial settings from config/booking.php's env-derived defaults.
     * Existing rows are left untouched — this only fills gaps on first run,
     * it never overwrites values changed later via Administration -> Settings.
     */
    public function run(): void
    {
        foreach (config('booking.defaults') as $key => $value) {
            $type = match (true) {
                is_bool($value) => 'boolean',
                is_int($value) => 'integer',
                default => 'string',
            };

            Setting::firstOrCreate(
                ['key' => $key],
                ['value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value, 'type' => $type]
            );
        }
    }
}
