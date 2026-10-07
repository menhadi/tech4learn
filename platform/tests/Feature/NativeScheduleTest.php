<?php

namespace Tests\Feature;

use App\Console\Kernel;
use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class NativeScheduleTest extends TestCase
{
    private function commands(bool $providers): array
    {
        config(['native_schedule.lifecycle_emails'=>$providers,'native_schedule.search_console'=>$providers,'paper_processing.workers'=>1]);
        $schedule=new Schedule;
        (new \ReflectionMethod(Kernel::class,'schedule'))->invoke(app(Kernel::class),$schedule);
        return array_map(fn($event)=>$event->command,$schedule->events());
    }
    public function test_initial_schedule_keeps_exam_workers_without_unverified_provider_jobs(): void
    {
        $commands=implode("\n",$this->commands(false));
        foreach(['questions:process-imports','exam-documents:reconcile','exam-quality:process','ai-answers:process','source-exams:process','image-cleanup:process'] as $command) $this->assertStringContainsString($command,$commands);
        $this->assertStringNotContainsString('students:process-lifecycle-emails',$commands);
        $this->assertStringNotContainsString('seo:sync-search-console',$commands);
        $this->assertStringNotContainsString('--worker=2',$commands);
    }
    public function test_provider_jobs_can_be_enabled_explicitly(): void
    {
        $commands=implode("\n",$this->commands(true));
        $this->assertStringContainsString('students:process-lifecycle-emails',$commands);
        $this->assertStringContainsString('seo:sync-search-console',$commands);
    }
}
