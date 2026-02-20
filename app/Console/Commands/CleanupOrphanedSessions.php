<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Auth\SessionService;
use Illuminate\Console\Command;

class CleanupOrphanedSessions extends Command
{
    protected $signature = 'sessions:cleanup
                            {--dry-run : Show what would be deleted without actually deleting}';

    protected $description = 'Delete orphaned user sessions where the Sanctum token no longer exists';

    public function handle(SessionService $sessionService): int
    {
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $count = \App\Models\UserSession::whereDoesntHave('token')->count();
            $this->info("Would delete {$count} orphaned session(s).");
        } else {
            $count = $sessionService->cleanupOrphanedSessions();
            $this->info("Deleted {$count} orphaned session(s).");

            if ($count > 0) {
                \Log::info('Orphaned sessions cleaned up', [
                    'deleted_count' => $count,
                ]);
            }
        }

        return Command::SUCCESS;
    }
}
