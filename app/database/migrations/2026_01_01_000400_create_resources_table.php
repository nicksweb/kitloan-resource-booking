<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_pool_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('name');
            $table->string('asset_number')->nullable();
            $table->string('serial')->nullable();
            $table->enum('status', [
                'available', 'unavailable', 'maintenance', 'missing', 'retired', 'disabled',
            ])->default('available');
            $table->enum('source', ['manual', 'snipeit'])->default('manual');
            $table->unsignedInteger('display_order')->default(0);
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['resource_pool_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resources');
    }
};
