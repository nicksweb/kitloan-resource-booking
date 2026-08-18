<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            // Filled immediately after insert (uses the auto-increment id to
            // guarantee uniqueness without extra locking) — see BookingService.
            $table->string('reference')->nullable()->unique();

            // Primary pool — drives the reference prefix and default approval
            // rules. Additional resource types (e.g. chargers alongside
            // laptops) live in booking_items and may reference other pools.
            $table->foreignId('resource_pool_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('booking_type_id')->nullable()->constrained()->nullOnDelete();

            $table->foreignId('booked_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();

            $table->dateTime('start_at');
            $table->dateTime('end_at');

            $table->text('notes')->nullable();

            $table->enum('approval_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->enum('allocation_status', ['unallocated', 'partial', 'allocated'])->default('unallocated');
            $table->enum('lifecycle_status', ['active', 'cancelled', 'completed'])->default('active');

            $table->boolean('auto_approved')->default(false);
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->foreignId('rejected_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();

            $table->boolean('conflict_override')->default(false);
            $table->text('override_reason')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['start_at', 'end_at']);
            $table->index(['approval_status', 'lifecycle_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
