<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'passages',
        'coupons',
        'questions_report',
    ];

    public function up(): void
    {
        $this->createQuestionsReportTableIfMissing();

        foreach ($this->tables as $tableName) {
            if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'organization_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedBigInteger('organization_id')->nullable()->after('id')->index();
            });
        }

        $defaultOrganizationId = DB::table('organizations')->where('slug', 'examelite')->value('id')
            ?: DB::table('organizations')->orderBy('id')->value('id');

        if (! $defaultOrganizationId) {
            return;
        }

        foreach ($this->tables as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'organization_id')) {
                continue;
            }

            DB::table($tableName)
                ->whereNull('organization_id')
                ->update(['organization_id' => $defaultOrganizationId]);
        }
    }


    private function createQuestionsReportTableIfMissing(): void
    {
        if (Schema::hasTable('questions_report')) {
            return;
        }

        Schema::create('questions_report', function (Blueprint $table) {
            $table->id();
            $table->string('guest_id')->nullable()->index();
            $table->unsignedBigInteger('student_id')->nullable()->index();
            $table->unsignedBigInteger('question_id')->nullable()->index();
            $table->unsignedBigInteger('subject_id')->nullable()->index();
            $table->string('question_type')->nullable();
            $table->text('message')->nullable();
            $table->string('status')->default('Pending')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (array_reverse($this->tables) as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'organization_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $table->dropIndex("{$tableName}_organization_id_index");
                $table->dropColumn('organization_id');
            });
        }
    }
};
