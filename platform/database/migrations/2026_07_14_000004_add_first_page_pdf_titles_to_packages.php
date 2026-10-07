<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('packages', 'pdf_title_text')) {
            Schema::table('packages', function (Blueprint $table) {
                $table->string('pdf_title_text')->nullable()->after('show_pdf_download');
            });
        }

        if (! Schema::hasColumn('packages', 'solution_pdf_title_text')) {
            Schema::table('packages', function (Blueprint $table) {
                $table->string('solution_pdf_title_text')->nullable()->after('show_solution_pdf_download');
            });
        }
    }

    public function down(): void
    {
        foreach (['solution_pdf_title_text', 'pdf_title_text'] as $column) {
            if (Schema::hasColumn('packages', $column)) {
                Schema::table('packages', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};