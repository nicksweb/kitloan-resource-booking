<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_asset_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('external_source')->default('snipeit');
            $table->string('external_id');
            $table->string('asset_tag')->nullable();
            $table->string('serial')->nullable();
            $table->string('name')->nullable();
            $table->string('model')->nullable();
            $table->string('status')->nullable();
            $table->string('location')->nullable();
            // Set when the external asset can no longer be found/matches during
            // sync (e.g. deleted in Snipe-IT). The resource + its history is kept.
            $table->timestamp('missing_since')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('external_metadata')->nullable();
            $table->timestamps();

            $table->unique(['external_source', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_asset_links');
    }
};
