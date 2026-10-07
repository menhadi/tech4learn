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

        DB::table('saas_plans')->select('id', 'features')->orderBy('id')->chunkById(100, function ($plans) {
            foreach ($plans as $plan) {
                $features = json_decode($plan->features ?? '[]', true);

                if (! is_array($features)) {
                    $features = [];
                }

                $features['ai_platform_api'] = $features['ai_platform_api'] ?? true;
                $features['ai_settings'] = $features['ai_settings'] ?? ($features['ai_generator'] ?? true);
                $features['student_self_registration'] = $features['student_self_registration'] ?? true;

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
        if (! Schema::hasTable('saas_plans')) {
            return;
        }

        DB::table('saas_plans')->select('id', 'features')->orderBy('id')->chunkById(100, function ($plans) {
            foreach ($plans as $plan) {
                $features = json_decode($plan->features ?? '[]', true);

                if (! is_array($features)) {
                    continue;
                }

                unset($features['ai_platform_api'], $features['ai_settings'], $features['student_self_registration']);

                DB::table('saas_plans')
                    ->where('id', $plan->id)
                    ->update([
                        'features' => json_encode($features),
                        'updated_at' => now(),
                    ]);
            }
        });
    }
};
