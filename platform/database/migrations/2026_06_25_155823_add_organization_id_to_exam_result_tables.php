<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'exam_results',
        'exam_stats',
        'exam_result_details',
        'exam_feedbacks',
        'exam_proctor_images',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'organization_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $table) {
                $table->unsignedBigInteger('organization_id')->nullable()->after('id')->index();
            });
        }

        $this->backfillFromExam('exam_results');
        $this->backfillFromExam('exam_stats');
        $this->backfillFromExam('exam_result_details');
        $this->backfillFromExam('exam_proctor_images');
        $this->backfillFeedbacks();
    }

    public function down(): void
    {
        foreach (array_reverse($this->tables) as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'organization_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn('organization_id');
            });
        }
    }

    private function backfillFromExam(string $table): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'organization_id') || ! Schema::hasColumn($table, 'exam_id')) {
            return;
        }

        DB::table($table)
            ->select(['id', 'exam_id'])
            ->whereNull('organization_id')
            ->whereNotNull('exam_id')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($table) {
                $organizationIds = DB::table('exams')
                    ->whereIn('id', $rows->pluck('exam_id')->filter()->unique())
                    ->pluck('organization_id', 'id');

                foreach ($rows as $row) {
                    $organizationId = $organizationIds->get($row->exam_id);

                    if ($organizationId) {
                        DB::table($table)->where('id', $row->id)->update(['organization_id' => $organizationId]);
                    }
                }
            });
    }

    private function backfillFeedbacks(): void
    {
        if (! Schema::hasTable('exam_feedbacks') || ! Schema::hasColumn('exam_feedbacks', 'organization_id')) {
            return;
        }

        DB::table('exam_feedbacks')
            ->select(['id', 'exam_result_id'])
            ->whereNull('organization_id')
            ->whereNotNull('exam_result_id')
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                $organizationIds = DB::table('exam_results')
                    ->whereIn('id', $rows->pluck('exam_result_id')->filter()->unique())
                    ->pluck('organization_id', 'id');

                foreach ($rows as $row) {
                    $organizationId = $organizationIds->get($row->exam_result_id);

                    if ($organizationId) {
                        DB::table('exam_feedbacks')->where('id', $row->id)->update(['organization_id' => $organizationId]);
                    }
                }
            });
    }
};
