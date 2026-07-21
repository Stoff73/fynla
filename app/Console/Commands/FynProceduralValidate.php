<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\AI\Memory\Procedural\ProceduralCorpusLoader;
use Illuminate\Console\Command;
use Throwable;

/**
 * Validate the Fyn procedural corpus (fyn-memory/procedural/{kind}/{module}/*.md).
 * Fail-closed deploy gate: non-zero exit on any validation error, no partial
 * acceptance. Runtime serving (ProceduralCorpusLoader::load) degrades instead.
 */
final class FynProceduralValidate extends Command
{
    protected $signature = 'fyn:procedural:validate';

    protected $description = 'Validate the Fyn procedural corpus (fail-closed deploy gate).';

    public function handle(ProceduralCorpusLoader $loader): int
    {
        try {
            $corpus = $loader->loadStrict();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $all = $corpus->all();
        $kinds = array_unique(array_map(fn ($p) => $p->kind, $all));
        $modules = array_unique(array_map(fn ($p) => $p->module, $all));

        $this->info(sprintf(
            'Procedural corpus valid: %d procedure(s) across %d kind(s), %d module(s).',
            count($all),
            count($kinds),
            count($modules),
        ));

        foreach ($all as $p) {
            if ($p->active) {
                $this->line("  active: {$p->procedureId} v{$p->version} ({$p->kind}/{$p->module})");
            }
        }

        return self::SUCCESS;
    }
}
