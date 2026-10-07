<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('category')) {
            Schema::create('category', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('parent_id')->nullable()->index();
                $table->string('title');
                $table->string('slug')->index();
                $table->boolean('status')->default(true)->index();
                $table->timestamps();
            });
        }

        Schema::table('category', function (Blueprint $table) {
            if (! Schema::hasColumn('category', 'meta_title')) {
                $table->string('meta_title', 191)->nullable();
            }

            if (! Schema::hasColumn('category', 'meta_description')) {
                $table->text('meta_description')->nullable();
            }

            if (! Schema::hasColumn('category', 'meta_keywords')) {
                $table->text('meta_keywords')->nullable();
            }

            if (! Schema::hasColumn('category', 'canonical_url')) {
                $table->string('canonical_url', 255)->nullable();
            }

            if (! Schema::hasColumn('category', 'og_title')) {
                $table->string('og_title', 191)->nullable();
            }

            if (! Schema::hasColumn('category', 'og_description')) {
                $table->text('og_description')->nullable();
            }

            if (! Schema::hasColumn('category', 'og_image')) {
                $table->string('og_image', 255)->nullable();
            }

            if (! Schema::hasColumn('category', 'robots_meta')) {
                $table->string('robots_meta', 50)->nullable()->default('index,follow');
            }

            if (! Schema::hasColumn('category', 'seo_schema')) {
                $table->longText('seo_schema')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('category')) {
            Schema::create('category', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('parent_id')->nullable()->index();
                $table->string('title');
                $table->string('slug')->index();
                $table->boolean('status')->default(true)->index();
                $table->timestamps();
            });
        }

        Schema::table('category', function (Blueprint $table) {
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
                if (Schema::hasColumn('category', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
