<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('email_templates')) {
            return;
        }

        $body = <<<'HTML'
<div style="margin:0;background:#f3f8f7;color:#111827;font-family:Arial,Helvetica,sans-serif;">
    <div style="max-width:680px;margin:0 auto;padding:26px 14px;">
        <div style="background:#ffffff;border:1px solid #d7e2df;border-radius:14px;overflow:hidden;">
            <div style="padding:28px 28px 24px;background:{#primaryColor#};color:#ffffff;">
                <div style="font-size:24px;font-weight:800;line-height:1.25;">Welcome to {#siteName#}</div>
                <div style="font-size:14px;margin-top:8px;opacity:.92;">Your exam practice account is ready</div>
            </div>

            <div style="padding:26px 28px;">
                <p style="font-size:16px;line-height:1.6;margin:0 0 16px;">Hello {#studentName#},</p>

                <p style="font-size:16px;line-height:1.6;margin:0 0 16px;">
                    Your account is ready. You can now practice exams, mock tests and previous year papers from your student dashboard.
                </p>

                <div style="background:#f8fafc;border:1px solid #d7e2df;border-radius:12px;padding:18px;margin:20px 0;">
                    <div style="font-size:16px;font-weight:800;margin-bottom:12px;color:#111827;">What you can do on {#siteName#}</div>
                    <div style="font-size:15px;line-height:1.8;color:#334155;">
                        <div style="margin-bottom:8px;"><strong style="color:{#primaryColor#};">Language choice:</strong> Take available exams in the language enabled by your institute.</div>
                        <div style="margin-bottom:8px;"><strong style="color:{#primaryColor#};">Practice library:</strong> Access mock tests, PYP and exam packages from your dashboard.</div>
                        <div style="margin-bottom:8px;"><strong style="color:{#primaryColor#};">Subjective practice:</strong> Attempt subjective questions when they are available in your exams.</div>
                        <div><strong style="color:{#primaryColor#};">Question PDFs:</strong> Download question papers when PDF download is enabled by your institute.</div>
                    </div>
                </div>

                <p style="font-size:15px;line-height:1.6;margin:0 0 16px;">{#groupBlock#}</p>

                <div style="margin:24px 0 10px;">
                    <a href="{#myExamsUrl#}" style="display:inline-block;background:{#primaryColor#};color:#ffffff;text-decoration:none;font-weight:800;border-radius:8px;padding:13px 20px;margin:0 8px 10px 0;">
                        Open My Exams
                    </a>
                    <a href="{#coursesUrl#}" style="display:inline-block;background:{#secondaryColor#};color:#ffffff;text-decoration:none;font-weight:800;border-radius:8px;padding:13px 20px;margin:0 0 10px 0;">
                        Browse Courses
                    </a>
                </div>

                <p style="font-size:14px;line-height:1.6;color:#5f6b7a;margin:20px 0 0;">
                    This is a one-time welcome email after your registration. If you did not create this account, you can ignore this message.
                </p>
            </div>

            <div style="padding:16px 28px;border-top:1px solid #e5e7eb;color:#6b7280;font-size:13px;">
                Regards,<br>
                {#organizationName#}
            </div>
        </div>
    </div>
</div>
HTML;

        $query = DB::table('email_templates')->where('type', 'student_welcome');

        if (! $query->exists()) {
            return;
        }

        $update = [
            'description' => $body,
            'name' => 'Student Welcome Email',
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('email_templates', 'subject')) {
            $update['subject'] = 'Welcome to {#siteName#}';
        }

        $query->update($update);
    }

    public function down(): void
    {
        //
    }
};
