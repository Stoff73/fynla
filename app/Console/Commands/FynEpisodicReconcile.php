<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AiMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * CoALA Phase 2 — FCA SYSC 9.1 retention: nightly orphan reconcile. Walks every
 * episodic blob across hot (episodic/) and cold (episodic-cold/) storage and
 * flags any whose {message_id} has no matching ai_messages row. Flag only —
 * never deletes, never reuses an id.
 */
final class FynEpisodicReconcile extends Command
{
    protected $signature = 'fyn:episodic:reconcile';

    protected $description = 'Flag orphan episodic blobs with no matching ai_messages row (flag only).';

    public function handle(): int
    {
        $disk = Storage::disk('local');
        $files = array_merge($disk->allFiles('episodic'), $disk->allFiles('episodic-cold'));

        $orphans = 0;

        foreach ($files as $path) {
            if (! str_ends_with($path, '.md')) {
                continue;
            }

            $messageId = basename($path, '.md');

            if (! AiMessage::whereKey($messageId)->exists()) {
                $this->warn("Orphan blob (message {$messageId}): {$path}");
                $orphans++;
            }
        }

        $this->info("Episodic reconcile complete: {$orphans} orphan blob(s) flagged.");

        return self::SUCCESS;
    }
}
