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

        DB::table('saas_plans')->select('id', 'features')->orderBy('id')->get()->each(function ($plan) {
            $features = json_decode($plan->features ?? '[]', true);

            if (! is_array($features)) {
                $features = [];
            }

            $changed = false;

            foreach ([
                'email_messaging' => true,
                'sms_messaging' => true,
                'reports' => true,
                'question_sharing' => true,
            ] as $key => $default) {
                if (! array_key_exists($key, $features)) {
                    $features[$key] = $default;
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
        // Keep existing plan decisions intact on rollback.
    }
};
