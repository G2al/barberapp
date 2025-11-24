<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('availabilities', function (Blueprint $table) {
            $table->id();
            $table->integer('weekday'); // 0 = Sunday, 1 = Monday, ... 6 = Saturday
            $table->string('slot_type'); // 'morning', 'afternoon'
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Indice per velocizzare ricerche
            $table->unique(['weekday', 'slot_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('availabilities');
    }
};