<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('configurations', 'source_extractor_mappings')) {
            Schema::table('configurations', function (Blueprint $table) {
                $table->json('source_extractor_mappings')->nullable()->after('ai_task_priorities');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('configurations', 'source_extractor_mappings')) {
            Schema::table('configurations', function (Blueprint $table) {
                $table->dropColumn('source_extractor_mappings');
            });
        }
    }
};
