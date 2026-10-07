<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('google_sheet_connections')) {
            return;
        }

        Schema::create('google_sheet_connections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->string('resource', 60);
            $table->string('spreadsheet_id');
            $table->text('spreadsheet_url');
            $table->string('tab_name', 100)->default('Data');
            $table->string('share_email')->nullable();
            $table->json('selected_fields');
            $table->json('filters')->nullable();
            $table->timestamp('last_exported_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'resource']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_sheet_connections');
    }
};
