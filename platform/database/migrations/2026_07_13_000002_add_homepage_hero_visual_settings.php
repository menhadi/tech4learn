<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('configurations', function (Blueprint $table) {
            $table->string('homepage_hero_desktop_image')->nullable();
            $table->string('homepage_hero_mobile_image')->nullable();
            $table->unsignedTinyInteger('homepage_hero_overlay')->nullable();
            $table->string('homepage_hero_alignment', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('configurations', function (Blueprint $table) {
            $table->dropColumn([
                'homepage_hero_desktop_image', 'homepage_hero_mobile_image',
                'homepage_hero_overlay', 'homepage_hero_alignment',
            ]);
        });
    }
};
