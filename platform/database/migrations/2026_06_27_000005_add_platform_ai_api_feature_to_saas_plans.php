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

                if (! array_key_exists('ai_platform_api', $features)) {
                    $features['ai_platform_api'] = true;

                    DB::table('saas_plans')
                        ->where('id', $plan->id)
                        ->update([
                            'features' => json_encode($features),
                            'updated_at' => now(),
                        ]);
                }
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

                if (! is_array($features) || ! array_key_exists('ai_platform_api', $features)) {
                    continue;
                }

                unset($features['ai_platform_api']);

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
