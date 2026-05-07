<?php

declare(strict_types=1);

namespace App\Services\Account;

use App\Mail\Account\AccountDeletionScheduledEmail;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Audit\AuditService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class AccountDeletionService
{
    public function __construct(
        private readonly AuditService $auditService
    ) {}

    public function scheduleDeletion(
        User $user,
        string $reason,
        string $source,
        Carbon $executesAt
    ): void {
        if ($user->trashed()) {
            throw new \RuntimeException('User is already deleted.');
        }
        if ($user->isScheduledForDeletion()) {
            throw new \RuntimeException('User is already scheduled for deletion.');
        }

        DB::transaction(function () use ($user, $reason, $source, $executesAt) {
            $this->auditService->logGDPR(
                AuditLog::ACTION_ACCOUNT_DELETION_SCHEDULED,
                $user->id,
                [
                    'reason' => $reason,
                    'source' => $source,
                    'executes_at' => $executesAt->toIso8601String(),
                ]
            );

            $user->update([
                'deletion_scheduled_for' => $executesAt,
                'deletion_reason' => $reason,
                'deletion_source' => $source,
            ]);

            Mail::to($user->email)->queue(
                new AccountDeletionScheduledEmail($user, $executesAt)
            );
        });
    }

    public function cancelScheduledDeletion(User $user): void
    {
        // Implemented in Task 2.2
        throw new \LogicException('Not yet implemented');
    }

    public function deleteAccount(User $user, string $reason, string $source): void
    {
        // Implemented in Task 2.3
        throw new \LogicException('Not yet implemented');
    }

    public function restoreAccount(User $user): void
    {
        // Implemented in Task 2.4
        throw new \LogicException('Not yet implemented');
    }
}
