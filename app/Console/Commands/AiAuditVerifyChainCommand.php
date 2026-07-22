<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AiAuditEvent;
use App\Services\AI\AuditChainService;
use App\Services\AI\Memory\Episodic\EpisodeBlobLocator;
use Illuminate\Console\Command;

/**
 * S0.12 — Walk the AI audit hash chain and emit JSON status.
 *
 * Returns exit 0 with `{chain_valid: true, tip_hash, row_count}` when the
 * chain is intact, exit 1 with `{chain_valid: false, broken_at, row_count}`
 * when a row has been mutated since insert. Used by the weekly health-check
 * schedule + Sprint 0 verification rollup.
 *
 * Phase 2 (CoALA cross-medium tamper-evidence) — when the chain math passes,
 * a second pass re-hashes every `hash_scheme = 2` episode blob on disk and
 * compares it to the SHA recorded in the chain. This detects filesystem
 * tampering (the chain math alone only proves the recorded SHA is sound). A
 * missing blob is reported distinctly from a modified one so ops can tell an
 * accidental deletion from a content change.
 */
final class AiAuditVerifyChainCommand extends Command
{
    protected $signature = 'ai:audit:verify-chain';

    protected $description = 'Walk the ai_audit_events hash chain and emit JSON status.';

    public function handle(AuditChainService $service, EpisodeBlobLocator $locator): int
    {
        $result = $service->verifyChain();

        $this->line((string) json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        if (! ($result['chain_valid'] ?? false)) {
            return self::FAILURE;
        }

        return $this->verifyEpisodeBlobs($locator);
    }

    /**
     * Re-hash every v2 episode blob on disk against its recorded SHA.
     * Returns SUCCESS only when every blob resolves and matches.
     */
    private function verifyEpisodeBlobs(EpisodeBlobLocator $locator): int
    {
        $failures = [];

        foreach (AiAuditEvent::query()->where('hash_scheme', 2)->cursor() as $row) {
            $rs = $row->result_summary ?? [];
            $path = (string) ($rs['blob_md_path'] ?? '');
            $expectedSha = (string) ($rs['blob_md_sha256'] ?? '');

            if ($path === '') {
                continue;
            }

            $body = $locator->get($path);

            if ($body === null) {
                $failures[] = [
                    'path' => $path,
                    'expected_sha' => $expectedSha,
                    'actual_state' => 'missing',
                ];

                continue;
            }

            if (hash('sha256', $body) !== $expectedSha) {
                $failures[] = [
                    'path' => $path,
                    'expected_sha' => $expectedSha,
                    'actual_state' => 'modified',
                ];
            }
        }

        if ($failures === []) {
            return self::SUCCESS;
        }

        foreach ($failures as $failure) {
            $this->line((string) json_encode($failure, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        }

        return self::FAILURE;
    }
}
