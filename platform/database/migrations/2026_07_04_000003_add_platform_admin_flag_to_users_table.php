<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'is_platform_admin')) {
            DB::table('users')
                ->where('email', 'menhadi@gmail.com')
                ->update(['is_platform_admin' => true]);
        }
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
