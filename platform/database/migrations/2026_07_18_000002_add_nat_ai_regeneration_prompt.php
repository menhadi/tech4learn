<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configurations', function (Blueprint $table) {
            if (! Schema::hasColumn('configurations', 'ai_regeneration_nat_prompt')) {
                $table->longText('ai_regeneration_nat_prompt')->nullable()->after('ai_regeneration_subjective_prompt');
            }
        });
    }

    public function down(): void
    {
        Schema::table('configurations', function (Blueprint $table) {
            if (Schema::hasColumn('configurations', 'ai_regeneration_nat_prompt')) {
                $table->dropColumn('ai_regeneration_nat_prompt');
            }
        });
    }
};