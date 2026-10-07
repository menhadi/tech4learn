<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            if (! Schema::hasColumn('packages', 'guest_flashcards_enabled')) {
                $table->boolean('guest_flashcards_enabled')->default(false)->after('flashcards_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            if (Schema::hasColumn('packages', 'guest_flashcards_enabled')) {
                $table->dropColumn('guest_flashcards_enabled');
            }
        });
    }
};
