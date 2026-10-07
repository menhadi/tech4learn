<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->string('test_type', 32)->default('other')->after('display_order')->index();
            $table->foreignId('test_subject_id')->nullable()->after('test_type')
                ->constrained('subjects')->nullOnDelete();
            $table->foreignId('test_topic_id')->nullable()->after('test_subject_id')
                ->constrained('topics')->nullOnDelete();
            $table->index(['test_type', 'test_subject_id', 'test_topic_id'], 'exams_test_classification_idx');
        });
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropIndex('exams_test_classification_idx');
            $table->dropForeign(['test_topic_id']);
            $table->dropForeign(['test_subject_id']);
            $table->dropColumn(['test_topic_id', 'test_subject_id', 'test_type']);
        });
    }
};
