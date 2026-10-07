<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('about_us')) {
            return;
        }

        Schema::table('about_us', function (Blueprint $table) {
            if (! Schema::hasColumn('about_us', 'meta_title')) {
                $table->string('meta_title', 191)->nullable();
            }

            if (! Schema::hasColumn('about_us', 'meta_description')) {
                $table->text('meta_description')->nullable();
            }

            if (! Schema::hasColumn('about_us', 'meta_keywords')) {
                $table->text('meta_keywords')->nullable();
            }

            if (! Schema::hasColumn('about_us', 'canonical_url')) {
                $table->string('canonical_url', 255)->nullable();
            }

            if (! Schema::hasColumn('about_us', 'og_title')) {
                $table->string('og_title', 191)->nullable();
            }

            if (! Schema::hasColumn('about_us', 'og_description')) {
                $table->text('og_description')->nullable();
            }

            if (! Schema::hasColumn('about_us', 'og_image')) {
                $table->string('og_image', 255)->nullable();
            }

            if (! Schema::hasColumn('about_us', 'robots_meta')) {
                $table->string('robots_meta', 50)->nullable()->default('index,follow');
            }

            if (! Schema::hasColumn('about_us', 'seo_schema')) {
                $table->longText('seo_schema')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('about_us')) {
            return;
        }

        Schema::table('about_us', function (Blueprint $table) {
            foreach ([
                'meta_title',
                'meta_description',
                'meta_keywords',
                'canonical_url',
                'og_title',
                'og_description',
                'og_image',
                'robots_meta',
                'seo_schema',
            ] as $column) {
                if (Schema::hasColumn('about_us', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
