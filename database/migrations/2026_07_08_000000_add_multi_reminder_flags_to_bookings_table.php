<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->boolean('reminder_24h_sent')->default(false);
            $table->boolean('reminder_3h_sent')->default(false);
            $table->boolean('reminder_1h_sent')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn([
                'reminder_24h_sent',
                'reminder_3h_sent',
                'reminder_1h_sent',
            ]);
        });
    }
};
