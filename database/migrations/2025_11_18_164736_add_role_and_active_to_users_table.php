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
        Schema::table('users', function (Blueprint $table) {
            // Add surname if not exist
            if (!Schema::hasColumn('users', 'surname')) {
                $table->string('surname', 100)->after('name');
            }

            // Add phone
            if (!Schema::hasColumn('users', 'phone')) {
                $table->string('phone', 20)->unique()->after('email');
            }

            // Add role
            if (!Schema::hasColumn('users', 'role')) {
                $table->string('role', 20)->default('client')->after('password');
            }

            // Add is_active
            if (!Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')->default(1)->after('role');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['surname', 'phone', 'role', 'is_active']);
        });
    }

};
