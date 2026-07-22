<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AiMessage;
use App\Models\User;
use App\Services\AI\Memory\Episodic\EpisodeBlobLocator;
use App\Services\AI\Memory\UserSemanticStore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * CoALA Phase 2 — GDPR right-to-erasure for a single user's episodic memory.
 * Erasure must span BOTH media: the ai_messages rows AND the episodic .md
 * blobs they address (hot episodic/ and cold episodic-cold/). Partial erasure
 * (rows deleted, blobs left on disk) is a regulatory failure.
 *
 * Blobs are deleted FIRST — they are addressed by blob_md_path on the rows, so
 * once the rows are gone the paths are lost. Dry-run by default; --force to execute.
 *
 * CoALA Phase 6: also erases the user's per-user semantic facts
 * (UserSemanticStore, storage/app/memory/semantic-user/{userId}/). The forget()
 * call is gated on --force, mirroring the episodic blob deletion above.
 */
final class FynUserErase extends Command
{
    protected $signature = 'fyn:user:erase {user : The user id to erase} {--force : Execute the erasure (default is dry-run)}';

    protected $description = "Erase a user's episodic data across SQL rows and .md blobs (hot + cold), and per-user semantic facts. Dry-run unless --force.";

    public function handle(EpisodeBlobLocator $locator, UserSemanticStore $semanticStore): int
    {
        $userId = (int) $this->argument('user');

        $user = User::find($userId);
        if ($user === null) {
            $this->error("User {$userId} not found.");

            return self::FAILURE;
        }

        $messageQuery = AiMessage::whereHas(
            'conversation',
            fn ($q) => $q->where('user_id', $userId)
        );

        $episodeCount = (clone $messageQuery)->count();
        $blobCount = (clone $messageQuery)->whereNotNull('blob_md_path')->count();
        $semanticFactCount = count($semanticStore->forUser($userId));

        $force = (bool) $this->option('force');

        if (! $force) {
            $this->info("Would erase {$episodeCount} episodes ({$blobCount} blobs), {$semanticFactCount} per-user semantic facts for user {$userId} (dry-run). Use --force to execute.");

            return self::SUCCESS;
        }

        // Phase 1: delete the blobs FIRST — the rows carry the paths.
        $blobsDeleted = $locator->eraseForUser($userId);

        // Phase 2: delete the SQL rows — the user's ai_messages and conversations.
        $episodesDeleted = 0;
        DB::transaction(function () use ($userId, &$episodesDeleted): void {
            $episodesDeleted = AiMessage::whereHas(
                'conversation',
                fn ($q) => $q->where('user_id', $userId)
            )->delete();

            DB::table('ai_conversations')->where('user_id', $userId)->delete();
        });

        // Phase 3: delete per-user semantic facts (CoALA Phase 6).
        $semanticStore->forget($userId);

        $this->info("Erased {$episodesDeleted} episodes, {$blobsDeleted} blobs, {$semanticFactCount} per-user semantic facts for user {$userId}.");

        return self::SUCCESS;
    }
}
