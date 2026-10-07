<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->foreignId('test_stopic_id')->nullable()->after('test_topic_id')
                ->constrained('stopics')->nullOnDelete();
            $table->index(
                ['test_type', 'test_subject_id', 'test_topic_id', 'test_stopic_id'],
                'exams_test_hierarchy_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropIndex('exams_test_hierarchy_idx');
            $table->dropForeign(['test_stopic_id']);
            $table->dropColumn('test_stopic_id');
        });
    }
};
