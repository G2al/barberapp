<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->string('last_name', 100)->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('staff')->whereNull('last_name')->update(['last_name' => '']);

        Schema::table('staff', function (Blueprint $table) {
            $table->string('last_name', 100)->nullable(false)->change();
        });
    }
};
