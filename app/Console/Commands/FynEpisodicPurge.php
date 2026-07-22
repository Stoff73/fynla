<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AiMessage;
use App\Services\AI\Memory\Episodic\EpisodeBlobLocator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * CoALA Phase 2 — FCA SYSC 9.1 retention: hard-delete episodic blobs and
 * their ai_messages rows that are older than 6 years.
 * Dry-run by default; requires --force to execute deletions.
 */
final class FynEpisodicPurge extends Command
{
    protected $signature = 'fyn:episodic:purge {--force : Execute deletions (default is dry-run)}';

    protected $description = 'Hard-delete episodic blobs and rows older than 6 years. Dry-run unless --force.';

    public function handle(EpisodeBlobLocator $locator): int
    {
        $force = (bool) $this->option('force');
        $disk = Storage::disk('local');

        $query = AiMessage::query()
            ->where('created_at', '<', now()->subYears(6));

        $count = $query->count();

        if (! $force) {
            $this->info("Would purge {$count} episode(s) (dry-run). Use --force to execute.");

            return self::SUCCESS;
        }

        $purged = 0;

        $query->chunkById(200, function ($rows) use ($locator, $disk, &$purged): void {
            foreach ($rows as $msg) {
                if ($msg->blob_md_path !== null) {
                    $resolved = $locator->resolve($msg->blob_md_path);
                    if ($resolved !== null) {
                        $disk->delete($resolved);
                    }
                }

                $msg->delete();
                $purged++;
            }
        });

        $this->info("Episodic purge complete: {$purged} episode(s) deleted.");

        return self::SUCCESS;
    }
}
