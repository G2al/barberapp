<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_reward_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 40);
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reward_service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->unsignedInteger('points_required')->nullable();
            $table->unsignedInteger('visits_required')->nullable();
            $table->unsignedInteger('reward_points_cost')->default(0);
            $table->string('reward_title');
            $table->text('reward_description')->nullable();
            $table->unsignedInteger('expires_after_days')->nullable();
            $table->boolean('is_repeatable')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_reward_rules');
    }
};
