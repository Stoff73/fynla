<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Exceptions\Billing\AppleVerificationException;
use App\Models\AppleNotificationLog;
use App\Models\AppleNotificationRecovery;
use App\Models\User;
use App\Services\Billing\Apple\AppleReconciliationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

final class RecoverAppleNotifications extends Command
{
    protected $signature = 'apple:notifications:recover {--limit=100}';

    protected $description = 'Retry verified App Store notification evidence that needs reconciliation';

    public function handle(): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 500],
        ]);
        if ($limit === false) {
            $this->error('The recovery limit must be between 1 and 500.');

            return self::INVALID;
        }

        $recoveries = AppleNotificationRecovery::query()
            ->whereIn('status', [
                AppleNotificationRecovery::STATUS_PENDING,
                AppleNotificationRecovery::STATUS_FAILED,
            ])
            ->where(function ($query): void {
                $query->whereNull('next_attempt_at')
                    ->orWhere('next_attempt_at', '<=', now());
            })
            ->orderByRaw('COALESCE(next_attempt_at, created_at) ASC')
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->all();

        foreach ($recoveries as $recovery) {
            $user = $recovery->user()->first();

            if (! $user instanceof User) {
                $this->markRejected($recovery, 'invalid_signed_data');

                continue;
            }

            $this->recordAttempt($recovery);

            try {
                app(AppleReconciliationService::class)->reconcile(
                    $user,
                    $recovery->original_transaction_id,
                );
            } catch (AuthorizationException) {
                $this->markRejected($recovery, 'invalid_signed_data');

                continue;
            } catch (AppleVerificationException $exception) {
                if (
                    $exception->retryable
                    || $exception->errorCode === 'invalid_configuration'
                ) {
                    $this->markFailed($recovery, $exception->errorCode);
                } else {
                    $this->markRejected($recovery, $exception->errorCode);
                }

                continue;
            } catch (Throwable) {
                $this->markFailed($recovery, 'processing_unavailable');

                continue;
            }

            $this->markProcessed($recovery);
        }

        return self::SUCCESS;
    }

    private function recordAttempt(AppleNotificationRecovery $recovery): void
    {
        $recovery->forceFill([
            'attempt_count' => $recovery->attempt_count + 1,
            'last_attempted_at' => now(),
        ])->save();
    }

    private function markProcessed(AppleNotificationRecovery $recovery): void
    {
        $this->classify(
            $recovery,
            AppleNotificationRecovery::STATUS_PROCESSED,
            AppleNotificationLog::STATUS_PROCESSED,
            null,
            null,
            now(),
        );
    }

    private function markFailed(
        AppleNotificationRecovery $recovery,
        string $errorCode,
    ): void {
        $this->classify(
            $recovery,
            AppleNotificationRecovery::STATUS_FAILED,
            AppleNotificationLog::STATUS_FAILED,
            $errorCode,
            now()->addMinutes(10),
            null,
        );
    }

    private function markRejected(
        AppleNotificationRecovery $recovery,
        string $errorCode,
    ): void {
        $this->classify(
            $recovery,
            AppleNotificationRecovery::STATUS_REJECTED,
            AppleNotificationLog::STATUS_REJECTED,
            $errorCode,
            null,
            now(),
        );
    }

    private function classify(
        AppleNotificationRecovery $recovery,
        string $recoveryStatus,
        string $auditStatus,
        ?string $errorCode,
        mixed $nextAttemptAt,
        mixed $processedAt,
    ): void {
        DB::transaction(function () use (
            $recovery,
            $recoveryStatus,
            $auditStatus,
            $errorCode,
            $nextAttemptAt,
            $processedAt,
        ): void {
            $lockedRecovery = AppleNotificationRecovery::query()
                ->whereKey($recovery->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $audit = AppleNotificationLog::query()
                ->whereKey($lockedRecovery->apple_notification_log_id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedRecovery->forceFill([
                'status' => $recoveryStatus,
                'error_code' => $errorCode,
                'next_attempt_at' => $nextAttemptAt,
                'processed_at' => $processedAt,
            ])->save();
            $audit->forceFill([
                'status' => $auditStatus,
                'error_code' => $errorCode,
                'processed_at' => $processedAt,
            ])->save();
        }, 3);
    }
}
