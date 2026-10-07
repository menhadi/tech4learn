<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pages')) {
            return;
        }

        $ordering = [
            'email-templates.index' => 30,
            'email-settings.index' => 31,
            'configurations.messaging' => 32,
            'send-email-form' => 33,
            'sms-templates.index' => 34,
        ];

        foreach ($ordering as $actionName => $position) {
            DB::table('pages')->where('action_name', $actionName)->update([
                'ordering' => $position,
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('pages')) {
            return;
        }

        $ordering = [
            'email-templates.index' => 30,
            'email-settings.index' => 31,
            'send-email-form' => 32,
            'sms-templates.index' => 33,
            'configurations.messaging' => 33,
        ];

        foreach ($ordering as $actionName => $position) {
            DB::table('pages')->where('action_name', $actionName)->update([
                'ordering' => $position,
                'updated_at' => now(),
            ]);
        }
    }
};