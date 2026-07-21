<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('closed_slots', function (Blueprint $table) {
            $table->dropForeign(['staff_id']);
        });

        Schema::table('closed_slots', function (Blueprint $table) {
            $table->unsignedBigInteger('staff_id')->nullable()->change();
            $table->date('end_date')->nullable()->after('date');
            $table->boolean('is_global')->default(false)->after('staff_id');

            $table->foreign('staff_id')->references('id')->on('staff')->nullOnDelete();
            $table->index(['is_global', 'date', 'end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('closed_slots', function (Blueprint $table) {
            $table->dropForeign(['staff_id']);
            $table->dropIndex(['is_global', 'date', 'end_date']);
        });

        Schema::table('closed_slots', function (Blueprint $table) {
            $table->unsignedBigInteger('staff_id')->nullable(false)->change();
            $table->dropColumn(['end_date', 'is_global']);

            $table->foreign('staff_id')->references('id')->on('staff')->cascadeOnDelete();
        });
    }
};
