<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\PendingRegistration;
use Illuminate\Console\Command;

class CleanupPendingRegistrations extends Command
{
    protected $signature = 'registrations:cleanup
                            {--dry-run : Show what would be deleted without actually deleting}';

    protected $description = 'Delete expired pending registrations';

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');

        $query = PendingRegistration::where('expires_at', '<', now());
        $count = $query->count();

        if ($count === 0) {
            $this->info('No expired pending registrations to clean up.');

            return Command::SUCCESS;
        }

        if ($isDryRun) {
            $this->info("Would delete {$count} expired pending registration(s).");
        } else {
            $query->delete();
            $this->info("Deleted {$count} expired pending registration(s).");

            \Log::info('Expired pending registrations cleaned up', [
                'deleted_count' => $count,
            ]);
        }

        return Command::SUCCESS;
    }
}
