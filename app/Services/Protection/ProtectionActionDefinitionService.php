<?php

declare(strict_types=1);

namespace App\Services\Protection;

use App\Models\ProtectionActionDefinition;
use App\Traits\FormatsCurrency;

class ProtectionActionDefinitionService
{
    use FormatsCurrency;

    /**
     * Evaluate all enabled action definitions against the comprehensive plan data.
     *
     * @return array<int, array> Recommendation arrays matching the structureActions format
     */
    public function evaluateActions(array $comprehensivePlan): array
    {
        $definitions = ProtectionActionDefinition::getEnabled();
        $recommendations = [];

        foreach ($definitions as $definition) {
            $result = $this->evaluateDefinition($definition, $comprehensivePlan);

            if ($result !== null) {
                $recommendations[] = $result;
            }
        }

        usort($recommendations, fn ($a, $b) => $a['priority'] <=> $b['priority']);

        return $recommendations;
    }

    /**
     * Evaluate a single definition against the plan data.
     */
    private function evaluateDefinition(ProtectionActionDefinition $definition, array $comprehensivePlan): ?array
    {
        $triggerConfig = $definition->trigger_config ?? [];
        $condition = $triggerConfig['condition'] ?? '';

        $result = match ($condition) {
            'gap_exists' => $this->evaluateGapCondition($definition, $comprehensivePlan),
            'strategy_recommendation' => $this->evaluateStrategyCondition($definition, $comprehensivePlan),
            'policies_exist_with_gaps' => $this->evaluatePoliciesExistWithGaps($definition, $comprehensivePlan),
            'multiple_policies' => $this->evaluateMultiplePolicies($definition, $comprehensivePlan),
            'profile_missing' => $this->evaluateProfileMissing($definition, $comprehensivePlan),
            'no_policies_with_gaps' => $this->evaluateNoPoliciesWithGaps($definition, $comprehensivePlan),
            default => null,
        };

        return $result;
    }

    /**
     * Evaluate gap_exists condition — triggers when a specific coverage type has a gap > threshold.
     */
    private function evaluateGapCondition(ProtectionActionDefinition $definition, array $comprehensivePlan): ?array
    {
        $triggerConfig = $definition->trigger_config;
        $coverageType = $triggerConfig['coverage_type'] ?? '';
        $threshold = (float) ($triggerConfig['threshold'] ?? 0);

        $coverageAnalysis = $comprehensivePlan['coverage_analysis'] ?? [];
        $typeData = $coverageAnalysis[$coverageType] ?? [];
        $gap = (float) ($typeData['gap'] ?? 0);
        $coverage = (float) ($typeData['coverage'] ?? 0);
        $need = (float) ($typeData['need'] ?? 0);

        if ($gap <= $threshold) {
            return null;
        }

        // Build description text based on coverage type
        $descriptionText = $this->buildGapDescription($coverageType, $gap, $coverage);

        $vars = [
            'gap_amount' => $this->formatCurrency($gap),
            'need_amount' => $this->formatCurrency($need),
            'coverage_amount' => $this->formatCurrency($coverage),
            'description_text' => $descriptionText,
        ];

        return $this->buildRecommendation($definition, $vars, $gap);
    }

    /**
     * Evaluate strategy_recommendation — matches optimized strategy recommendations by category.
     */
    private function evaluateStrategyCondition(ProtectionActionDefinition $definition, array $comprehensivePlan): ?array
    {
        $triggerConfig = $definition->trigger_config;
        $categoryMatch = strtolower($triggerConfig['category_match'] ?? '');

        $strategy = $comprehensivePlan['optimized_strategy'] ?? [];
        $strategyRecs = $strategy['recommendations'] ?? [];

        // Find matching strategy recommendations
        $matched = [];
        foreach ($strategyRecs as $rec) {
            $recCategory = strtolower($rec['category'] ?? '');
            if (str_contains($recCategory, $categoryMatch)) {
                $matched[] = $rec;
            }
        }

        if (empty($matched)) {
            return null;
        }

        // Use the first matching recommendation's data
        $rec = $matched[0];
        $coverageAmount = $rec['coverage_amount'] ?? $rec['monthly_benefit'] ?? 0;
        $monthlyCost = $rec['estimated_monthly_cost'] ?? 0;

        $vars = [
            'action_text' => $rec['action'] ?? 'Review coverage',
            'details_text' => $rec['details'] ?? '',
            'coverage_amount' => $this->formatCurrency($coverageAmount),
            'monthly_cost' => $this->formatCurrency($monthlyCost),
        ];

        return [
            'priority' => $rec['priority'] ?? 3,
            'category' => $definition->category,
            'action' => $definition->renderTitle($vars),
            'rationale' => $definition->renderDescription($vars),
            'impact' => $rec['importance'] ?? 'Medium',
            'estimated_cost' => round((float) $monthlyCost, 2),
            'impact_parameters' => ['coverage_amount' => $coverageAmount],
            'timeframe' => $rec['timeframe'] ?? 'Within 3 months',
        ];
    }

