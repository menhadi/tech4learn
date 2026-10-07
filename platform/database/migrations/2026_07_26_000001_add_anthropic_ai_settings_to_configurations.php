<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configurations', function (Blueprint $table) {
            $table->text('anthropic_api_key')->nullable()->after('deepseek_api_key');
            $table->string('anthropic_model')->nullable()->after('deepseek_model');
        });
    }

    public function down(): void
    {
        Schema::table('configurations', function (Blueprint $table) {
            $table->dropColumn(['anthropic_api_key', 'anthropic_model']);
        });
    }
};