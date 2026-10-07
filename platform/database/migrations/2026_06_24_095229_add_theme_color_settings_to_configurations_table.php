<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configurations', function (Blueprint $table) {
            foreach ([
                'theme_primary_color',
                'theme_secondary_color',
                'theme_header_bg',
                'theme_header_text',
                'theme_footer_bg',
                'theme_footer_text',
                'theme_body_bg',
                'theme_heading_color',
                'theme_button_text',
            ] as $column) {
                if (! Schema::hasColumn('configurations', $column)) {
                    $table->string($column, 20)->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('configurations', function (Blueprint $table) {
            foreach ([
                'theme_primary_color',
                'theme_secondary_color',
                'theme_header_bg',
                'theme_header_text',
                'theme_footer_bg',
                'theme_footer_text',
                'theme_body_bg',
                'theme_heading_color',
                'theme_button_text',
            ] as $column) {
                if (Schema::hasColumn('configurations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
