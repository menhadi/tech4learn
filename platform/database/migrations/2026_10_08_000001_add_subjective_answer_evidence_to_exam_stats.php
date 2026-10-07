<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        foreach (['uploaded_answer_path','extracted_answer_text','ai_assessed'] as $column) {
            if (Schema::hasColumn('exam_stats',$column)) continue;
            Schema::table('exam_stats',function(Blueprint $table)use($column){
                if($column==='ai_assessed')$table->boolean($column)->default(false);
                else $table->text($column)->nullable();
            });
        }
    }
    public function down(): void
    {
        // Preserve answer evidence on code rollback, including pre-existing columns.
    }
};
