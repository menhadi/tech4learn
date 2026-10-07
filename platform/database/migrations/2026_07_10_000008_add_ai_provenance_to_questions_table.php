<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            if (! Schema::hasColumn('questions', 'ai_generated')) {
                $table->string('ai_generated', 30)->nullable()->after('status')->index();
            }
            if (! Schema::hasColumn('questions', 'original_question_id')) {
                $table->unsignedBigInteger('original_question_id')->nullable()->after('ai_generated')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            if (Schema::hasColumn('questions', 'original_question_id')) {
                $table->dropColumn('original_question_id');
            }
            if (Schema::hasColumn('questions', 'ai_generated')) {
                $table->dropColumn('ai_generated');
            }
        });
    }
};
