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

        if (! Schema::hasColumn('packages', 'pdf_header_text')) {
            Schema::table('packages', function (Blueprint $table) {
                $table->string('pdf_header_text')->nullable()->after('show_pdf_download');
            });
        }

        if (! Schema::hasColumn('packages', 'pdf_footer_text')) {
            Schema::table('packages', function (Blueprint $table) {
                $table->string('pdf_footer_text', 500)->nullable()->after('pdf_header_text');
            });
        }

        if (! Schema::hasColumn('packages', 'pdf_watermark_text')) {
            Schema::table('packages', function (Blueprint $table) {
                $table->string('pdf_watermark_text')->nullable()->after('pdf_footer_text');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('packages')) {
            return;
        }

        foreach (['pdf_watermark_text', 'pdf_footer_text', 'pdf_header_text'] as $column) {
            if (Schema::hasColumn('packages', $column)) {
                Schema::table('packages', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }
};