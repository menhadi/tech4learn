<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flashcards', function (Blueprint $table) {
            if (! Schema::hasColumn('flashcards', 'card_type')) {
                $table->string('card_type', 30)->default('basic')->after('back');
            }

            if (! Schema::hasColumn('flashcards', 'options')) {
                $table->json('options')->nullable()->after('card_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('flashcards', function (Blueprint $table) {
            if (Schema::hasColumn('flashcards', 'options')) {
                $table->dropColumn('options');
            }

            if (Schema::hasColumn('flashcards', 'card_type')) {
                $table->dropColumn('card_type');
            }
        });
    }
};
