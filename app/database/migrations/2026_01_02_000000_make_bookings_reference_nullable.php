<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The original migration was edited to add ->nullable() after it had
     * already run against a live database (migrations don't retroactively
     * re-apply on file changes), leaving the real column NOT NULL while
     * BookingService relies on inserting with a null reference and filling
     * it in immediately after using the new row's id — see ReferenceGenerator.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('reference')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('reference')->nullable(false)->change();
        });
    }
};
