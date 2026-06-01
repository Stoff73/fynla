<?php

declare(strict_types=1);

namespace App\Services\AI\Memory\Episodic;

use App\Models\AiMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** Typed episodic retrieval — SQL-only list path (no blob I/O). */
final class EpisodeRetriever
{
    public function findEpisodes(int $clientId, int $limit = 20, ?Carbon $since = null): Collection
    {
        return AiMessage::query()
            ->where('role', 'assistant')
            ->whereHas('conversation', fn ($q) => $q->where('user_id', $clientId))
            ->when($since !== null, fn ($q) => $q->where('created_at', '>=', $since))
            ->with('conversation:id,user_id')
            ->latest('id')
            ->limit($limit)
            ->get();
    }
}
