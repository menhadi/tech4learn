<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('question_share_batches')) {
            Schema::create('question_share_batches', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('source_organization_id')->nullable()->index();
                $table->unsignedBigInteger('target_organization_id')->nullable()->index();
                $table->string('direction', 40)->index();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->unsignedInteger('question_count')->default(0);
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('question_share_items')) {
            Schema::create('question_share_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('batch_id')->index();
                $table->unsignedBigInteger('source_question_id')->index();
                $table->unsignedBigInteger('target_question_id')->nullable()->index();
                $table->string('status', 30)->default('copied');
                $table->text('message')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('question_share_items');
        Schema::dropIfExists('question_share_batches');
    }
};
