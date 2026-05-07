<?php

declare(strict_types=1);

namespace App\Services\Account;

use App\Mail\Account\AccountDeletionCancelledEmail;
use App\Mail\Account\AccountDeletionConfirmationEmail;
use App\Mail\Account\AccountDeletionScheduledEmail;
use App\Mail\Account\AccountRestorationConfirmationEmail;
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
        if (! $user->isScheduledForDeletion()) {
            throw new \RuntimeException('User is not scheduled for deletion.');
        }

        DB::transaction(function () use ($user) {
            $this->auditService->logGDPR(
                AuditLog::ACTION_ACCOUNT_DELETION_CANCELLED,
                $user->id,
                [
                    'previous_reason' => $user->deletion_reason,
                    'previous_source' => $user->deletion_source,
                    'previous_scheduled_for' => $user->deletion_scheduled_for?->toIso8601String(),
                ]
            );

            $user->update([
                'deletion_scheduled_for' => null,
                'deletion_reason' => null,
                'deletion_source' => null,
            ]);

            Mail::to($user->email)->queue(new AccountDeletionCancelledEmail($user));
        });
    }

    public function deleteAccount(User $user, string $reason, string $source): void
    {
        DB::transaction(function () use ($user, $reason, $source) {
            $this->auditService->logGDPR(
                AuditLog::ACTION_ACCOUNT_DELETED,
                $user->id,
                [
                    'reason' => $reason,
                    'source' => $source,
                ]
            );

            // Revoke Sanctum tokens
            DB::table('personal_access_tokens')
                ->where('tokenable_type', User::class)
                ->where('tokenable_id', $user->id)
                ->delete();

            // Delete sessions
            DB::table('user_sessions')->where('user_id', $user->id)->delete();

            // Cancel an active subscription only — leave others (expired/trialing/cancelled) alone
            DB::table('subscriptions')
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->update(['status' => 'cancelled', 'updated_at' => now()]);

            $retentionYears = (int) config('retention.account_years', 7);

            $user->update([
                'deletion_scheduled_for' => null,
                'deletion_reason' => $reason,
                'deletion_source' => $source,
                'purge_eligible_at' => now()->addYears($retentionYears),
            ]);
            $user->delete(); // soft-delete via SoftDeletes trait

            Mail::to($user->email)->queue(new AccountDeletionConfirmationEmail($user));
        });
    }

    public function restoreAccount(User $user): void
    {
        if (! $user->canBeRestored()) {
            throw new \RuntimeException('Account cannot be restored.');
        }

        DB::transaction(function () use ($user) {
            $this->auditService->logGDPR(
                AuditLog::ACTION_ACCOUNT_RESTORED,
                $user->id,
                [
                    'previous_reason' => $user->deletion_reason,
                    'previous_source' => $user->deletion_source,
                ]
            );

            $user->update([
                'restored_at' => now(),
                'purge_eligible_at' => null,
                // deletion_reason and deletion_source intentionally NOT cleared (audit history)
            ]);
            $user->restore();

            Mail::to($user->email)->queue(new AccountRestorationConfirmationEmail($user));
        });
    }
}
