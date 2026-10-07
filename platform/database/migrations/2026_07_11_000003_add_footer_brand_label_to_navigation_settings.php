<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('navigation_settings') && ! Schema::hasColumn('navigation_settings', 'footer_brand_label')) {
            Schema::table('navigation_settings', fn (Blueprint $table) => $table->string('footer_brand_label')->nullable()->after('footer_enabled'));
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('navigation_settings') && Schema::hasColumn('navigation_settings', 'footer_brand_label')) {
            Schema::table('navigation_settings', fn (Blueprint $table) => $table->dropColumn('footer_brand_label'));
        }
    }
};
