<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('configurations') && ! Schema::hasColumn('configurations', 'student_welcome_email_enabled')) {
            Schema::table('configurations', function (Blueprint $table) {
                $table->boolean('student_welcome_email_enabled')->default(true)->after('allow_guest_exam_attempts');
            });
        }

        if (Schema::hasTable('email_templates')) {
            if (! Schema::hasColumn('email_templates', 'subject')) {
                Schema::table('email_templates', function (Blueprint $table) {
                    $table->string('subject')->nullable()->after('name');
                });
            }

            $organizationId = Schema::hasTable('organizations')
                ? DB::table('organizations')->where('slug', 'examelite')->value('id')
                : null;

            $body = <<<'HTML'
<p>Hello {#studentName#},</p>
<p>Welcome to {#siteName#}. Your student account is ready.</p>
<p>You can open your exams, explore available practice packages, and continue your preparation from your dashboard.</p>
<p><a href="{#myExamsUrl#}" style="display:inline-block;background:{#primaryColor#};color:#ffffff;text-decoration:none;padding:12px 18px;border-radius:8px;font-weight:700;">Open My Exams</a></p>
<p style="margin-top:18px;">Useful links:</p>
<ul>
    <li><a href="{#myExamsUrl#}">My Exams</a></li>
    <li><a href="{#coursesUrl#}">Explore Courses</a></li>
</ul>
<p>{#groupBlock#}</p>
<p>This is a one-time welcome email sent after registration. We are glad to have you here.</p>
<p>Regards,<br>{#organizationName#}</p>
HTML;

            $attributes = ['type' => 'student_welcome'];

            if (Schema::hasColumn('email_templates', 'organization_id') && $organizationId) {
                $attributes['organization_id'] = $organizationId;
            }

            $existing = DB::table('email_templates')
                ->where('type', 'student_welcome')
                ->when(Schema::hasColumn('email_templates', 'organization_id') && $organizationId, function ($query) use ($organizationId) {
                    $query->where('organization_id', $organizationId);
                })
                ->first();

            $existingByName = $existing ?: DB::table('email_templates')
                ->where('name', 'Student Welcome Email')
                ->first();

            if (! $existingByName) {
                DB::table('email_templates')->insert(array_merge($attributes, [
                    'name' => 'Student Welcome Email',
                    'subject' => 'Welcome to {#siteName#}',
                    'description' => $body,
                    'status' => 'Active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            } else {
                $updates = [
                    'type' => 'student_welcome',
                    'updated_at' => now(),
                ];

                if (Schema::hasColumn('email_templates', 'subject') && empty($existingByName->subject)) {
                    $updates['subject'] = 'Welcome to {#siteName#}';
                }

                DB::table('email_templates')
                    ->where('id', $existingByName->id)
                    ->update($updates);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('configurations') && Schema::hasColumn('configurations', 'student_welcome_email_enabled')) {
            Schema::table('configurations', function (Blueprint $table) {
                $table->dropColumn('student_welcome_email_enabled');
            });
        }

        if (Schema::hasTable('email_templates') && Schema::hasColumn('email_templates', 'subject')) {
            Schema::table('email_templates', function (Blueprint $table) {
                $table->dropColumn('subject');
            });
        }
    }
};
