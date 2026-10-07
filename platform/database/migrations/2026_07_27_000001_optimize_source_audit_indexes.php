<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_quality_audits', function (Blueprint $table) {
            $table->index(['organization_id', 'status', 'created_at'], 'quality_audits_org_status_created_idx');
        });
        Schema::table('exam_quality_findings', function (Blueprint $table) {
            $table->index(['organization_id', 'status'], 'quality_findings_org_status_idx');
        });
        Schema::table('source_exam_imports', function (Blueprint $table) {
            $table->index(['organization_id', 'status', 'created_at'], 'source_imports_org_status_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('exam_quality_audits', function (Blueprint $table) {
            $table->dropIndex('quality_audits_org_status_created_idx');
        });
        Schema::table('exam_quality_findings', function (Blueprint $table) {
            $table->dropIndex('quality_findings_org_status_idx');
        });
        Schema::table('source_exam_imports', function (Blueprint $table) {
            $table->dropIndex('source_imports_org_status_created_idx');
        });
    }
};
