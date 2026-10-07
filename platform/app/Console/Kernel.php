<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('questions:process-imports --limit=1')->everyMinute()->withoutOverlapping(120)->runInBackground();
        if (config('native_schedule.lifecycle_emails', true)) {
            $schedule->command('students:process-lifecycle-emails')->everyMinute()->withoutOverlapping();
        }
        $schedule->command('exam-documents:reconcile')->everyFiveMinutes()->withoutOverlapping(10);
        foreach (range(1, config('paper_processing.workers', 4)) as $worker) {
            $schedule->command("exam-quality:process --limit=1 --worker={$worker}")
                ->name("exam-quality-worker-{$worker}")
                ->everyMinute()
                ->withoutOverlapping(30)
                ->runInBackground();
        }
        foreach (range(1, config('paper_processing.workers', 4)) as $worker) {
            $schedule->command("ai-answers:process --limit=1 --worker={$worker}")
                ->name("ai-answer-worker-{$worker}")
                ->everyMinute()
                ->withoutOverlapping(30)
                ->runInBackground();
        }
        foreach (range(1, config('paper_processing.workers', 4)) as $worker) {
            $schedule->command("exam-quality:process-repairs --limit=5 --worker={$worker}")
                ->name("exam-quality-repair-worker-{$worker}")
                ->everyMinute()
                ->withoutOverlapping(30)
                ->runInBackground();
        }
        foreach (range(1, config('paper_processing.workers', 4)) as $worker) {
            $schedule->command("source-exams:process --limit=1 --worker={$worker}")
                ->name("source-exams-worker-{$worker}")
                ->everyMinute()
                ->withoutOverlapping(30)
                ->runInBackground();
        }
        foreach (range(1, min(2, config('paper_processing.workers', 4))) as $worker) {
            $schedule->command("image-cleanup:process --limit=1 --worker={$worker}")
                ->name("image-cleanup-worker-{$worker}")
                ->everyMinute()
                ->withoutOverlapping(30)
                ->runInBackground();
        }
        if (config('native_schedule.search_console', true)) {
            $schedule->command('seo:sync-search-console')
                ->dailyAt('03:30')
                ->withoutOverlapping(60)
                ->onOneServer();
        }
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
