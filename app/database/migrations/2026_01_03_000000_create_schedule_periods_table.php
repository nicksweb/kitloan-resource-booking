<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Admin-configurable school timetable periods (e.g. "Period 1", "Pastoral
     * Care") used purely as quick-fill presets for the booking start/finish
     * time fields — not a source of truth for anything else, and never
     * enforced as a constraint on when a booking can be made.
     */
    public function up(): void
    {
        Schema::create('schedule_periods', function (Blueprint $table) {
            $table->id();
            // Free-text grouping label, e.g. "Junior School" / "Senior School"
            // — lets the picker show the right list without a rigid schema.
            $table->string('group_name');
            $table->string('name');
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();

            $table->index(['group_name', 'display_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_periods');
    }
};
