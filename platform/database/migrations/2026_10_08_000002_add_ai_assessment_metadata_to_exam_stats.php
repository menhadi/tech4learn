<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if(!Schema::hasColumn('exam_stats','ai_score')) {
            Schema::table('exam_stats',fn(Blueprint $table)=>$table->decimal('ai_score',12,2)->nullable());
        }
        if(!Schema::hasColumn('exam_stats','ai_providers_used')) {
            Schema::table('exam_stats',fn(Blueprint $table)=>$table->text('ai_providers_used')->nullable());
        }
    }
    public function down(): void
    {
        // Keep assessment evidence during a code rollback, including pre-existing fields.
    }
};
