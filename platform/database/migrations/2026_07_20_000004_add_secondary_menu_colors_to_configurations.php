<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configurations', function (Blueprint $table) {
            if (! Schema::hasColumn('configurations', 'secondary_menu_bg_color')) {
                $table->string('secondary_menu_bg_color', 7)->nullable();
            }
            if (! Schema::hasColumn('configurations', 'secondary_menu_text_color')) {
                $table->string('secondary_menu_text_color', 7)->nullable();
            }
            if (! Schema::hasColumn('configurations', 'secondary_menu_hover_color')) {
                $table->string('secondary_menu_hover_color', 7)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('configurations', function (Blueprint $table) {
            $columns = array_values(array_filter([
                Schema::hasColumn('configurations', 'secondary_menu_bg_color') ? 'secondary_menu_bg_color' : null,
                Schema::hasColumn('configurations', 'secondary_menu_text_color') ? 'secondary_menu_text_color' : null,
                Schema::hasColumn('configurations', 'secondary_menu_hover_color') ? 'secondary_menu_hover_color' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};