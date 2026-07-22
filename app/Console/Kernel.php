<?php

declare(strict_types=1);

namespace App\Console;

use App\Jobs\AiAuditRetentionJob;
use App\Jobs\AiIdempotencyCleanupJob;
use App\Jobs\PublishScheduledInsightsJob;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('subscriptions:send-renewal-reminders')->dailyAt('09:00');
        $schedule->command('data-retention:send-warnings')->dailyAt('09:00');
        $schedule->command('subscriptions:expire')->dailyAt('00:05');
        $schedule->command('accounts:execute-scheduled-deletions')->dailyAt('00:10');
        $schedule->command('accounts:execute-grace-deletions')->dailyAt('00:15');
        $schedule->command('accounts:send-deletion-reminders')->dailyAt('00:20');
        $schedule->command('accounts:purge-after-retention')->monthlyOn(1, '02:00');
        $schedule->command('registrations:cleanup')->hourly();
        $schedule->command('sessions:cleanup')->dailyAt('02:00');
        $schedule->command('audit:purge')->weeklyOn(0, '03:00');
        // CoALA Phase 5 FR-M10 — pause + consolidate conversations idle 3+ min.
        $schedule->command('ai:conversations:summarise-stale --idle-minutes=3 --pause')
            ->everyThreeMinutes()
            ->withoutOverlapping();
        $schedule->command('notifications:daily-insight')->dailyAt('08:00');
        $schedule->command('lifecycle:run-daily')->dailyAt('08:30');
        $schedule->command('notifications:policy-renewals')->dailyAt('09:00');
        $schedule->command('protection:send-alerts')->dailyAt('09:15');
        $schedule->command('notifications:mortgage-rate-alerts')->dailyAt('09:30');
        $schedule->command('savings:send-alerts')->dailyAt('10:00');
        $schedule->command('estate:send-alerts')->dailyAt('10:30');
        $schedule->command('subscriptions:check-overdue')->dailyAt('01:00');
        $schedule->command('payments:reconcile-pending --older-than=15')->everyTenMinutes()->withoutOverlapping();

        $schedule->job(new PublishScheduledInsightsJob)->everyFiveMinutes();

        // S0.11.3 — clean up expired idempotency rows once a day.
        $schedule->job(new AiIdempotencyCleanupJob)->dailyAt('03:30');

        // S0.12 — weekly retention sweep + chain-integrity health check.
        $schedule->job(new AiAuditRetentionJob)->weeklyOn(0, '04:00');
        $schedule->command('ai:audit:verify-chain')->weeklyOn(0, '04:30');

        // S1.3 — every 30 minutes, dispatch summariser jobs for any
        // conversation whose index is missing or behind the latest
        // message. Idle-minutes default keeps in-flight chats out.
        $schedule->command('ai:conversations:summarise-stale')->everyThirtyMinutes();

        // CoALA Phase 2 — FCA SYSC 9.1 episodic retention.
        // Nightly orphan reconcile (flag only) + weekly cold-archive of >12mo
        // blobs. Purge (6-year hard delete) is manual / --force only.
        $schedule->command('fyn:episodic:reconcile')->daily();
        $schedule->command('fyn:episodic:cold-archive')->weekly();

        // Marketing pipeline — article + video detection. Polled every few
        // minutes (config('pipeline.poll_frequency_minutes', 5)) so new Drive
        // files / published articles enter the pipeline within minutes without
        // waiting for a daily scan. The Drive webhook (when configured) triggers
        // the same commands instantly; this polling is the always-on safety net.
        // withoutOverlapping stops a slow run stacking on the next tick.
        $pollEvery = max(1, (int) config('pipeline.poll_frequency_minutes', 5));

        $schedule->command('pipeline:detect-new-article-docs')
            ->cron('*/'.$pollEvery.' * * * *')->withoutOverlapping();

        $schedule->command('pipeline:detect-new-articles')
            ->cron('*/'.$pollEvery.' * * * *')->withoutOverlapping();

        // CMS DocumentArticles → same script pipeline.
        $schedule->command('pipeline:detect-new-document-articles')
            ->cron('*/'.$pollEvery.' * * * *')->withoutOverlapping();

        $schedule->command('pipeline:detect-new-videos')
            ->cron('*/'.$pollEvery.' * * * *')->withoutOverlapping();

        // Real-time Drive trigger — re-register the change webhook before it
        // expires (no-op when PIPELINE_DRIVE_WEBHOOK_URL is unset).
        $schedule->command('pipeline:drive-watch')->dailyAt('05:00')->withoutOverlapping();

        // Quarterly (1 Jan / 1 Apr / 1 Jul / 1 Oct) housekeeping reminder.
        $schedule->command('pipeline:audit-social-videos')
            ->cron('0 8 1 1,4,7,10 *');

        // Stage 3.5 — auto-approve clip approvals near their scheduled post time.
        $schedule->command('pipeline:auto-approve-clips')
            ->cron('*/'.((int) config('pipeline.clip_approval.auto_approve_cron_frequency_minutes', 5)).' * * * *');

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
