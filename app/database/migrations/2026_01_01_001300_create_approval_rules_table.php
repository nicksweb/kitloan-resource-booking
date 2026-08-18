<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Deliberately minimal — most approval conditions (lead time, weekends,
        // out-of-hours, booking type, resource pool) are plain columns on
        // resource_pools/booking_types/settings. This table exists only for the
        // conditions that don't fit a single owning row, e.g. "more than N
        // resources requires approval" scoped to a pool (or globally, if null).
        Schema::create('approval_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('resource_pool_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('rule_type')->default('min_quantity');
            $table->unsignedInteger('threshold_value');
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_rules');
    }
};
