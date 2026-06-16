<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\AI\Memory\SemanticCorpusLoader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Validate the semantic corpus and write a cached index. Sparse-only — no
 * embeddings (deferred until ~500 concurrent users, CSJ 2026-06-01). Runs at
 * deploy time and on demand; fail-closed (non-zero exit, no partial index).
 */
final class FynSemanticReindex extends Command
{
    protected $signature = 'fyn:semantic:reindex';

    protected $description = 'Validate the Fyn semantic corpus and write the cached index (sparse, no embeddings).';

    public function handle(SemanticCorpusLoader $loader): int
    {
        try {
            $facts = $loader->all();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $index = ['count' => count($facts), 'facts' => []];
        foreach ($facts as $id => $fact) {
            $index['facts'][$id] = [
                'category' => $fact->category,
                'title' => $fact->title,
                'version' => $fact->version,
                'valid_from' => $fact->validFrom?->toDateString(),
                'valid_to' => $fact->validTo?->toDateString(),
            ];
        }

        $path = (string) config('fyn.memory.semantic_index');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->info("Semantic corpus reindexed: {$index['count']} facts → {$path}");

        return self::SUCCESS;
    }
}
