<?php

declare(strict_types=1);

namespace App\Services\AI\Memory\Episodic;

use App\Models\AiMessage;
use Illuminate\Support\Carbon;

/** Read model over the (SQL row, .md blob) pair. List = SQL only; detail lazy-loads the blob. */
final class EpisodeProjection
{
    public function __construct(
        private readonly EpisodeRetriever $retriever,
        private readonly EpisodeBlobLocator $locator,
    ) {}

    /** @return list<array<string,mixed>> */
    public function list(int $clientId, int $limit = 20, ?Carbon $since = null): array
    {
        return $this->retriever->findEpisodes($clientId, $limit, $since)
            ->map(fn (AiMessage $m): array => [
                'id' => $m->id,
                'created_at' => $m->created_at?->toIso8601String(),
                'persona' => $m->persona,
                'model_used' => $m->model_used,
                'module' => $m->metadata['module'] ?? null,
                'tool_count' => is_array($m->tool_calls) ? count($m->tool_calls) : 0,
                'has_blob' => $m->blob_md_path !== null,
                'semantic_snapshot_id' => $m->semantic_snapshot_id,
            ])
            ->all();
    }

    /** @return array<string,mixed> */
    public function detail(int $messageId): array
    {
        $m = AiMessage::query()->with('conversation:id,user_id')->findOrFail($messageId);

        return [
            'id' => $m->id,
            'created_at' => $m->created_at?->toIso8601String(),
            'persona' => $m->persona,
            'model_used' => $m->model_used,
            'fetch_provenance' => $m->fetch_provenance,
            'semantic_snapshot_id' => $m->semantic_snapshot_id,
            'procedural_version' => $m->procedural_version,
            'blob_md_path' => $m->blob_md_path,
            'blob_md_sha256' => $m->blob_md_sha256,
            'blob_body' => $m->blob_md_path !== null ? $this->locator->get($m->blob_md_path) : null,
        ];
    }
}
