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

        // S0.11.3 — clean up expired idempotency rows once a day.
        $schedule->job(new \App\Jobs\AiIdempotencyCleanupJob())->dailyAt('03:30');

        // S0.12 — weekly retention sweep + chain-integrity health check.
        $schedule->job(new \App\Jobs\AiAuditRetentionJob())->weeklyOn(0, '04:00');
        $schedule->command('ai:audit:verify-chain')->weeklyOn(0, '04:30');

        // S1.3 — every 30 minutes, dispatch summariser jobs for any
        // conversation whose index is missing or behind the latest
        // message. Idle-minutes default keeps in-flight chats out.
        $schedule->command('ai:conversations:summarise-stale')->everyThirtyMinutes();
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
