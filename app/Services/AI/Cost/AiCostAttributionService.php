<?php

declare(strict_types=1);

namespace App\Services\AI\Cost;

use App\Models\AiCostAttribution;
use App\Traits\HasAiChat;

/**
 * Writes the per-action cost ledger (CoALA Phase 5 item 8 — FR-M11).
 *
 * The instrumentation (Planner + FynLoop) calls {@see record()} once per LLM
 * call with the action context plus the cost-telemetry fragment that
 * {@see HasAiChat::costTelemetryMetadata} produces (cache_hit_tokens,
 * cache_miss_tokens, gbp_cost, gbp_cost_priced). The aggregate read queries for
 * the admin dashboard live with the dashboard controller.
 */
final class AiCostAttributionService
{
    /**
     * Persist one LLM call's cost attribution. Unknown keys are dropped by the
     * model's fillable guard; the defaults cover a call we could not fully cost.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function record(array $attributes): AiCostAttribution
    {
        return AiCostAttribution::create(array_merge([
            'cycle_id' => 1,
            'input_tokens' => 0,
            'output_tokens' => 0,
            'cache_hit_tokens' => 0,
            'cache_miss_tokens' => 0,
            'gbp_cost' => 0,
            'gbp_cost_priced' => false,
        ], $attributes));
    }
}
