<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive, non-breaking. Adds:
 *  - TOTP two-factor columns (enforced only for local/non-SSO admin logins —
 *    SSO accounts rely on the identity provider's own MFA).
 *  - A per-account lockout stamp for the local-login brute-force limiter.
 *  - Soft deletes, so an administrator can remove a user without breaking the
 *    bookings / audit rows that reference them (those FKs are restrictOnDelete).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('two_factor_secret')->nullable()->after('password');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
            $table->unsignedSmallInteger('two_factor_failed_attempts')->default(0)->after('two_factor_confirmed_at');
            $table->timestamp('locked_until')->nullable()->after('two_factor_failed_attempts');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'two_factor_secret',
                'two_factor_recovery_codes',
                'two_factor_confirmed_at',
                'two_factor_failed_attempts',
                'locked_until',
                'deleted_at',
            ]);
        });
    }
};
