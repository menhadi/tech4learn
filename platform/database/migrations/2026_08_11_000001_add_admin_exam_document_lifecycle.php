<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('exam_languages', function (Blueprint $table) {
            $table->boolean('auto_translate')->default(false)->after('translation_status');
            $table->boolean('auto_pdf')->default(false)->after('auto_translate');
            $table->timestamp('translation_approved_at')->nullable()->after('translated_at');
            $table->unsignedBigInteger('translation_approved_by')->nullable()->after('translation_approved_at');
        });
        Schema::table('question_langs', fn (Blueprint $table) => $table->char('source_fingerprint', 64)->nullable()->index()->after('translated_by'));
        Schema::table('exam_language_translations', fn (Blueprint $table) => $table->char('source_fingerprint', 64)->nullable()->index()->after('translated_by'));
        Schema::create('exam_pdf_builds', function (Blueprint $table) {
            $table->id(); $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
            $table->foreignId('package_id')->nullable()->constrained('packages')->nullOnDelete();
            $table->foreignId('language_id')->nullable()->constrained('languages')->nullOnDelete();
            $table->enum('document_type', ['questions','solutions']); $table->string('status', 20)->default('queued')->index();
            $table->char('source_fingerprint', 64)->nullable()->index(); $table->text('current_path')->nullable(); $table->text('version_path')->nullable();
            $table->unsignedBigInteger('file_size')->nullable(); $table->unsignedInteger('page_count')->nullable(); $table->text('last_error')->nullable();
            $table->timestamp('queued_at')->nullable(); $table->timestamp('started_at')->nullable(); $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('requested_by')->nullable(); $table->timestamps();
            $table->unique(['exam_id','package_id','language_id','document_type'], 'exam_pdf_build_identity');
        });
        DB::table('exam_languages')->where('translation_status', 'ready')->update(['translation_approved_at'=>DB::raw('COALESCE(translated_at, updated_at)')]);
    }
    public function down(): void {
        Schema::dropIfExists('exam_pdf_builds');
        Schema::table('exam_language_translations', fn (Blueprint $table) => $table->dropColumn('source_fingerprint'));
        Schema::table('question_langs', fn (Blueprint $table) => $table->dropColumn('source_fingerprint'));
        Schema::table('exam_languages', fn (Blueprint $table) => $table->dropColumn(['auto_translate','auto_pdf','translation_approved_at','translation_approved_by']));
    }
};
