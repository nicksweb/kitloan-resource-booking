<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resource_pools', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('enabled')->default(true);
            $table->string('icon')->default('laptop');
            $table->unsignedInteger('display_order')->default(0);

            // individual = discrete tracked resources (e.g. Exam Laptop 01..20)
            // quantity   = pooled count only (e.g. 30 USB-C Chargers)
            $table->enum('allocation_mode', ['individual', 'quantity'])->default('individual');
            $table->unsignedInteger('quantity_total')->nullable();

            $table->unsignedInteger('minimum_lead_time_minutes')->default(0);
            $table->unsignedInteger('preparation_buffer_minutes')->default(0);
            $table->unsignedInteger('return_buffer_minutes')->default(0);

            $table->boolean('allow_weekends')->default(false);
            $table->boolean('allow_out_of_hours')->default(false);

            $table->boolean('requires_room')->default(true);
            $table->boolean('allows_student')->default(true);
            $table->boolean('requires_student')->default(false);
            $table->boolean('requires_booking_type')->default(true);

            $table->boolean('auto_approval_enabled')->default(true);
            $table->string('booking_reference_prefix', 8)->default('BK');

            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resource_pools');
    }
};
