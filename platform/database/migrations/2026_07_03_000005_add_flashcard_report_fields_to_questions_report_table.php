<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions_report', function (Blueprint $table) {
            if (! Schema::hasColumn('questions_report', 'guest_name')) {
                $table->string('guest_name')->nullable()->after('guest_id');
            }

            if (! Schema::hasColumn('questions_report', 'guest_email')) {
                $table->string('guest_email')->nullable()->after('guest_name');
            }

            if (! Schema::hasColumn('questions_report', 'flashcard_id')) {
                $table->unsignedBigInteger('flashcard_id')->nullable()->after('question_id')->index();
            }

            if (! Schema::hasColumn('questions_report', 'flashcard_set_id')) {
                $table->unsignedBigInteger('flashcard_set_id')->nullable()->after('flashcard_id')->index();
            }

            if (! Schema::hasColumn('questions_report', 'report_source')) {
                $table->string('report_source', 30)->default('question')->after('flashcard_set_id')->index();
            }
        });

        Schema::table('questions_report', function (Blueprint $table) {
            $table->unsignedBigInteger('question_id')->nullable()->change();
            $table->unsignedBigInteger('subject_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('questions_report', function (Blueprint $table) {
            if (Schema::hasColumn('questions_report', 'report_source')) {
                $table->dropColumn('report_source');
            }

            if (Schema::hasColumn('questions_report', 'flashcard_set_id')) {
                $table->dropColumn('flashcard_set_id');
            }

            if (Schema::hasColumn('questions_report', 'flashcard_id')) {
                $table->dropColumn('flashcard_id');
            }

            if (Schema::hasColumn('questions_report', 'guest_email')) {
                $table->dropColumn('guest_email');
            }

            if (Schema::hasColumn('questions_report', 'guest_name')) {
                $table->dropColumn('guest_name');
            }
        });
    }
};
