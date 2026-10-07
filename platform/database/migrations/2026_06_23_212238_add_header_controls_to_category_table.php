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
            if (! Schema::hasColumn('category', 'show_in_header')) {
                $table->boolean('show_in_header')->default(false)->after('status');
            }

            if (! Schema::hasColumn('category', 'header_display_order')) {
                $table->unsignedInteger('header_display_order')->nullable()->after('show_in_header');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('category')) {
            return;
        }

        Schema::table('category', function (Blueprint $table) {
            if (Schema::hasColumn('category', 'header_display_order')) {
                $table->dropColumn('header_display_order');
            }

            if (Schema::hasColumn('category', 'show_in_header')) {
                $table->dropColumn('show_in_header');
            }
        });
    }
};
