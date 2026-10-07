<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('configurations')) {
            return;
        }

        Schema::table('configurations', function (Blueprint $table) {
            if (! Schema::hasColumn('configurations', 'secondary_menu_top_border_color')) {
                $table->string('secondary_menu_top_border_color', 7)->nullable();
            }
            if (! Schema::hasColumn('configurations', 'secondary_menu_bottom_border_color')) {
                $table->string('secondary_menu_bottom_border_color', 7)->nullable();
            }
            if (! Schema::hasColumn('configurations', 'show_package_tag_filters')) {
                $table->boolean('show_package_tag_filters')->default(true);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('configurations')) {
            return;
        }

        Schema::table('configurations', function (Blueprint $table) {
            if (Schema::hasColumn('configurations', 'show_package_tag_filters')) {
                $table->dropColumn('show_package_tag_filters');
            }
            if (Schema::hasColumn('configurations', 'secondary_menu_bottom_border_color')) {
                $table->dropColumn('secondary_menu_bottom_border_color');
            }
            if (Schema::hasColumn('configurations', 'secondary_menu_top_border_color')) {
                $table->dropColumn('secondary_menu_top_border_color');
            }
        });
    }
};
