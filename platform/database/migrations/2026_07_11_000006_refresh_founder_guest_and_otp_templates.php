<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('email_templates')) return;
        $templates = [
            'student_founder_followup' => ['A personal message from {#founderName#}', $this->card('A personal welcome','A note from the founder','<p>Hello {#studentName#},</p><p>I am <strong>{#founderName#}</strong>, founder of {#siteName#}. Thank you for joining us. I would genuinely value hearing about your preparation goals and how we can serve you better.</p><div style="margin:24px 0"><a href="{#loginUrl#}" style="display:inline-block;background:{#primaryColor#};color:#fff;text-decoration:none;font-weight:700;border-radius:8px;padding:13px 22px">Continue learning</a></div><p style="color:#64748b">Warm regards,<br><strong>{#founderName#}</strong><br>{#siteName#}</p>')],
            'guest_contact_welcome' => ['Continue your exam journey with {#siteName#}', $this->card('Thanks for practising with us','Your progress can continue','<p>Hello {#guestName#},</p><p>Thank you for attempting an exam on {#siteName#}. Create your free account to save progress, review results and discover packages matched to your preparation.</p><div style="margin:24px 0"><a href="{#signupUrl#}" style="display:inline-block;background:{#primaryColor#};color:#fff;text-decoration:none;font-weight:700;border-radius:8px;padding:13px 22px">Create free account</a></div><p style="font-size:13px;color:#64748b">You received this email because this address was submitted during a guest exam attempt.</p>')],
            'otp' => ['{#otp#} is your {#siteName#} verification code', $this->card('Verify your email','Your secure one-time verification code','<p>Hello {#studentName#},</p><p>Use the code below to complete your registration:</p><div style="margin:24px 0;padding:18px;text-align:center;background:#f8fafc;border:1px dashed {#primaryColor#};border-radius:12px;font-size:34px;font-weight:800;letter-spacing:8px;color:{#primaryColor#}">{#otp#}</div><p>This code expires shortly. Never share it with anyone.</p><p style="font-size:13px;color:#64748b">If you did not request this code, you can safely ignore this email.</p>')],
        ];
        foreach ($templates as $type => [$subject,$body]) DB::table('email_templates')->where('type',$type)->update(['subject'=>$subject,'description'=>$body,'status'=>'Active','updated_at'=>now()]);
        $hasOrg=Schema::hasColumn('email_templates','organization_id');
        $orgs=$hasOrg && Schema::hasTable('organizations') ? DB::table('organizations')->pluck('id')->all() : [null];
        foreach($orgs ?: [null] as $org) foreach($templates as $type=>[$subject,$body]) {
            $query=DB::table('email_templates')->where('type',$type); if($hasOrg) $query->where('organization_id',$org);
            if(!$query->exists()) DB::table('email_templates')->insert(array_filter(['organization_id'=>$hasOrg?$org:null,'name'=>ucwords(str_replace('_',' ',$type)).' ['.($org?'Org '.$org:'Platform').']','type'=>$type,'subject'=>$subject,'description'=>$body,'status'=>'Active','created_at'=>now(),'updated_at'=>now()],fn($v,$k)=>$hasOrg||$k!=='organization_id',ARRAY_FILTER_USE_BOTH));
        }
    }
    private function card(string $title,string $subtitle,string $content): string
    {
        return '<div style="margin:0;background:#f3f8f7;color:#111827;font-family:Arial,Helvetica,sans-serif"><div style="max-width:680px;margin:0 auto;padding:26px 14px"><div style="background:#fff;border:1px solid #d7e2df;border-radius:14px;overflow:hidden"><div style="padding:28px;background:{#primaryColor#};color:#fff"><div style="font-size:24px;font-weight:800">'.$title.'</div><div style="font-size:14px;margin-top:8px;opacity:.92">'.$subtitle.'</div></div><div style="padding:28px;font-size:16px;line-height:1.7">'.$content.'</div><div style="padding:16px 28px;border-top:1px solid #e5e7eb;color:#64748b;font-size:13px">{#siteName#} &bull; Secure exam preparation</div></div></div></div>';
    }
    public function down(): void {}
};
