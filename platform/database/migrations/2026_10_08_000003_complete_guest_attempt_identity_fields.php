<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // The copied guest engine creates attempts without a registered student.
        foreach (['exam_results','exam_stats'] as $name) {
            Schema::table($name,function (Blueprint $table) {
                $table->unsignedBigInteger('student_id')->nullable()->change();
            });
        }
        if (!Schema::hasColumn('exam_stats','guest_id')) {
            Schema::table('exam_stats',function (Blueprint $table) {
                $table->string('guest_id')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        // Preserve guest attempts and evidence; reverting nullability would destroy them.
    }
};
