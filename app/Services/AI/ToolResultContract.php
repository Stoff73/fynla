<?php

declare(strict_types=1);

namespace App\Services\AI;

/**
 * Per-agent output contract for module analysis tool results (S1.6.b).
 *
 * Replaces the lossy 15-key whitelist in CoordinatingAgent::extractKeyMetrics.
 * Every module's analyze() output now passes through validate() before it
 * reaches the LLM as a tool_result content block. Drift throws — the model
 * never sees a silently truncated payload.
 *
 * The validator handles the four paths each agent can take:
 *  1. Happy path — full module shape including missing_for_quality_advice
 *  2. Readiness-gated — readiness service blocked the analysis
 *     (`can_proceed: false` + `readiness_checks`)
 *  3. Success-false — agent returned `['success' => false, 'message' => ...]`
 *     because a required profile is absent (passes through verbatim)
 *  4. Module-specific empty state — Investment with no accounts, Goals with
 *     no goals (passes through with the module's empty-state required keys)
 *
 * Wrapped agents (Protection, Retirement, Estate) return
 *   ['success' => true, 'message' => ..., 'data' => [...payload...]]
 * — the validator unwraps before checking. Unwrapped agents (Savings,
 * Investment, Goals) return the payload directly.
 *
 * Source spec: April/April24Updates/plan/11-sprint-1-plan.md §S1.6 +
 * "Output-shape contract — S1.6 scope extension".
 */
final class ToolResultContract
{
    /** @var list<string> */
    public const MODULES = ['protection', 'savings', 'investment', 'retirement', 'estate', 'goals', 'holistic'];

    /**
     * Required top-level keys per module on the happy path.
     * Every key must exist in the (unwrapped) payload, including
     * `missing_for_quality_advice` so the LLM has structured gaps
     * to ask the user about.
     *
     * @var array<string, list<string>>
     */
    public const REQUIRED_KEYS = [
        'protection' => [
            'profile', 'needs', 'coverage', 'gaps', 'adequacy_score',
            'recommendations', 'scenarios', 'debt_breakdown', 'policies',
            'profile_completeness', 'missing_for_quality_advice',
        ],
        'savings' => [
            'summary', 'emergency_fund', 'isa_allowance', 'liquidity',
            'rate_comparisons', 'goals', 'missing_for_quality_advice',
        ],
        'investment' => [
            'portfolio_summary', 'asset_allocation', 'risk_metrics',
            'fee_analysis', 'tax_efficiency', 'tax_wrappers', 'goals',
            'missing_for_quality_advice',
        ],
        'retirement' => [
            'summary', 'income_projection', 'breakdown', 'annual_allowance',
            'profile', 'missing_for_quality_advice',
        ],
        'estate' => [
            'summary', 'asset_breakdown', 'iht_calculation',
            'trust_recommendations', 'gifting_opportunities', 'life_cover',
            'profile', 'missing_for_quality_advice',
        ],
        'goals' => [
            'has_goals', 'summary', 'by_module', 'top_goals', 'affordability',
            'missing_for_quality_advice',
        ],
        'holistic' => [
            'user_id', 'analysis_date', 'module_analysis', 'available_surplus',
            'ranked_recommendations', 'cashflow_allocation', 'summary',
        ],
    ];

    /**
     * Required keys when the readiness service blocks analysis.
     * Modules without a readiness gate are absent.
     *
     * @var array<string, list<string>>
     */
    public const PARTIAL_KEYS = [
        'protection' => ['can_proceed', 'readiness_checks'],
        'savings' => ['can_proceed', 'readiness_checks'],
        'investment' => ['can_proceed', 'readiness_checks'],
        'retirement' => ['can_proceed', 'readiness_checks'],
        'estate' => ['can_proceed', 'readiness_checks'],
    ];

    /**
     * Validate raw agent analysis output against the per-module contract.
     * Returns the unwrapped payload (with `module` key prepended) ready to
     * be sent to the LLM as a tool_result.
     *
     * @param  array<string, mixed>  $rawAnalysis
     * @return array<string, mixed>
     *
     * @throws ToolResultContractException when the shape drifts from the contract
     */
    public function validate(string $module, array $rawAnalysis): array
    {
        if (! in_array($module, self::MODULES, true)) {
            throw ToolResultContractException::unknownModule($module);
        }

        // Path 0: agent reported an error (e.g. retirement profile absent).
        // Pass through verbatim — the LLM sees the message and asks the user.
        if (isset($rawAnalysis['success']) && $rawAnalysis['success'] === false) {
            return $this->prependModule($module, $rawAnalysis);
        }

        // Path 1: unwrap success/data wrapper if the agent uses BaseAgent::response().
        $payload = $rawAnalysis;
        if (isset($rawAnalysis['data']) && is_array($rawAnalysis['data']) && isset($rawAnalysis['success'])) {
            $payload = $rawAnalysis['data'];
        }

        // Path 2: readiness gate blocked. Pass through with required gate keys.
        if (isset($payload['can_proceed']) && $payload['can_proceed'] === false) {
            $required = self::PARTIAL_KEYS[$module] ?? [];
            $this->assertKeys("{$module} (readiness-gated)", $required, $payload);

            return $this->prependModule($module, $payload);
        }

        // Path 3: module-specific empty states.
        if ($module === 'investment' && ($payload['accounts_count'] ?? null) === 0) {
            return $this->prependModule($module, $payload);
        }

        if ($module === 'goals' && ($payload['has_goals'] ?? null) === false) {
            return $this->prependModule($module, $payload);
        }

        // Path 4: happy path — full per-module contract.
        $required = self::REQUIRED_KEYS[$module] ?? [];
        $this->assertKeys($module, $required, $payload);

        return $this->prependModule($module, $payload);
    }

    /**
     * @param  list<string>  $required
     * @param  array<string, mixed>  $payload
     */
    private function assertKeys(string $context, array $required, array $payload): void
    {
        $missing = [];
        foreach ($required as $key) {
            if (! array_key_exists($key, $payload)) {
                $missing[] = $key;
            }
        }

        if ($missing !== []) {
            throw ToolResultContractException::missingKeys(
                $context,
                $missing,
                array_map('strval', array_keys($payload)),
            );
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function prependModule(string $module, array $payload): array
    {
        if (($payload['module'] ?? null) === $module) {
            return $payload;
        }

        return ['module' => $module] + $payload;
    }
}
