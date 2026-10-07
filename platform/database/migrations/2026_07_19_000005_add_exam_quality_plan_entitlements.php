<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('saas_plans')) return;

        DB::table('saas_plans')->select('id', 'features', 'limits')->orderBy('id')->get()->each(function ($plan) {
            $features = json_decode($plan->features ?? '[]', true);
            $limits = json_decode($plan->limits ?? '[]', true);
            if (! is_array($features)) $features = [];
            if (! is_array($limits)) $limits = [];

            $base = (bool) ($features['reports'] ?? false);
            $ai = $base && (bool) ($features['ai_content_generation'] ?? $features['ai_regeneration'] ?? false);

            $features += [
                'exam_quality_audit' => $base,
                'exam_quality_source' => $ai,
                'exam_quality_visual' => $base,
                'exam_quality_ai' => $ai,
            ];
            $limits += [
                'quality_audits_monthly' => null,
                'quality_questions_per_audit' => null,
                'quality_source_per_audit' => null,
                'quality_ai_per_audit' => null,
                'quality_visual_per_audit' => null,
                'quality_repairs_monthly' => null,
            ];

            DB::table('saas_plans')->where('id', $plan->id)->update([
                'features' => json_encode($features),
                'limits' => json_encode($limits),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        // Preserve plan decisions and recorded limits on rollback.
    }
};