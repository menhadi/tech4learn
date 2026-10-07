<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flashcards', function (Blueprint $table) {
            if (! Schema::hasColumn('flashcards', 'source_question_id')) {
                $table->unsignedBigInteger('source_question_id')->nullable()->after('source_url')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('flashcards', function (Blueprint $table) {
            if (Schema::hasColumn('flashcards', 'source_question_id')) {
                $table->dropColumn('source_question_id');
            }
        });
    }
};
