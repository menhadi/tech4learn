<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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

                if ($changed) {
                    DB::table('saas_plans')
                        ->where('id', $plan->id)
                        ->update([
                            'features' => json_encode($features),
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    public function down(): void
    {
        // Keep plan feature decisions intact on rollback.
    }
};
