<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('configurations')) {
            return;
        }

        $columns = [
            'image_cleanup_branding_enabled' => fn (Blueprint $table) => $table->boolean('image_cleanup_branding_enabled')->default(false)->after('image_cleanup_quality'),
            'image_cleanup_branding_mode' => fn (Blueprint $table) => $table->string('image_cleanup_branding_mode', 16)->default('logo')->after('image_cleanup_branding_enabled'),
            'image_cleanup_watermark_text' => fn (Blueprint $table) => $table->string('image_cleanup_watermark_text')->nullable()->after('image_cleanup_branding_mode'),
            'image_cleanup_watermark_opacity' => fn (Blueprint $table) => $table->unsignedTinyInteger('image_cleanup_watermark_opacity')->default(8)->after('image_cleanup_watermark_text'),
        ];

        foreach ($columns as $name => $definition) {
            if (! Schema::hasColumn('configurations', $name)) {
                Schema::table('configurations', $definition);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('configurations')) {
            return;
        }

        $columns = collect([
            'image_cleanup_branding_enabled',
            'image_cleanup_branding_mode',
            'image_cleanup_watermark_text',
            'image_cleanup_watermark_opacity',
        ])->filter(fn (string $column) => Schema::hasColumn('configurations', $column))->all();

        if ($columns) {
            Schema::table('configurations', fn (Blueprint $table) => $table->dropColumn($columns));
        }
    }
};
