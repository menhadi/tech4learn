<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_normalization_runs', function (Blueprint $table) {
            $table->json('restore_exam_ids')->nullable()->after('restored_child_run_ids');
            $table->json('restored_exam_ids')->nullable()->after('restore_exam_ids');
        });
    }

    public function down(): void
    {
        Schema::table('content_normalization_runs', function (Blueprint $table) {
            $table->dropColumn(['restore_exam_ids', 'restored_exam_ids']);
        });
    }
};
