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
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('staff_id')->constrained()->onDelete('cascade');
            $table->foreignId('service_id')->constrained()->onDelete('cascade');
            $table->foreignId('haircut_id')->nullable()->constrained()->onDelete('set null');
            $table->date('date');
            $table->time('time');
            $table->string('status')->default('pending'); // pending, confirmed, completed, cancelled, no_show
            $table->timestamps();

            // Index per query velocizzate
            $table->index(['user_id', 'date']);
            $table->index(['staff_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};