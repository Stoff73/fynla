<?php

declare(strict_types=1);

namespace App\Services\AI\Memory\Episodic;

use App\Models\AiMessage;
use Illuminate\Support\Facades\Storage;

/** Resolve an episodic blob across hot (episodic/) and cold (episodic-cold/) storage. */
final class EpisodeBlobLocator
{
    /**
     * GDPR right-to-erasure: delete every Phase 2 episodic .md blob (hot + cold)
     * addressed by this user's ai_messages.blob_md_path. Shared by both erasure
     * paths — the manual fyn:user:erase command and the scheduled retention purge.
     *
     * Must be called BEFORE the ai_messages rows are deleted — the rows carry the
     * paths, so once they are gone the blobs can no longer be located.
     */
    public function eraseForUser(int $userId): int
    {
        $disk = Storage::disk('local');
        $deleted = 0;

        AiMessage::whereHas('conversation', fn ($q) => $q->where('user_id', $userId))
            ->whereNotNull('blob_md_path')
            ->chunkById(200, function ($rows) use ($disk, &$deleted): void {
                foreach ($rows as $msg) {
                    $resolved = $this->resolve($msg->blob_md_path);
                    if ($resolved !== null) {
                        $disk->delete($resolved);
                        $deleted++;
                    }
                }
            });

        return $deleted;
    }

    public function resolve(string $relativePath): ?string
    {
        $disk = Storage::disk('local');
        if ($disk->exists($relativePath)) {
            return $relativePath;
        }
        $cold = str_replace('episodic/', 'episodic-cold/', $relativePath);
        if ($cold !== $relativePath && $disk->exists($cold)) {
            return $cold;
        }

        return null;
    }

    public function get(string $relativePath): ?string
    {
        $resolved = $this->resolve($relativePath);

        return $resolved === null ? null : Storage::disk('local')->get($resolved);
    }
}
