<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('configurations') || Schema::hasColumn('configurations', 'social_sharing_settings')) {
            return;
        }

        Schema::table('configurations', function (Blueprint $table) {
            $table->json('social_sharing_settings')->nullable();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('configurations') && Schema::hasColumn('configurations', 'social_sharing_settings')) {
            Schema::table('configurations', fn (Blueprint $table) => $table->dropColumn('social_sharing_settings'));
        }
    }
};
