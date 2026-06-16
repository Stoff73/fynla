<?php

declare(strict_types=1);

namespace App\Services\AI\Cost;

/**
 * Computes the GBP cost of a single LLM turn from its token breakdown
 * (CoALA Phase 5 PR 1 — FR-M13).
 *
 * Rates live in `config/ai_pricing.php` as GBP per 1,000 tokens, split
 * into regular input, output, cache-write (creation) and cache-read (hit)
 * components. The caller is responsible for normalising provider-specific
 * token semantics before calling — Anthropic reports cache read/creation
 * separately from `input_tokens`, whereas xAI folds cached reads into
 * `prompt_tokens`, so "regular input" means different things per provider.
 *
 * An unpriced model never silently costs £0: it falls back to the
 * `default` rate and the returned `priced` flag is false so the gap is
 * visible to anyone reading the telemetry.
 */
class AiCostCalculator
{
    /**
     * @return array{gbp_cost: float, breakdown: array{input: float, output: float, cache_read: float, cache_write: float}, model: string, priced: bool}
     */
    public function compute(
        string $model,
        int $inputTokens,
        int $outputTokens,
        int $cacheReadTokens = 0,
        int $cacheCreationTokens = 0,
    ): array {
        [$rates, $priced] = $this->ratesFor($model);

        $breakdown = [
            'input' => $this->lineCost($inputTokens, $rates['input'] ?? 0.0),
            'output' => $this->lineCost($outputTokens, $rates['output'] ?? 0.0),
            'cache_read' => $this->lineCost($cacheReadTokens, $rates['cache_read'] ?? 0.0),
            'cache_write' => $this->lineCost($cacheCreationTokens, $rates['cache_write'] ?? 0.0),
        ];

        return [
            'gbp_cost' => round(array_sum($breakdown), 6),
            'breakdown' => $breakdown,
            'model' => $model,
            'priced' => $priced,
        ];
    }

    /**
     * Resolve the rate row for a model. Exact key first, then prefix match
     * against the configured keys (providers append version/date suffixes),
     * then the `default` fallback.
     *
     * @return array{0: array<string, float>, 1: bool}
     */
    private function ratesFor(string $model): array
    {
        $models = (array) config('ai_pricing.models', []);

        if (isset($models[$model])) {
            return [$models[$model], true];
        }

        foreach ($models as $key => $rates) {
            if (str_starts_with($model, (string) $key)) {
                return [$rates, true];
            }
        }

        return [(array) config('ai_pricing.default', []), false];
    }

    private function lineCost(int $tokens, float $ratePer1k): float
    {
        return round(($tokens / 1000) * $ratePer1k, 6);
    }
}
