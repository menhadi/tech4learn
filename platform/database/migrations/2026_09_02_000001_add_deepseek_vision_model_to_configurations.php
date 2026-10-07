<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configurations', function (Blueprint $table) {
            if (! Schema::hasColumn('configurations', 'deepseek_vision_model')) {
                $table->string('deepseek_vision_model')->nullable()->default('deepseek-v4-flash-vision-exp')->after('deepseek_model');
            }
        });
    }

    public function down(): void
    {
        Schema::table('configurations', function (Blueprint $table) {
            if (Schema::hasColumn('configurations', 'deepseek_vision_model')) {
                $table->dropColumn('deepseek_vision_model');
            }
        });
    }
};
