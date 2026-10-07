<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('email_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('email_settings','founder_from_name')) $table->string('founder_from_name')->nullable();
            if (!Schema::hasColumn('email_settings','founder_from_address')) $table->string('founder_from_address')->nullable();
        });
        if (!Schema::hasTable('email_templates')) return;
        $hasOrg=Schema::hasColumn('email_templates','organization_id');
        $orgs=$hasOrg && Schema::hasTable('organizations') ? DB::table('organizations')->pluck('id')->all() : [null];
        if (!$orgs) $orgs=[null];
        foreach($orgs as $org) foreach([
            ['student_founder_followup','Founder Follow-up Email','A personal message from {#founderName#}','<p>Hello {#studentName#},</p><p>I am {#founderName#}, founder of {#siteName#}. I wanted to personally welcome you.</p><p><a href="{#loginUrl#}">Continue learning</a></p>'],
            ['guest_contact_welcome','Guest Contact Welcome Email','Your exam journey with {#siteName#}','<p>Hello {#guestName#},</p><p>Thank you for trying {#siteName#}. Create your account to save progress.</p><p><a href="{#signupUrl#}">Create your account</a></p>'],
        ] as [$type,$name,$subject,$description]) {
            $key=['type'=>$type]; if($hasOrg) $key['organization_id']=$org;
            $uniqueName = $name . ' [' . ($org ? 'Org ' . $org : 'Platform') . ']';
            DB::table('email_templates')->updateOrInsert($key,array_merge($key,['name'=>$uniqueName,'subject'=>$subject,'description'=>$description,'status'=>'Active','created_at'=>now(),'updated_at'=>now()]));
        }
    }
    public function down(): void {}
};
