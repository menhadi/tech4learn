<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configurations', function (Blueprint $table) {
            $table->json('ai_task_priorities')->nullable()->after('ai_provider_priority');
        });
    }

    public function down(): void
    {
        Schema::table('configurations', function (Blueprint $table) {
            $table->dropColumn('ai_task_priorities');
        });
    }
};