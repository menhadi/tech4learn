<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('source_question_adapter_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('key', 64);
            $table->string('url_pattern', 500);
            $table->json('selectors');
            $table->boolean('enabled')->default(true)->index();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['organization_id', 'key']);
        });

        Schema::table('source_question_import_runs', function (Blueprint $table) {
            $table->string('mode', 16)->default('import')->after('status')->index();
        });

        Schema::table('source_question_import_items', function (Blueprint $table) {
            $table->string('source_url_hash', 64)->nullable()->after('source_url')->index();
            $table->json('audit_result')->nullable()->after('payload');
            $table->timestamp('audited_at')->nullable()->after('fetched_at');
            $table->timestamp('repaired_at')->nullable()->after('audited_at');
        });
    }

    public function down(): void
    {
        Schema::table('source_question_import_items', function (Blueprint $table) {
            $table->dropIndex(['source_url_hash']);
            $table->dropColumn(['source_url_hash', 'audit_result', 'audited_at', 'repaired_at']);
        });
        Schema::table('source_question_import_runs', function (Blueprint $table) {
            $table->dropIndex(['mode']);
            $table->dropColumn('mode');
        });
        Schema::dropIfExists('source_question_adapter_profiles');
    }
};
