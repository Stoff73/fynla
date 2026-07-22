<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AiMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * CoALA Phase 2 — FCA SYSC 9.1 retention: move episodic blobs older than
 * 12 months from hot storage (episodic/) to cold storage (episodic-cold/).
 * The ai_messages row is untouched; EpisodeBlobLocator resolves both tiers.
 */
final class FynEpisodicColdArchive extends Command
{
    protected $signature = 'fyn:episodic:cold-archive';

    protected $description = 'Move episodic blobs older than 12 months to cold storage (idempotent).';

    public function handle(): int
    {
        $moved = 0;
        $disk = Storage::disk('local');

        AiMessage::query()
            ->whereNotNull('blob_md_path')
            ->where('blob_md_path', 'like', 'episodic/%')
            ->where('created_at', '<', now()->subMonths(12))
            ->chunkById(200, function ($rows) use ($disk, &$moved): void {
                foreach ($rows as $msg) {
                    $hot = $msg->blob_md_path;
                    $cold = 'episodic-cold/'.substr($hot, strlen('episodic/'));

                    if (! $disk->exists($hot)) {
                        // Already moved or missing — skip.
                        continue;
                    }

                    $disk->move($hot, $cold);
                    $moved++;
                }
            });

        $this->info("Episodic cold-archive complete: {$moved} blob(s) moved to cold storage.");

        return self::SUCCESS;
    }
}
