<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('flashcard_sets')) {
            return;
        }

        Schema::table('flashcard_sets', function (Blueprint $table) {
            if (! Schema::hasColumn('flashcard_sets', 'category_level_1')) {
                $table->unsignedBigInteger('category_level_1')->nullable()->after('group_id');
            }

            if (! Schema::hasColumn('flashcard_sets', 'category_level_2')) {
                $table->unsignedBigInteger('category_level_2')->nullable()->after('category_level_1');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('flashcard_sets')) {
            return;
        }

        Schema::table('flashcard_sets', function (Blueprint $table) {
            foreach (['category_level_2', 'category_level_1'] as $column) {
                if (Schema::hasColumn('flashcard_sets', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
