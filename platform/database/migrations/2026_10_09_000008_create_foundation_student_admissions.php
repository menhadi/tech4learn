<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('foundation_student_admissions',function(Blueprint $table) {
            $table->unsignedBigInteger('organization_id');
            $table->uuid('admission_id');
            $table->unsignedBigInteger('student_id');
            $table->string('state',24)->default('awaiting_review');
            $table->unsignedBigInteger('version')->default(1);
            $table->timestamps();
            $table->primary(['organization_id','admission_id']);
            $table->unique(['organization_id','student_id'],'foundation_admission_student_unique');
            $table->foreign(['organization_id','student_id'],'foundation_admission_student_fk')
                ->references(['organization_id','id'])->on('students');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('foundation_student_admissions');
    }
};
