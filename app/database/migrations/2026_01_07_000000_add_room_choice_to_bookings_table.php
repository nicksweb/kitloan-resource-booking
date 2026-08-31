<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive. How the room requirement was answered:
 *  - room   → a real location_id (the existing behaviour, and the default)
 *  - pickup → the requestor collects the kit from IT; no room
 *  - other  → room not known yet / decided elsewhere; details go in notes
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('room_choice')->default('room')->after('location_id');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('room_choice');
        });
    }
};
