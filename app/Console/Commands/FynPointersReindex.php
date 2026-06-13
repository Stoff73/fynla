<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\AI\Pointers\PointerRegistry;
use Illuminate\Console\Command;
use Throwable;

/**
 * Validate the pointer corpus at deploy time. Fail-closed — if any pointer is
 * malformed or references an unregistered handler, the command exits non-zero
 * and no partial state is written. No index file is produced; the registry
 * reads the corpus directly at runtime. Mirrors FynSemanticReindex's idiom.
 */
final class FynPointersReindex extends Command
{
    protected $signature = 'fyn:pointers:reindex';

    protected $description = 'Validate the Fyn pointer corpus (fail-closed deploy-time safety net).';

    public function handle(PointerRegistry $registry): int
    {
        try {
            $pointers = $registry->all();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Pointer corpus validated: '.count($pointers).' pointers loaded.');

        return self::SUCCESS;
    }
}
