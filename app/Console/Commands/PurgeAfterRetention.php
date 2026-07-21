<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Account\RetentionPurgeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PurgeAfterRetention extends Command
{
    protected $signature = 'accounts:purge-after-retention';

    protected $description = 'Hard-purge users whose retention period has elapsed.';

    public function handle(RetentionPurgeService $service): int
    {
        $users = User::onlyTrashed()
            ->whereNotNull('purge_eligible_at')
            ->where('purge_eligible_at', '<=', now())
            ->where(function ($q) {
                $q->where('deletion_reason', '!=', 'legacy_purged')
                    ->orWhereNull('deletion_reason');
            })
            ->get();

        $this->info("Purging {$users->count()} retention-expired user(s).");

        foreach ($users as $user) {
            try {
                $service->purgeUser($user);
                $this->info("Purged user #{$user->id}.");
            } catch (\Throwable $e) {
                Log::error('Retention purge failed', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
                $this->error("Failed user #{$user->id}: {$e->getMessage()}");
            }
        }

        return Command::SUCCESS;
    }
}
