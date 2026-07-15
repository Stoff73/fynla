<?php

declare(strict_types=1);

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('trials:send-reminders')->dailyAt('09:00');
        $schedule->command('subscriptions:send-renewal-reminders')->dailyAt('09:00');
        $schedule->command('data-retention:send-warnings')->dailyAt('09:00');
        $schedule->command('trials:expire')->dailyAt('00:05');
        $schedule->command('data-retention:purge-expired')->dailyAt('00:30');
        $schedule->command('registrations:cleanup')->hourly();
        $schedule->command('sessions:cleanup')->dailyAt('02:00');
        $schedule->command('audit:purge')->weeklyOn(0, '03:00');
        $schedule->command('notifications:daily-insight')->dailyAt('08:00');
        $schedule->command('lifecycle:run-daily')->dailyAt('08:30');
        $schedule->command('notifications:policy-renewals')->dailyAt('09:00');
        $schedule->command('protection:send-alerts')->dailyAt('09:15');
        $schedule->command('notifications:mortgage-rate-alerts')->dailyAt('09:30');
        $schedule->command('savings:send-alerts')->dailyAt('10:00');
        $schedule->command('estate:send-alerts')->dailyAt('10:30');
        $schedule->command('subscriptions:check-overdue')->dailyAt('01:00');

        $schedule->job(new \App\Jobs\PublishScheduledInsightsJob())->everyFiveMinutes();

        $schedule->command('pipeline:detect-new-articles')
            ->dailyAt(config('pipeline.detect_schedule', '07:00'));

        $schedule->command('pipeline:detect-new-videos')
            ->dailyAt(config('pipeline.video.detect_schedule', '07:30'));

        // Quarterly (1 Jan / 1 Apr / 1 Jul / 1 Oct) housekeeping reminder.
        $schedule->command('pipeline:audit-social-videos')
            ->cron('0 8 1 1,4,7,10 *');

        // Stage 4 — social scheduling + reporting.
        $schedule->command('pipeline:schedule-ready-posts')->hourly();
        $schedule->command('pipeline:recalculate-optimal-times')->weeklyOn(1, '06:00');
        $schedule->command('pipeline:weekly-social-report')->weeklyOn(
            (int) config('pipeline.reports.weekly_social_schedule_day', 1),
            (string) config('pipeline.reports.weekly_social_schedule_time', '09:00'),
        );
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
