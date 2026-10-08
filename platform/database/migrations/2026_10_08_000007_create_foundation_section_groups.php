<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('groups',fn(Blueprint $t)=>$t->unique(['organization_id','id'],'groups_foundation_pair'));
        Schema::create('foundation_section_groups',function(Blueprint $t){
            $t->unsignedBigInteger('organization_id');$t->uuid('section_id');$t->unsignedBigInteger('group_id');
            $t->boolean('active')->default(true);$t->unsignedBigInteger('version')->default(1);$t->timestamps();
            $t->primary(['organization_id','section_id']);
            $t->foreign(['organization_id','group_id'])->references(['organization_id','id'])->on('groups');
        });
        Schema::create('foundation_student_group_deliveries',function(Blueprint $t){
            $t->unsignedBigInteger('organization_id');$t->uuid('learner_id');$t->unsignedBigInteger('student_id');
            $t->uuid('section_id');$t->unsignedBigInteger('group_id')->nullable();$t->unsignedBigInteger('pivot_id')->nullable();
            $t->unsignedBigInteger('revision');$t->timestamps();$t->primary(['organization_id','learner_id']);
            $t->foreign(['organization_id','learner_id'])->references(['organization_id','learner_id'])->on('foundation_student_profiles');
            $t->foreign(['organization_id','student_id'])->references(['organization_id','id'])->on('students');
            $t->foreign(['organization_id','group_id'])->references(['organization_id','id'])->on('groups');
            // No cascade on pivot: disappearance must be detected as an explicit review condition.
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('foundation_student_group_deliveries');Schema::dropIfExists('foundation_section_groups');
        Schema::table('groups',fn(Blueprint $t)=>$t->dropUnique('groups_foundation_pair'));
    }
};
