<?php

declare(strict_types=1);

namespace App\Services\Pipeline;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Soft cost caps for the pipeline's Anthropic spend.
 *
 * Two guardrails:
 *  - Daily cap: checked BEFORE every request. If already exceeded, the call
 *    is refused (no partial charge). Resets at midnight (server time).
 *  - Per-request cap: checked AFTER every request (the money's already spent
 *    by then). Logs a warning; caller can decide to abort further work.
 *
 * Costs are stored in the cache under a per-day key. Anthropic bills in USD;
 * the config's GBP↔USD rate converts.
 */
class CostCap
{
    /**
     * Anthropic Opus 4 published pricing (per million tokens). Update when
     * Anthropic changes it.
     */
    private const OPUS_INPUT_USD_PER_MTOK = 15.0;

    private const OPUS_OUTPUT_USD_PER_MTOK = 75.0;

    private const OPUS_CACHE_WRITE_USD_PER_MTOK = 18.75;

    private const OPUS_CACHE_READ_USD_PER_MTOK = 1.5;

    /** @throws RuntimeException when the daily cap has already been reached. */
    public function ensureDailyBudgetAvailable(): void
    {
        $dailyGbp = (float) config('pipeline.cost.per_day_gbp', 2.0);
        $spentGbp = $this->dailySpendGbp();

        if ($spentGbp >= $dailyGbp) {
            throw new RuntimeException(sprintf(
                'Pipeline daily cost cap reached: £%.2f spent today (cap: £%.2f). Will resume tomorrow.',
                $spentGbp,
                $dailyGbp
            ));
        }
    }

    /**
     * Record a call's actual cost and check the per-request cap.
     * Returns the GBP cost of this single call.
     *
     * @param  array{input_tokens?:int,output_tokens?:int,cache_creation_input_tokens?:int,cache_read_input_tokens?:int}  $usage
     */
    public function recordUsage(array $usage): float
    {
        $usd =
            (($usage['input_tokens'] ?? 0) / 1_000_000) * self::OPUS_INPUT_USD_PER_MTOK +
            (($usage['output_tokens'] ?? 0) / 1_000_000) * self::OPUS_OUTPUT_USD_PER_MTOK +
            (($usage['cache_creation_input_tokens'] ?? 0) / 1_000_000) * self::OPUS_CACHE_WRITE_USD_PER_MTOK +
            (($usage['cache_read_input_tokens'] ?? 0) / 1_000_000) * self::OPUS_CACHE_READ_USD_PER_MTOK;

        $usdPerGbp = max(0.5, (float) config('pipeline.cost.usd_per_gbp', 1.27));
        $gbp = $usd / $usdPerGbp;

        $perRequestCap = (float) config('pipeline.cost.per_request_gbp', 0.30);
        if ($gbp > $perRequestCap) {
            Log::channel('pipeline')->warning('Per-request cost cap exceeded.', [
                'gbp_this_call' => round($gbp, 4),
                'cap_gbp' => $perRequestCap,
                'usage' => $usage,
            ]);
        }

        Cache::increment($this->dailyKey(), (int) round($gbp * 10000));

        return $gbp;
    }

    public function dailySpendGbp(): float
    {
        $units = (int) Cache::get($this->dailyKey(), 0);

        return $units / 10000;
    }

    private function dailyKey(): string
    {
        return 'pipeline.cost.day.'.now()->toDateString();
    }
}
