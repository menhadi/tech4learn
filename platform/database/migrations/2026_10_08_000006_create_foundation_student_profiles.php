<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('students', fn(Blueprint $table) => $table->unique(['organization_id','id'],'students_foundation_pair'));
        Schema::create('foundation_student_profiles', function(Blueprint $table) {
            $table->unsignedBigInteger('organization_id');
            $table->uuid('learner_id');
            $table->unsignedBigInteger('student_id')->unique();
            $table->unsignedBigInteger('revision');
            $table->char('fingerprint',64);
            $table->timestamps();
            $table->primary(['organization_id','learner_id']);
            $table->foreign(['organization_id','student_id'])->references(['organization_id','id'])->on('students');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('foundation_student_profiles');
        Schema::table('students', fn(Blueprint $table) => $table->dropUnique('students_foundation_pair'));
    }
};
