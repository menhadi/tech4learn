<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('configurations', function (Blueprint $table) {
            $table->string('brand_fallback_name')->nullable();
            $table->string('brand_fallback_tagline')->nullable();
            $table->string('brand_fallback_icon', 100)->nullable();
            $table->string('homepage_hero_title')->nullable();
            $table->text('homepage_hero_phrases')->nullable();
            $table->text('homepage_hero_description')->nullable();
            $table->string('homepage_search_placeholder')->nullable();
            $table->string('homepage_search_button_text', 100)->nullable();
            $table->string('homepage_search_empty_text')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('configurations', function (Blueprint $table) {
            $table->dropColumn([
                'brand_fallback_name', 'brand_fallback_tagline', 'brand_fallback_icon',
                'homepage_hero_title', 'homepage_hero_phrases', 'homepage_hero_description',
                'homepage_search_placeholder', 'homepage_search_button_text', 'homepage_search_empty_text',
            ]);
        });
    }
};
