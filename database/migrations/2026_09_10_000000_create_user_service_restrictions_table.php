<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_service_restrictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            // Null means the service is disabled for every staff member.
            $table->foreignId('staff_id')->nullable()->constrained('staff')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'service_id', 'staff_id']);
            $table->index(['user_id', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_service_restrictions');
    }
};
