<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('packages')) {
            return;
        }

        $columns = [
            'show_solution_pdf_download' => fn (Blueprint $table) => $table->boolean('show_solution_pdf_download')->default(true)->after('show_pdf_download'),
            'solution_pdf_header_text' => fn (Blueprint $table) => $table->string('solution_pdf_header_text')->nullable()->after('pdf_watermark_text'),
            'solution_pdf_footer_text' => fn (Blueprint $table) => $table->string('solution_pdf_footer_text', 500)->nullable()->after('solution_pdf_header_text'),
            'solution_pdf_watermark_text' => fn (Blueprint $table) => $table->string('solution_pdf_watermark_text')->nullable()->after('solution_pdf_footer_text'),
        ];

        foreach ($columns as $column => $definition) {
            if (! Schema::hasColumn('packages', $column)) {
                Schema::table('packages', $definition);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('packages')) {
            return;
        }

        foreach (['solution_pdf_watermark_text', 'solution_pdf_footer_text', 'solution_pdf_header_text', 'show_solution_pdf_download'] as $column) {
            if (Schema::hasColumn('packages', $column)) {
                Schema::table('packages', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }
};