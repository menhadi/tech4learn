<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flashcards', function (Blueprint $table) {
            if (! Schema::hasColumn('flashcards', 'source_label')) {
                $table->string('source_label')->nullable()->after('difficulty');
            }

            if (! Schema::hasColumn('flashcards', 'source_url')) {
                $table->text('source_url')->nullable()->after('source_label');
            }

            if (! Schema::hasColumn('flashcards', 'ai_generated')) {
                $table->boolean('ai_generated')->default(false)->after('source_url');
            }
        });
    }

    public function down(): void
    {
        Schema::table('flashcards', function (Blueprint $table) {
            foreach (['source_label', 'source_url', 'ai_generated'] as $column) {
                if (Schema::hasColumn('flashcards', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
