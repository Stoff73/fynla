<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Services\Account\AccountDeletionService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExecuteGraceDeletions extends Command
{
    protected $signature = 'accounts:execute-grace-deletions';

    protected $description = 'Soft-delete users whose 30-day post-expiry grace period has ended.';

    public function handle(AccountDeletionService $service): int
    {
        $cutoff = Carbon::now()->startOfDay()->subDays(30);

        $subscriptions = Subscription::where('status', 'expired')
            ->whereNotNull('data_retention_starts_at')
            ->where('data_retention_starts_at', '<=', $cutoff)
            ->with('user')
            ->get();

        if ($subscriptions->isEmpty()) {
            $this->info('No users past the 30-day grace period.');

            return Command::SUCCESS;
        }

        $count = 0;
        foreach ($subscriptions as $sub) {
            $user = $sub->user;
            if (! $user || $user->trashed() || $user->is_preview_user) {
                continue;
            }

            $reason = $sub->trial_started_at
                ? 'trial_expired'
                : 'subscription_cancelled_grace_ended';

            try {
                $service->deleteAccount($user, $reason, 'auto_expiration_grace');
                $this->info("Deleted user #{$user->id} (reason: {$reason}).");
                $count++;
            } catch (\Throwable $e) {
                Log::error('Grace deletion failed', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
                $this->error("Failed user #{$user->id}: {$e->getMessage()}");
            }
        }

        $this->info("Processed {$count} grace-period deletion(s).");

        return Command::SUCCESS;
    }
}
