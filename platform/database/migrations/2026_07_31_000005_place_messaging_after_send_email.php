<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->applyOrdering([
            'email-templates.index' => 30,
            'email-settings.index' => 31,
            'send-email-form' => 32,
            'configurations.messaging' => 33,
            'sms-templates.index' => 34,
        ]);
    }

    public function down(): void
    {
        $this->applyOrdering([
            'email-templates.index' => 30,
            'email-settings.index' => 31,
            'configurations.messaging' => 32,
            'send-email-form' => 33,
            'sms-templates.index' => 34,
        ]);
    }

    private function applyOrdering(array $ordering): void
    {
        if (! Schema::hasTable('pages')) {
            return;
        }

        foreach ($ordering as $actionName => $position) {
            DB::table('pages')->where('action_name', $actionName)->update([
                'ordering' => $position,
                'updated_at' => now(),
            ]);
        }
    }
};