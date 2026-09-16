<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('booking_waitlist_entries')) {
            Schema::create('booking_waitlist_entries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('staff_id')->constrained()->cascadeOnDelete();
                $table->foreignId('service_id')->constrained()->cascadeOnDelete();
                $table->date('date');
                $table->time('time');
                $table->string('status', 20)->default('waiting');
                $table->foreignId('assigned_booking_id')->nullable()->constrained('bookings')->nullOnDelete();
                $table->timestamps();
            });
        }

        $indexes = Schema::getIndexes('booking_waitlist_entries');
        $uniqueColumns = ['user_id', 'staff_id', 'service_id', 'date', 'time'];
        $hasUnique = collect($indexes)->contains(
            fn (array $index) => ($index['unique'] ?? false) && $index['columns'] === $uniqueColumns
        );

        if (! $hasUnique) {
            Schema::table('booking_waitlist_entries', function (Blueprint $table) use ($uniqueColumns) {
                $table->unique($uniqueColumns, 'booking_waitlist_user_slot_unique');
            });
        }

        $indexes = Schema::getIndexes('booking_waitlist_entries');
        $queueColumns = ['staff_id', 'date', 'status', 'created_at'];
        $hasQueueIndex = collect($indexes)->contains(
            fn (array $index) => $index['columns'] === $queueColumns
        );

        if (! $hasQueueIndex) {
            Schema::table('booking_waitlist_entries', function (Blueprint $table) use ($queueColumns) {
                $table->index($queueColumns, 'booking_waitlist_staff_queue_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_waitlist_entries');
    }
};
