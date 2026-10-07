<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_quality_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20)->default('questions');
            $table->string('kind', 10);
            $table->string('label')->nullable();
            $table->string('file_path')->nullable();
            $table->text('source_url')->nullable();
            $table->string('provider_file_id')->nullable();
            $table->timestamp('provider_uploaded_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['organization_id', 'exam_id', 'role']);
        });

        Schema::table('exam_quality_audits', function (Blueprint $table) {
            $table->boolean('include_source')->default(false)->after('include_visual');
        });
    }

    public function down(): void
    {
        Schema::table('exam_quality_audits', function (Blueprint $table) {
            $table->dropColumn('include_source');
        });
        Schema::dropIfExists('exam_quality_sources');
    }
};
