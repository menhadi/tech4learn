<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_quality_sources', function (Blueprint $table) {
            $table->string('storage_disk', 30)->default('local')->after('label');
        });
    }

    public function down(): void
    {
        Schema::table('exam_quality_sources', fn (Blueprint $table) => $table->dropColumn('storage_disk'));
    }
};