    /**
     * Evaluate policies_exist_with_gaps — triggers when policies exist but gaps remain.
     */
    private function evaluatePoliciesExistWithGaps(ProtectionActionDefinition $definition, array $comprehensivePlan): ?array
    {
        $currentCoverage = $comprehensivePlan['current_coverage'] ?? [];
        $policyCount = $this->countPolicies($currentCoverage);

        if ($policyCount === 0) {
            return null;
        }

        if (! $this->hasAnyGap($comprehensivePlan)) {
            return null;
        }

        $vars = [
            'policy_count' => (string) $policyCount,
        ];

        return $this->buildRecommendation($definition, $vars, 0);
    }

    /**
     * Evaluate multiple_policies — triggers when policy count exceeds threshold.
     */
    private function evaluateMultiplePolicies(ProtectionActionDefinition $definition, array $comprehensivePlan): ?array
    {
        $threshold = (int) ($definition->trigger_config['threshold'] ?? 3);
        $currentCoverage = $comprehensivePlan['current_coverage'] ?? [];
        $policyCount = $this->countPolicies($currentCoverage);

        if ($policyCount < $threshold) {
            return null;
        }

        $vars = [
            'policy_count' => (string) $policyCount,
        ];

        return $this->buildRecommendation($definition, $vars, 0);
    }

    /**
     * Evaluate profile_missing — triggers when no protection profile exists.
     */
    private function evaluateProfileMissing(ProtectionActionDefinition $definition, array $comprehensivePlan): ?array
    {
        $userProfile = $comprehensivePlan['user_profile'] ?? [];

        // If user profile has meaningful data, profile exists
        if (! empty($userProfile) && isset($userProfile['age'])) {
            return null;
        }

        return $this->buildRecommendation($definition, [], 0);
    }

    /**
     * Evaluate no_policies_with_gaps — triggers when no policies exist and gaps are present.
     */
    private function evaluateNoPoliciesWithGaps(ProtectionActionDefinition $definition, array $comprehensivePlan): ?array
    {
        $currentCoverage = $comprehensivePlan['current_coverage'] ?? [];
        $policyCount = $this->countPolicies($currentCoverage);

        if ($policyCount > 0) {
            return null;
        }

        if (! $this->hasAnyGap($comprehensivePlan)) {
            return null;
        }

        $coverageAnalysis = $comprehensivePlan['coverage_analysis'] ?? [];
        $lifeGap = (float) ($coverageAnalysis['life_insurance']['gap'] ?? 0);
        $ciGap = (float) ($coverageAnalysis['critical_illness']['gap'] ?? 0);
        $ipGap = (float) ($coverageAnalysis['income_protection']['gap'] ?? 0);
        $totalGap = $lifeGap + $ciGap + ($ipGap * 12);

        $vars = [
            'total_gap' => $this->formatCurrency($totalGap),
        ];

        return $this->buildRecommendation($definition, $vars, 0);
    }

    /**
     * Build a recommendation array from a definition and template variables.
     */
    private function buildRecommendation(ProtectionActionDefinition $definition, array $vars, float $coverageAmount): array
    {
        $priorityMap = [
            'critical' => 1,
            'high' => 2,
            'medium' => 3,
            'low' => 4,
        ];

        return [
            'priority' => $priorityMap[$definition->priority] ?? 3,
            'category' => $definition->category,
            'action' => $definition->renderTitle($vars),
            'rationale' => $definition->renderDescription($vars),
            'impact' => ucfirst($definition->priority),
            'estimated_cost' => 0,
            'impact_parameters' => ['coverage_amount' => $coverageAmount],
            'timeframe' => 'Within 3 months',
        ];
    }

    /**
     * Build a human-readable description for a coverage gap.
     */
    private function buildGapDescription(string $coverageType, float $gap, float $coverage): string
    {
        return match ($coverageType) {
            'critical_illness' => $coverage > 0
                ? sprintf(
                    'Your critical illness cover of %s leaves a shortfall of %s against your calculated need.',
                    $this->formatCurrency($coverage),
                    $this->formatCurrency($gap)
                )
                : 'You have no critical illness cover. A serious diagnosis could leave you unable to meet financial commitments.',
            'income_protection' => $coverage > 0
                ? sprintf(
                    'Your income protection of %s per month leaves a shortfall of %s per month.',
                    $this->formatCurrency($coverage),
                    $this->formatCurrency($gap)
                )
                : 'You have no income protection. If illness or injury prevented you from working, you would have no replacement income.',
            default => sprintf(
                'Your current cover falls short of your calculated need by %s.',
                $this->formatCurrency($gap)
            ),
        };
    }

    /**
     * Count total policies from current coverage data.
     */
    private function countPolicies(array $currentCoverage): int
    {
        $lifePolicies = $currentCoverage['life_insurance']['policies'] ?? [];
        $ciPolicies = $currentCoverage['critical_illness']['policies'] ?? [];
        $ipPolicies = $currentCoverage['income_protection']['policies'] ?? [];

        return count($lifePolicies) + count($ciPolicies) + count($ipPolicies);
    }

    /**
     * Check if any coverage gap exists.
     */
    private function hasAnyGap(array $comprehensivePlan): bool
    {
        $coverageAnalysis = $comprehensivePlan['coverage_analysis'] ?? [];
        $lifeGap = (float) ($coverageAnalysis['life_insurance']['gap'] ?? 0);
        $ciGap = (float) ($coverageAnalysis['critical_illness']['gap'] ?? 0);
        $ipGap = (float) ($coverageAnalysis['income_protection']['gap'] ?? 0);

        return $lifeGap > 0 || $ciGap > 0 || $ipGap > 0;
    }
}
