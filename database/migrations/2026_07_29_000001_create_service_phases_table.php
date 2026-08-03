<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_phases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('duration');
            $table->boolean('staff_required')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['service_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_phases');
    }
};
