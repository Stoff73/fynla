<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Tiers\RevolutTierVariationSync;
use Illuminate\Console\Command;

/**
 * Scheduled command that runs RevolutTierVariationSync.
 *
 * Schedule: weekly (alongside CheckOverdueSubscriptions / SyncRevolutPlans
 * cadence — tier prices change rarely; weekly is sufficient).
 *
 * Usage:
 *   php artisan tier:sync-revolut              # Use system actor (User id=1)
 *   php artisan tier:sync-revolut --actor=5    # Use specific user id as audit actor
 */
class SyncRevolutTierVariations extends Command
{
    protected $signature = 'tier:sync-revolut {--actor= : User ID to use as audit actor (default: 1)}';

    protected $description = 'Push tier prices to Revolut and write back variation ids';

    public function handle(RevolutTierVariationSync $sync): int
    {
        $actorId = (int) ($this->option('actor') ?? 1);
        $actor = User::find($actorId);

        if (! $actor) {
            $this->warn("Actor user {$actorId} not found — using synthetic system actor.");
        }

        $this->info('Syncing tier plan variations to Revolut...');

        try {
            $sync->run($actor);
            $this->info('Tier variation sync complete.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Sync failed: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
