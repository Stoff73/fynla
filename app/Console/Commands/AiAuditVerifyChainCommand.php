<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\AI\AuditChainService;
use Illuminate\Console\Command;

/**
 * S0.12 — Walk the AI audit hash chain and emit JSON status.
 *
 * Returns exit 0 with `{chain_valid: true, tip_hash, row_count}` when the
 * chain is intact, exit 1 with `{chain_valid: false, broken_at, row_count}`
 * when a row has been mutated since insert. Used by the weekly health-check
 * schedule + Sprint 0 verification rollup.
 */
final class AiAuditVerifyChainCommand extends Command
{
    protected $signature = 'ai:audit:verify-chain';

    protected $description = 'Walk the ai_audit_events hash chain and emit JSON status.';

    public function handle(AuditChainService $service): int
    {
        $result = $service->verifyChain();

        $this->line((string) json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        return ($result['chain_valid'] ?? false) ? self::SUCCESS : self::FAILURE;
    }
}
