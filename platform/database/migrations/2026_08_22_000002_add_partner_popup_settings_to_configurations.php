<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('configurations') || Schema::hasColumn('configurations', 'partner_popup_settings')) {
            return;
        }

        Schema::table('configurations', function (Blueprint $table) {
            $table->json('partner_popup_settings')->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('configurations') || ! Schema::hasColumn('configurations', 'partner_popup_settings')) {
            return;
        }

        Schema::table('configurations', function (Blueprint $table) {
            $table->dropColumn('partner_popup_settings');
        });
    }
};
