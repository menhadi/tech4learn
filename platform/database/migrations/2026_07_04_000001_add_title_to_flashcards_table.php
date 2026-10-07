<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flashcards', function (Blueprint $table) {
            if (! Schema::hasColumn('flashcards', 'title')) {
                $table->string('title')->nullable()->after('flashcard_set_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('flashcards', function (Blueprint $table) {
            if (Schema::hasColumn('flashcards', 'title')) {
                $table->dropColumn('title');
            }
        });
    }
};
