<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Only used for allocation_mode=individual booking items — a specific
        // physical resource tied to a specific booking item. Quantity-mode
        // items are tracked purely as a count on booking_items; there is no
        // per-unit identity to allocate.
        Schema::create('booking_resource_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resource_id')->constrained()->restrictOnDelete();
            $table->timestamp('allocated_at');
            $table->timestamp('released_at')->nullable();
            $table->foreignId('replaced_from_allocation_id')->nullable()
                ->constrained('booking_resource_allocations')->nullOnDelete();
            $table->text('replacement_reason')->nullable();
            $table->timestamps();

            // Fast lookup of a resource's active (non-released) allocations,
            // which is exactly what conflict detection queries on.
            $table->index(['resource_id', 'released_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_resource_allocations');
    }
};
