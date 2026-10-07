<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $columns = [
        'homepage_show_hero',
        'homepage_show_featured_packages',
        'homepage_show_top_performers',
        'homepage_show_testimonials',
        'homepage_show_counters',
        'homepage_show_features',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('configurations')) {
            return;
        }

        Schema::table('configurations', function (Blueprint $table) {
            foreach ($this->columns as $column) {
                if (! Schema::hasColumn('configurations', $column)) {
                    $table->boolean($column)->default(true)->after('allow_guest_exam_attempts');
                }
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('configurations')) {
            return;
        }

        Schema::table('configurations', function (Blueprint $table) {
            foreach (array_reverse($this->columns) as $column) {
                if (Schema::hasColumn('configurations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
