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

    /**
     * Did this result write what the user gave? A record created, updated or
     * deleted, or a capture_* handler's fields written (`onboarding_capture`).
     *
     * Every capture_* handler reports its write as `onboarding_capture`, which
     * the stored `capture_write_landed` used to ignore, so retirement, State
     * Pension and spouse writes were all recorded as not landed (fix plan T4,
     * N4). A capture handler that wrote nothing still says
     * `onboarding_capture` when it asks for clarification (dependants) or
     * lists what it still needs (a retirement income without an age), so
     * those do not count.
     *
     * @param  array<string, mixed>  $result
     */
    public static function wroteData(array $result): bool
    {
        if (($result['error'] ?? false) === true) {
            return false;
        }

        foreach (['created', 'updated', 'deleted'] as $flag) {
            if (($result[$flag] ?? false) === true) {
                return true;
            }
        }

        $details = is_array($result['details'] ?? null) ? $result['details'] : [];

        return ($result['onboarding_capture'] ?? false) === true
            && ($result['clarification_required'] ?? false) !== true
            && empty($details['missing'] ?? []);
    }
}
