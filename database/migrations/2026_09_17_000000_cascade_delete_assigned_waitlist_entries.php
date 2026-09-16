<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_waitlist_entries', function (Blueprint $table) {
            $table->dropForeign(['assigned_booking_id']);
        });

        Schema::table('booking_waitlist_entries', function (Blueprint $table) {
            $table->foreign('assigned_booking_id')
                ->references('id')
                ->on('bookings')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('booking_waitlist_entries', function (Blueprint $table) {
            $table->dropForeign(['assigned_booking_id']);
        });

        Schema::table('booking_waitlist_entries', function (Blueprint $table) {
            $table->foreign('assigned_booking_id')
                ->references('id')
                ->on('bookings')
                ->nullOnDelete();
        });
    }
};
