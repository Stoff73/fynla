<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Account\AccountDeletionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExecuteScheduledDeletions extends Command
{
    protected $signature = 'accounts:execute-scheduled-deletions';

    protected $description = 'Execute account deletions whose deletion_scheduled_for has passed.';

    public function handle(AccountDeletionService $service): int
    {
        $users = User::whereNull('deleted_at')
            ->whereNotNull('deletion_scheduled_for')
            ->where('deletion_scheduled_for', '<=', now())
            ->get();

        $this->info("Processing {$users->count()} scheduled deletion(s).");

        foreach ($users as $user) {
            try {
                $service->deleteAccount(
                    $user,
                    $user->deletion_reason,
                    $user->deletion_source
                );
                $this->info("Deleted user #{$user->id}.");
            } catch (\Throwable $e) {
                Log::error('Scheduled deletion failed', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
                $this->error("Failed user #{$user->id}: {$e->getMessage()}");
            }
        }

        return Command::SUCCESS;
    }
}
