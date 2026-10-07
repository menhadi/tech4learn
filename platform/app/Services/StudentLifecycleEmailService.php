<?php

namespace App\Services;

use App\Models\{Configuration,EmailSetting,EmailTemplate,ExamResult,Student};
use Illuminate\Support\Facades\Schema;

class StudentLifecycleEmailService
{
    public function __construct(private EmailService $emailService) {}

    public function sendFounderFollowup(Student $student): bool
    {
        if (!$student->email || $student->founder_followup_sent_at) return false;
        $config=$this->config($student->organization_id);
        if ($config && Schema::hasColumn('configurations','student_founder_followup_enabled') && !$config->student_founder_followup_enabled) return false;
        $tokens=$this->tokens($config,['{#studentName#}'=>e($student->name ?: 'Student')]);
        $template=$this->template('student_founder_followup',$student->organization_id);
        $mail=EmailSetting::where('organization_id',$student->organization_id)->first();
        $sent=$this->emailService->sendEmail($student->email,$this->replace($template?->subject ?: 'A personal welcome from {#founderName#}',$tokens),$this->replace($template?->description ?: '<p>Welcome to {#siteName#}.</p>',$tokens),$mail?->founder_from_address,$mail?->founder_from_name,$student->organization_id);
        if($sent) $student->forceFill(['founder_followup_sent_at'=>now()])->save();
        return $sent;
    }

    public function sendGuestWelcome(ExamResult $result): bool
    {
        if (!$result->guest_email || $result->guest_contact_email_sent_at) return false;
        if (ExamResult::where('organization_id',$result->organization_id)->where('guest_email',$result->guest_email)->whereNotNull('guest_contact_email_sent_at')->where('id','!=',$result->id)->exists()) return false;
        $config=$this->config($result->organization_id);
        if ($config && Schema::hasColumn('configurations','guest_contact_email_enabled') && !$config->guest_contact_email_enabled) return false;
        $tokens=$this->tokens($config,['{#guestName#}'=>e($result->guest_name ?: 'Learner')]);
        $template=$this->template('guest_contact_welcome',$result->organization_id);
        $sent=$this->emailService->sendEmail($result->guest_email,$this->replace($template?->subject ?: 'Continue learning with {#siteName#}',$tokens),$this->replace($template?->description ?: '<p>Thank you for trying {#siteName#}.</p>',$tokens),null,null,$result->organization_id);
        if($sent) $result->forceFill(['guest_contact_email_sent_at'=>now()])->save();
        return $sent;
    }
    private function config($org): ?Configuration { return $org ? Configuration::where('organization_id',$org)->first() : Configuration::whereNull('organization_id')->first() ?? Configuration::first(); }
    private function template(string $type,$org): ?EmailTemplate { return EmailTemplate::where('type',$type)->where('status','Active')->when(Schema::hasColumn('email_templates','organization_id'),fn($q)=>$q->where(fn($x)=>$x->where('organization_id',$org)->orWhereNull('organization_id'))->orderByRaw('organization_id is null'))->latest()->first(); }
    private function tokens(?Configuration $c,array $extra): array { $site=$c?->name ?: config('app.name','Examways'); return $extra+['{#siteName#}'=>e($site),'{#founderName#}'=>e($c?->author ?: 'Founder'),'{#primaryColor#}'=>e($c?->theme_primary_color ?: '#0f766e'),'{#secondaryColor#}'=>e($c?->theme_secondary_color ?: '#f59e0b'),'{#loginUrl#}'=>route('student.signin'),'{#signupUrl#}'=>route('student.signup')]; }
    private function replace(string $text,array $tokens): string { return str_replace(array_keys($tokens),array_values($tokens),$text); }
}
