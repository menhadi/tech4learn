<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('packages') && ! Schema::hasColumn('packages', 'show_pdf_download')) {
            Schema::table('packages', function (Blueprint $table) {
                $column = $table->boolean('show_pdf_download')->default(true);

                if (Schema::hasColumn('packages', 'status')) {
                    $column->after('status');
                } elseif (Schema::hasColumn('packages', 'package_type')) {
                    $column->after('package_type');
                }
            });
        }

        if (! Schema::hasTable('saas_plans')) {
            return;
        }

        DB::table('saas_plans')
            ->select('id', 'features')
            ->orderBy('id')
            ->get()
            ->each(function ($plan) {
                $features = json_decode($plan->features ?? '[]', true);

                if (! is_array($features)) {
                    return;
                }

                $defaultAiAccess = $features['ai_translation'] ?? true;
                $changed = false;

                foreach ([
                    'ai_generator',
                    'ai_regeneration',
                    'ai_content_generation',
                    'ai_subjective_analysis',
                    'ai_student_analysis',
                ] as $key) {
                    if (! array_key_exists($key, $features)) {
                        $features[$key] = $defaultAiAccess;
                        $changed = true;
                    }
                }

                if (! $changed) {
                    return;
                }

                DB::table('saas_plans')
                    ->where('id', $plan->id)
                    ->update([
                        'features' => json_encode($features),
                        'updated_at' => now(),
                    ]);
            });
    }

    public function down(): void
    {
        if (Schema::hasTable('packages') && Schema::hasColumn('packages', 'show_pdf_download')) {
            Schema::table('packages', function (Blueprint $table) {
                $table->dropColumn('show_pdf_download');
            });
        }
    }
};
