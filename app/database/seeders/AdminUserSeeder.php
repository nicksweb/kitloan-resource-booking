<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    /**
     * Pre-creates Administrator accounts from ADMIN_SEED_EMAILS so someone
     * can reach the admin panel on first OIDC login. Existing accounts with
     * the same email are left alone (their role is never changed here).
     */
    public function run(): void
    {
        $emails = array_filter(array_map('trim', explode(',', (string) config('booking.defaults.admin_seed_emails'))));

        foreach ($emails as $email) {
            $user = User::firstOrCreate(
                ['email' => $email],
                ['name' => Str::upper(Str::before($email, '@')), 'enabled' => true]
            );

            if (! $user->hasRole('administrator')) {
                $user->assignRole('administrator');
            }
        }
    }
}
