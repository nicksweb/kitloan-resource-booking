<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive — "book an IT officer as a resource":
 *  - resources.user_id        a resource can stand for a person (an opted-in officer)
 *  - resource_pools.kind       equipment (default) | staff
 *  - resource_pools.approval_route  team (default) | assigned_officer
 *  - bookings.helpdesk_url     optional link to a helpdesk ticket
 *  - users.bookable_as_officer the self-service opt-in flag
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resources', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('resource_pool_id')->constrained('users')->nullOnDelete();
        });

        Schema::table('resource_pools', function (Blueprint $table) {
            $table->string('kind')->default('equipment')->after('allocation_mode');
            $table->string('approval_route')->default('team')->after('auto_approval_enabled');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->string('helpdesk_url', 2048)->nullable()->after('notes');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('bookable_as_officer')->default(false)->after('receives_daily_summary');
        });
    }

    public function down(): void
    {
        Schema::table('resources', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
        Schema::table('resource_pools', function (Blueprint $table) {
            $table->dropColumn(['kind', 'approval_route']);
        });
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('helpdesk_url');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('bookable_as_officer');
        });
    }
};
