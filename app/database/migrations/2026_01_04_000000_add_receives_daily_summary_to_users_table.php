<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Per-person opt-out for the 7am daily booking summary (IT
            // Operator / Administrator only) — e.g. while someone's on leave.
            // Unrelated to the shared it_notification_address, which still
            // gets every individual booking notification regardless.
            $table->boolean('receives_daily_summary')->default(true)->after('enabled');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('receives_daily_summary');
        });
    }
};
