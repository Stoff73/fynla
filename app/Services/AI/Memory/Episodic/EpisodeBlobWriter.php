<?php

declare(strict_types=1);

namespace App\Services\AI\Memory\Episodic;

use App\Models\AiMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Atomic episodic blob writer (plan §"Atomic write protocol"):
 * compose -> write .tmp -> atomic rename -> sha256. Path is date-sharded by the
 * episode timestamp (UTC), then conversation, then message id — a contract
 * retention/erase scripts depend on.
 */
final class EpisodeBlobWriter
{
    public function write(AiMessage $message, EpisodeBlobData $data): EpisodeBlobRef
    {
        $disk = Storage::disk('local');
        $date = Carbon::parse($data->timestamp)->utc();
        $dir = sprintf('episodic/%s/%d', $date->format('Y/m/d'), $data->conversationId);
        $path = "{$dir}/{$message->id}.md";
        $tmp = "{$path}.tmp";

        $body = $data->toMarkdown();

        $disk->put($tmp, $body);
        $disk->delete($path);          // idempotent re-write: clear target
        $disk->move($tmp, $path);      // Flysystem local move = POSIX rename (atomic, same fs)

        $sha = hash('sha256', (string) $disk->get($path));

        return new EpisodeBlobRef($path, $sha);
    }
}
