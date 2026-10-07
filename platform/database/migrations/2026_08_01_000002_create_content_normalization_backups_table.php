<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_normalization_backups', function (Blueprint $table) {
            $table->id();
            $table->uuid('run_id');
            $table->unsignedBigInteger('organization_id');
            $table->string('record_table', 40);
            $table->unsignedBigInteger('record_id');
            $table->json('original_content');
            $table->json('normalized_content')->nullable();
            $table->char('original_hash', 64);
            $table->string('status', 30);
            $table->json('issues')->nullable();
            $table->timestamps();

            $table->unique(['run_id', 'record_table', 'record_id'], 'content_normalization_run_record_unique');
            $table->index(['organization_id', 'record_table', 'record_id'], 'content_normalization_tenant_record_index');
            $table->index(['organization_id', 'run_id'], 'content_normalization_tenant_run_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_normalization_backups');
    }
};
