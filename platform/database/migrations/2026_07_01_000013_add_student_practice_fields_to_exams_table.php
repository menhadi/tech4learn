<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            if (! Schema::hasColumn('exams', 'created_by_student_id')) {
                $table->unsignedBigInteger('created_by_student_id')->nullable()->after('organization_id')->index();
            }

            if (! Schema::hasColumn('exams', 'is_student_practice')) {
                $table->boolean('is_student_practice')->default(false)->after('created_by_student_id')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            if (Schema::hasColumn('exams', 'is_student_practice')) {
                $table->dropColumn('is_student_practice');
            }

            if (Schema::hasColumn('exams', 'created_by_student_id')) {
                $table->dropColumn('created_by_student_id');
            }
        });
    }
};
