<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'is_platform_admin')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('is_platform_admin')->default(false)->after('status');
            });
        }

        // Installing a flag never grants authority. Platform administrators
        // must be explicitly provisioned and linked to a verified API account.
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'is_platform_admin')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('is_platform_admin');
            });
        }
    }
};
