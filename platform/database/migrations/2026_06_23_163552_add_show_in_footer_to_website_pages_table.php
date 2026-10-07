<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('website_pages')) {
            return;
        }

        Schema::table('website_pages', function (Blueprint $table) {
            if (! Schema::hasColumn('website_pages', 'show_in_footer')) {
                $table->boolean('show_in_footer')->default(false)->after('show_in_menu');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('website_pages') || ! Schema::hasColumn('website_pages', 'show_in_footer')) {
            return;
        }

        Schema::table('website_pages', function (Blueprint $table) {
            $table->dropColumn('show_in_footer');
        });
    }
};
