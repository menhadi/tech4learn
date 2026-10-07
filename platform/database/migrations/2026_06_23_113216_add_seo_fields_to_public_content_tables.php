<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'groups',
        'categories',
        'package_categories',
        'packages',
        'exams',
        'website_pages',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (! Schema::hasColumn($tableName, 'meta_title')) {
                    $table->string('meta_title', 191)->nullable();
                }

                if (! Schema::hasColumn($tableName, 'meta_description')) {
                    $table->text('meta_description')->nullable();
                }

                if (! Schema::hasColumn($tableName, 'meta_keywords')) {
                    $table->text('meta_keywords')->nullable();
                }

                if (! Schema::hasColumn($tableName, 'canonical_url')) {
                    $table->string('canonical_url', 255)->nullable();
                }

                if (! Schema::hasColumn($tableName, 'og_title')) {
                    $table->string('og_title', 191)->nullable();
                }

                if (! Schema::hasColumn($tableName, 'og_description')) {
                    $table->text('og_description')->nullable();
                }

                if (! Schema::hasColumn($tableName, 'og_image')) {
                    $table->string('og_image', 255)->nullable();
                }

                if (! Schema::hasColumn($tableName, 'robots_meta')) {
                    $table->string('robots_meta', 50)->nullable()->default('index,follow');
                }

                if (! Schema::hasColumn($tableName, 'seo_schema')) {
                    $table->longText('seo_schema')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $columns = [
                    'meta_title',
                    'meta_description',
                    'meta_keywords',
                    'canonical_url',
                    'og_title',
                    'og_description',
                    'og_image',
                    'robots_meta',
                    'seo_schema',
                ];

                foreach ($columns as $column) {
                    if (Schema::hasColumn($tableName, $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
