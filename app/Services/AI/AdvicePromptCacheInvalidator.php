<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Constants\QuerySchemas;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * April30Updates F-9 — invalidates the per-user AI prompt caches so a
 * post-write advice turn sees the user's latest state.
 *
 * AdvicePromptBuilder caches:
 *   - ai_existing_records_{user_id}                   60s
 *   - ai_financial_context_{user_id}_{primary}       120s
 *
 * Without invalidation, a user who creates a record via inline-capture
 * and immediately asks an advice question would see stale records in
 * the prompt for up to 120s — leading to "you don't have an ISA"
 * responses or duplicate-create attempts (the RecordDuplicateChecker
 * shortcut catches those, but the prompt is still wrong for the user).
 *
 * This invalidator is best-effort: a Cache::forget that fails should
 * never bring down the write path. The next refresh tick will fall
 * back to the underlying state, just with the original cache lag.
 */
final class AdvicePromptCacheInvalidator
{
    /**
     * Forget every prompt cache for the given user. Intended call site:
     * after every successful write tool in `CoordinatingAgent::executeTool`.
     */
    public static function forUser(int $userId): void
    {
        try {
            Cache::forget("ai_existing_records_{$userId}");

            // Financial context is keyed by classification primary. Clear
            // every variant — cheap, only ~25 forgets, all fire-and-forget.
            // Includes `unknown` which is the fallback when classification
            // is null (rare, but keeps the cache consistent).
            $primaries = array_merge(
                array_values((new \ReflectionClass(QuerySchemas::class))->getConstants()),
                ['unknown']
            );

            foreach ($primaries as $primary) {
                if (! is_string($primary)) {
                    continue;
                }
                Cache::forget("ai_financial_context_{$userId}_{$primary}");
            }
        } catch (\Throwable $e) {
            // Forensic only — never let a cache invalidation failure
            // mask a successful write or break the chat stream.
            Log::warning(
                '[AdvicePromptCacheInvalidator] Failed to invalidate caches',
                [
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                ],
            );
        }
    }
}
