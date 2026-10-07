<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('category')) {
            return;
        }

        Schema::table('category', function (Blueprint $table) {
            if (! Schema::hasColumn('category', 'description')) {
                $table->text('description')->nullable()->after('title');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('category') || ! Schema::hasColumn('category', 'description')) {
            return;
        }

        Schema::table('category', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
