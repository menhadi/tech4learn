<?php

namespace App\Console\Commands;

use App\Models\Student;
use App\Services\{StudentAccountSupportEmailService,StudentLifecycleEmailService};
use Illuminate\Console\Command;

class ProcessStudentLifecycleEmails extends Command
{
    protected $signature='students:process-lifecycle-emails';
    protected $description='Send delayed pending OTP alerts and founder follow-ups';
    public function handle(StudentAccountSupportEmailService $support, StudentLifecycleEmailService $lifecycle): int
    {
        $pendingQuery = Student::where('status','Pending')->where('updated_at','<=',now()->subMinutes(2))->whereNull('pending_admin_notified_at');
        $pendingEligible = (clone $pendingQuery)->count();
        $pendingSent = 0;
        $pendingQuery->chunkById(100, function ($rows) use ($support, &$pendingSent) {
            $rows->each(function ($student) use ($support, &$pendingSent) {
                if ($support->notifyAdminPendingVerificationOnce($student)) $pendingSent++;
            });
        });

        $founderQuery = Student::where('status','Active')->whereNull('founder_followup_sent_at')->where(function ($query) {
            $cutoff = now()->subHour();
            $query->where(fn ($q) => $q->whereNotNull('welcome_email_sent_at')->where('welcome_email_sent_at','<=',$cutoff))
                ->orWhere(fn ($q) => $q->whereNotNull('admin_activated_at')->where('admin_activated_at','<=',$cutoff));
        });
        $founderEligible = (clone $founderQuery)->count();
        $founderSent = 0;
        $founderQuery->chunkById(100, function ($rows) use ($lifecycle, &$founderSent) {
            $rows->each(function ($student) use ($lifecycle, &$founderSent) {
                if ($lifecycle->sendFounderFollowup($student)) $founderSent++;
            });
        });

        $this->info("Pending admin alerts: {$pendingEligible} eligible, {$pendingSent} sent.");
        $this->info("Founder follow-ups: {$founderEligible} eligible, {$founderSent} sent.");
        return self::SUCCESS;
    }}
