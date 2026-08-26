<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive. Editable copy for the notification emails — a subject line and an
 * intro paragraph per message, plus a shared "policy notice" block. Rendered
 * with {{ token }} substitution from a fixed whitelist (see TemplateRenderer);
 * never evaluated as Blade/PHP. The structured booking-details table and the
 * calendar attachment stay in code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('subject')->nullable();
            $table->text('intro')->nullable();
            $table->boolean('enabled')->default(true);
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_templates');
    }
};
