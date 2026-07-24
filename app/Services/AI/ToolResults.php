<?php

declare(strict_types=1);

namespace App\Services\AI;

/**
 * Shared predicates over raw tool-result arrays (Rule 20 — one rule, one
 * place, every consumer).
 */
final class ToolResults
{
    /**
     * A dedupe skip ("A similar record already exists — not created to
     * avoid duplication") means the record EXISTS: a landed outcome, never
     * a failed write. Ledgering it as a failure voiced "I couldn't record
     * anything new there" directly above a landed write's ack (live
     * 2026-07-23, user 292 msg 19858).
     *
     * @param  array<string, mixed>  $result
     */
    public static function isDuplicateSkip(array $result): bool
    {
        return ($result['warning'] ?? false) === true
            && isset($result['existing_id']);
    }
}
