<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->string('department', 30)->default('hair')->after('role')->index();
        });

        Schema::table('services', function (Blueprint $table) {
            $table->string('department', 30)->default('hair')->after('description')->index();
            $table->string('price_type', 30)->default('fixed')->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropIndex(['department']);
            $table->dropColumn(['department', 'price_type']);
        });

        Schema::table('staff', function (Blueprint $table) {
            $table->dropIndex(['department']);
            $table->dropColumn('department');
        });
    }
};
