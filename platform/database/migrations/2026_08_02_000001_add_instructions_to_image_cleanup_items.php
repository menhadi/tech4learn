<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('image_cleanup_items') && ! Schema::hasColumn('image_cleanup_items', 'instructions')) {
            Schema::table('image_cleanup_items', fn (Blueprint $table) => $table->text('instructions')->nullable()->after('failure_message'));
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('image_cleanup_items') && Schema::hasColumn('image_cleanup_items', 'instructions')) {
            Schema::table('image_cleanup_items', fn (Blueprint $table) => $table->dropColumn('instructions'));
        }
    }
};