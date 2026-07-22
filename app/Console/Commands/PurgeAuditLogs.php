<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AppleNotificationLog;
use App\Models\AuditLog;
use Carbon\Carbon;
use Illuminate\Console\Command;

class PurgeAuditLogs extends Command
{
    private const DELETE_BATCH_SIZE = 5000;

    protected $signature = 'audit:purge
                            {--dry-run : Show what would be deleted without actually deleting}
                            {--days= : Override default retention days}';

    protected $description = 'Purge old audit logs based on retention policy';

    public function handle(): int
    {
        $defaultRetentionDays = config('auth.audit.retention_days', 90);
        $gdprRetentionDays = config('auth.audit.gdpr_retention_days', 2555);
        $appleNotificationRetentionDays = config('retention.apple_notification_audit_days', 2555);

        $retentionDays = $this->option('days') ? (int) $this->option('days') : $defaultRetentionDays;
        $isDryRun = $this->option('dry-run');

        $this->info('Audit Log Purge');
        $this->info('===============');
        $this->info("Standard retention: {$retentionDays} days");
        $this->info("GDPR retention: {$gdprRetentionDays} days");
        $this->info("Apple notification audit: {$appleNotificationRetentionDays} days");

        if ($isDryRun) {
            $this->warn('DRY RUN - No records will be deleted');
        }

        // Standard logs (non-GDPR)
        $standardCutoff = Carbon::now()->subDays($retentionDays);
        $standardQuery = AuditLog::where('created_at', '<', $standardCutoff)
            ->where('event_type', '!=', AuditLog::EVENT_GDPR);

        $standardCount = $standardQuery->count();

        // GDPR logs have longer retention
        $gdprCutoff = Carbon::now()->subDays($gdprRetentionDays);
        $gdprQuery = AuditLog::where('created_at', '<', $gdprCutoff)
            ->where('event_type', AuditLog::EVENT_GDPR);

        $gdprCount = $gdprQuery->count();

        $appleNotificationCutoff = Carbon::now()->subDays($appleNotificationRetentionDays);
        $appleNotificationQuery = AppleNotificationLog::where('created_at', '<', $appleNotificationCutoff);
        $appleNotificationCount = $appleNotificationQuery->count();

        $this->newLine();
        $this->table(
            ['Log Type', 'Cutoff Date', 'Records to Delete'],
            [
                ['Standard', $standardCutoff->toDateString(), $standardCount],
                ['GDPR', $gdprCutoff->toDateString(), $gdprCount],
                ['Apple notification', $appleNotificationCutoff->toDateString(), $appleNotificationCount],
            ]
        );

        $totalCount = $standardCount + $gdprCount + $appleNotificationCount;

        if ($totalCount === 0) {
            $this->info('No audit logs to purge.');

            return Command::SUCCESS;
        }

        if (! $isDryRun) {
            $standardQuery->delete();
            $gdprQuery->delete();
            $appleNotificationDeleted = $this->pruneAppleNotificationLogs($appleNotificationCutoff);

            $this->info("Deleted {$totalCount} audit log records.");

            \Log::info('Audit logs purged', [
                'standard_deleted' => $standardCount,
                'gdpr_deleted' => $gdprCount,
                'apple_notification_deleted' => $appleNotificationDeleted,
                'total_deleted' => $totalCount,
            ]);
        } else {
            $this->info("Would delete {$totalCount} audit log records.");
        }

        return Command::SUCCESS;
    }

    private function pruneAppleNotificationLogs(Carbon $cutoff): int
    {
        $total = 0;

        do {
            $deleted = AppleNotificationLog::query()
                ->where('created_at', '<', $cutoff)
                ->limit(self::DELETE_BATCH_SIZE)
                ->delete();
            $total += $deleted;
        } while ($deleted >= self::DELETE_BATCH_SIZE);

        return $total;
    }
}
