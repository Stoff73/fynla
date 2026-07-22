<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AiMessage;
use App\Services\AI\Memory\Episodic\EpisodeBlobData;
use App\Services\AI\Memory\Episodic\EpisodeBlobWriter;
use Illuminate\Console\Command;

/**
 * CoALA Phase 2 — one-shot, idempotent backfill of episodic .md blobs for
 * pre-cutover ai_messages rows. Writes the verbatim blob + populates
 * blob_md_path/sha256 so historical rows are first-class retrievable. Does NOT
 * append __episode__ audit events (history is not re-chained — spec §4/§5).
 */
final class FynEpisodicBackfillBlobs extends Command
{
    protected $signature = 'fyn:episodic:backfill-blobs';

    protected $description = 'Backfill episodic .md blobs for legacy ai_messages rows (idempotent).';

    public function handle(EpisodeBlobWriter $writer): int
    {
        $backfilled = 0;
        $skipped = 0;

        AiMessage::query()
            ->whereNull('blob_md_path')
            ->where(fn ($q) => $q->whereNotNull('system_prompt')->orWhereNotNull('assembled_context'))
            ->with('conversation:id,user_id')
            ->chunkById(200, function ($rows) use ($writer, &$backfilled, &$skipped): void {
                foreach ($rows as $msg) {
                    try {
                        $data = new EpisodeBlobData(
                            episodeId: (string) $msg->id,
                            conversationId: $msg->conversation_id,
                            clientId: (int) ($msg->conversation->user_id ?? 0),
                            timestamp: ($msg->created_at ?? now())->utc()->toIso8601String(),
                            persona: $msg->persona,
                            module: $msg->metadata['module'] ?? null,
                            proceduralVersion: null,
                            semanticSnapshotId: $msg->semantic_snapshot_id,
                            modelUsed: $msg->model_used,
                            systemPrompt: (string) ($msg->system_prompt ?? ''),
                            assembledContext: (string) ($msg->assembled_context ?? ''),
                            reasoningTrace: null,
                            toolCalls: is_array($msg->tool_calls) ? $msg->tool_calls : null,
                            toolResults: is_array($msg->tool_results) ? $msg->tool_results : null,
                        );
                        $ref = $writer->write($msg, $data);
                        $msg->update(['blob_md_path' => $ref->path, 'blob_md_sha256' => $ref->sha256]);
                        $backfilled++;
                    } catch (\Throwable $e) {
                        report($e);
                        $skipped++;
                    }
                }
            });

        $this->info("Episodic backfill complete: {$backfilled} backfilled, {$skipped} skipped.");

        return self::SUCCESS;
    }
}
