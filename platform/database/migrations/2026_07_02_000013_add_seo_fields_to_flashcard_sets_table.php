<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $columns = [
            'meta_title' => fn (Blueprint $table) => $table->string('meta_title')->nullable()->after('status'),
            'canonical_url' => fn (Blueprint $table) => $table->string('canonical_url')->nullable()->after('meta_title'),
            'meta_description' => fn (Blueprint $table) => $table->text('meta_description')->nullable()->after('canonical_url'),
            'meta_keywords' => fn (Blueprint $table) => $table->text('meta_keywords')->nullable()->after('meta_description'),
            'og_title' => fn (Blueprint $table) => $table->string('og_title')->nullable()->after('meta_keywords'),
            'og_image' => fn (Blueprint $table) => $table->string('og_image')->nullable()->after('og_title'),
            'og_description' => fn (Blueprint $table) => $table->text('og_description')->nullable()->after('og_image'),
            'robots_meta' => fn (Blueprint $table) => $table->string('robots_meta')->nullable()->after('og_description'),
            'seo_schema' => fn (Blueprint $table) => $table->longText('seo_schema')->nullable()->after('robots_meta'),
        ];

        foreach ($columns as $column => $callback) {
            if (! Schema::hasColumn('flashcard_sets', $column)) {
                Schema::table('flashcard_sets', $callback);
            }
        }
    }

    public function down(): void
    {
        $columns = collect([
            'meta_title',
            'canonical_url',
            'meta_description',
            'meta_keywords',
            'og_title',
            'og_image',
            'og_description',
            'robots_meta',
            'seo_schema',
        ])->filter(fn ($column) => Schema::hasColumn('flashcard_sets', $column))->all();

        if (! empty($columns)) {
            Schema::table('flashcard_sets', fn (Blueprint $table) => $table->dropColumn($columns));
        }
    }
};
