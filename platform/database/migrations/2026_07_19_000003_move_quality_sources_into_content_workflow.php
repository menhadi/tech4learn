<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->text('source_url')->nullable()->after('question_code');
            $table->string('source_reference')->nullable()->after('source_url');
        });

        Schema::table('exam_quality_sources', function (Blueprint $table) {
            $table->string('provider', 20)->nullable()->after('source_url');
        });
    }

    public function down(): void
    {
        Schema::table('exam_quality_sources', fn (Blueprint $table) => $table->dropColumn('provider'));
        Schema::table('questions', fn (Blueprint $table) => $table->dropColumn(['source_url', 'source_reference']));
    }
};