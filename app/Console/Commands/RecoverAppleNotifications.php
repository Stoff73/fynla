<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Exceptions\Billing\AppleVerificationException;
use App\Models\AppleNotificationLog;
use App\Models\AppleTransaction;
use App\Models\User;
use App\Services\Billing\Apple\AppleReconciliationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Console\Command;
use Throwable;

final class RecoverAppleNotifications extends Command
{
    private const TRANSACTION_SCAN_MULTIPLIER = 10;

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

        $hasRecoverableEvidence = AppleNotificationLog::query()
            ->whereIn('status', [
                AppleNotificationLog::STATUS_RECEIVED,
                AppleNotificationLog::STATUS_FAILED,
            ])
            ->orderBy('id')
            ->limit($limit)
            ->exists();

        if (! $hasRecoverableEvidence) {
            return self::SUCCESS;
        }

        $transactions = AppleTransaction::query()
            ->latest('id')
            ->limit($limit * self::TRANSACTION_SCAN_MULTIPLIER)
            ->get()
            ->unique('original_transaction_id')
            ->take($limit);

        foreach ($transactions as $transaction) {
            $user = $transaction?->user()->first();

            if (! $user instanceof User) {
                continue;
            }

            try {
                app(AppleReconciliationService::class)->reconcile(
                    $user,
                    $transaction->original_transaction_id,
                );
            } catch (AuthorizationException) {
                continue;
            } catch (AppleVerificationException) {
                continue;
            } catch (Throwable) {
                continue;
            }
        }

        return self::SUCCESS;
    }
}
