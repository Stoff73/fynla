<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AiAuditEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * S0.12 — Weekly retention job for ai_audit_events.
 *
 * Retention windows per INV-2.10.2:
 *   - 7 years (≈ 2,555 days): rows where `operation = 'write'` OR
 *     `tool_name = 'get_recommendations'`. These are the regulator-relevant
 *     trail of writes and personalised recommendations.
 *   - 2 years (730 days): all other rows (read tools, classifier hits,
 *     handoff chrome, stripped events).
 *
 * **Important — chain integrity vs PII removal.**
 *
 * The hash chain is computed over the original serialised payload of every
 * row. If this job *mutated* a historical row (e.g. swapped a name field
 * for a pseudonym), `verifyChain()` would correctly report the chain as
 * broken at that row — defeating the point of the chain.
 *
 * Therefore we delete rows that fall outside their retention window
 * rather than mutating them in place. The chain remains coherent for
 * the retained tail (the new "first" row simply has no predecessor;
 * `verifyChain()` treats anything not in the table as outside its
 * remit and walks from the earliest surviving id with the chain's
 * canonical zero-hash predecessor).
 *
 * If/when GDPR-style pseudonymisation of older rows is needed for an
 * export, do it in a separate read-only export view that re-serialises
 * the row with PII fields swapped — the source rows stay untouched and
 * the chain stays verifiable. That export view is out of scope for
 * Sprint 0.
 */
class AiAuditRetentionJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private const SEVEN_YEARS_DAYS = 2555;
    private const TWO_YEARS_DAYS = 730;

    public function handle(): array
    {
        $longCutoff = now()->subDays(self::SEVEN_YEARS_DAYS);
        $shortCutoff = now()->subDays(self::TWO_YEARS_DAYS);

        // Long-retention rows that have themselves aged out.
        $longRetained = AiAuditEvent::query()
            ->where('created_at', '<', $longCutoff)
            ->where(function ($q): void {
                $q->where('operation', 'write')
                    ->orWhere('tool_name', 'get_recommendations');
            })
            ->delete();

        // Short-retention rows: aged out AND not in the long-retention class.
        $shortRetained = AiAuditEvent::query()
            ->where('created_at', '<', $shortCutoff)
            ->where('operation', '!=', 'write')
            ->where('tool_name', '!=', 'get_recommendations')
            ->delete();

        Log::info('[AiAuditRetentionJob] Pruned aged audit rows', [
            'long_retention_pruned' => $longRetained,
            'short_retention_pruned' => $shortRetained,
            'long_cutoff' => $longCutoff->toIso8601String(),
            'short_cutoff' => $shortCutoff->toIso8601String(),
        ]);

        return [
            'long_retention_pruned' => $longRetained,
            'short_retention_pruned' => $shortRetained,
        ];
    }
}
