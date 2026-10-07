<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configurations', function (Blueprint $table) {
            if (! Schema::hasColumn('configurations', 'google_gemini_model')) {
                $table->string('google_gemini_model')->nullable()->default('gemini-1.5-flash')->after('google_gemini_api_key');
            }

            if (! Schema::hasColumn('configurations', 'openai_model')) {
                $table->string('openai_model')->nullable()->default('gpt-4o')->after('openai_api_key');
            }

            if (! Schema::hasColumn('configurations', 'deepseek_model')) {
                $table->string('deepseek_model')->nullable()->default('deepseek-chat')->after('deepseek_api_key');
            }
        });
    }

    public function down(): void
    {
        Schema::table('configurations', function (Blueprint $table) {
            $columns = [];

            foreach (['google_gemini_model', 'openai_model', 'deepseek_model'] as $column) {
                if (Schema::hasColumn('configurations', $column)) {
                    $columns[] = $column;
                }
            }

            if ($columns) {
                $table->dropColumn($columns);
            }
        });
    }
};
