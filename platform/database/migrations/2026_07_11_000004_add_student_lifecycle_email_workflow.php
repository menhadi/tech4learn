<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('configurations', function (Blueprint $table) {
            if (!Schema::hasColumn('configurations','require_student_registration_number')) $table->boolean('require_student_registration_number')->default(false);
            if (!Schema::hasColumn('configurations','student_founder_followup_enabled')) $table->boolean('student_founder_followup_enabled')->default(true);
            if (!Schema::hasColumn('configurations','guest_contact_email_enabled')) $table->boolean('guest_contact_email_enabled')->default(true);
        });
        Schema::table('students', function (Blueprint $table) {
            if (!Schema::hasColumn('students','founder_followup_sent_at')) $table->timestamp('founder_followup_sent_at')->nullable();
        });
        Schema::table('exam_results', function (Blueprint $table) {
            if (!Schema::hasColumn('exam_results','guest_contact_email_sent_at')) $table->timestamp('guest_contact_email_sent_at')->nullable();
        });
        if (Schema::hasTable('email_templates')) {
            $orgColumn=Schema::hasColumn('email_templates','organization_id');
            $orgId=$orgColumn && Schema::hasTable('organizations') ? DB::table('organizations')->value('id') : null;
            foreach ([
                ['student_founder_followup','Founder Follow-up Email','A personal message from {#founderName#}','<p>Hello {#studentName#},</p><p>I am {#founderName#}, founder of {#siteName#}. I wanted to personally welcome you and ask if there is anything we can do to improve your preparation experience.</p><p><a href="{#loginUrl#}">Continue learning</a></p>'],
                ['guest_contact_welcome','Guest Contact Welcome Email','Your exam journey with {#siteName#}','<p>Hello {#guestName#},</p><p>Thank you for trying an exam on {#siteName#}. Create your free account to save progress and access relevant packages.</p><p><a href="{#signupUrl#}">Create your account</a></p>'],
            ] as [$type,$name,$subject,$description]) {
                $key=['type'=>$type]; if($orgColumn) $key['organization_id']=$orgId;
                $uniqueName = $name . ' [' . ($orgId ? 'Org ' . $orgId : 'Platform') . ']';
                DB::table('email_templates')->updateOrInsert($key,array_merge($key,['name'=>$uniqueName,'subject'=>$subject,'description'=>$description,'status'=>'Active','updated_at'=>now(),'created_at'=>now()]));
            }
        }
    }
    public function down(): void {}
};
