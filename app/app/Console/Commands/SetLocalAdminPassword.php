<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class SetLocalAdminPassword extends Command
{
    protected $signature = 'admin:set-local-password {email}';

    protected $description = 'Set (or change) the emergency local-login password for an administrator account';

    public function handle(AuditLogger $auditLogger): int
    {
        $email = $this->argument('email');
        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("No user found with email {$email}. Create the account first under Administration -> Users, or via the seeder.");

            return self::FAILURE;
        }

        if (! $user->hasRole('administrator')) {
            $this->error("{$email} does not hold the administrator role. Local login is restricted to administrators.");
            $this->line('Assign the role first: Administration -> Users, or `php artisan tinker` -> $user->assignRole(\'administrator\').');

            return self::FAILURE;
        }

        if (! $user->enabled) {
            $this->error("{$email} is disabled. Enable the account first.");

            return self::FAILURE;
        }

        if (! config('auth.local_login.enabled')) {
            $this->warn('Note: LOCAL_LOGIN_ENABLED is currently false in .env — this password will be stored but unusable until that flag is turned on.');
        }

        $password = $this->secret('New password (min 12 characters)');
        $confirmation = $this->secret('Confirm password');

        if ($password !== $confirmation) {
            $this->error('Passwords did not match.');

            return self::FAILURE;
        }

        $validator = Validator::make(['password' => $password], ['password' => ['required', 'string', 'min:12']]);
        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }

        // forceFill(), not update() — 'password' is deliberately absent from
        // User's #[Fillable(...)] list (it's never user-editable via mass
        // assignment elsewhere), so update() would silently discard it here.
        $user->forceFill(['password' => Hash::make($password)])->save();

        $auditLogger->log(
            'auth.local_password_set',
            "Local emergency-login password set for {$user->email} via CLI"
        );

        $this->info("Local login password set for {$email}.");

        return self::SUCCESS;
    }
}
