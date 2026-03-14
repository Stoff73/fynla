<?php

declare(strict_types=1);

namespace App\Services\Protection;

use App\Models\CriticalIllnessPolicy;
use App\Models\IncomeProtectionPolicy;
use App\Models\LifeInsurancePolicy;
use App\Models\ProtectionActionDefinition;
use App\Models\User;
use App\Services\TaxConfigService;
use App\Traits\FormatsCurrency;

class ProtectionActionDefinitionService
{
    use FormatsCurrency;

    public function __construct(
        private readonly TaxConfigService $taxConfig
    ) {}

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
            $results = $this->evaluateDefinition($definition, $comprehensivePlan);

            if ($results === null) {
                continue;
            }

            // Some evaluators return multiple results (policy-level triggers)
            if (isset($results[0]) && is_array($results[0])) {
                foreach ($results as $result) {
                    $recommendations[] = $result;
                }
            } else {
                $recommendations[] = $results;
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

        return match ($condition) {
            // Existing conditions
            'gap_exists' => $this->evaluateGapCondition($definition, $comprehensivePlan),
            'strategy_recommendation' => $this->evaluateStrategyCondition($definition, $comprehensivePlan),
            'policies_exist_with_gaps' => $this->evaluatePoliciesExistWithGaps($definition, $comprehensivePlan),
            'multiple_policies' => $this->evaluateMultiplePolicies($definition, $comprehensivePlan),
            'profile_missing' => $this->evaluateProfileMissing($definition, $comprehensivePlan),
            'no_policies_with_gaps' => $this->evaluateNoPoliciesWithGaps($definition, $comprehensivePlan),

            // Employer benefits
            'dis_reliance_warning' => $this->evaluateDisReliance($definition, $comprehensivePlan),
            'no_employer_benefits_recorded' => $this->evaluateNoEmployerBenefits($definition, $comprehensivePlan),
            'group_ip_any_occupation' => $this->evaluateGroupIpDefinition($definition, $comprehensivePlan),

            // State benefits
            'ip_gap_after_state_benefits' => $this->evaluateIpGapAfterStateBenefits($definition, $comprehensivePlan),
            'self_employed_no_ip' => $this->evaluateSelfEmployedNoIp($definition, $comprehensivePlan),

            // Life insurance policy-level
            'policy_not_in_trust' => $this->evaluatePolicyNotInTrust($definition, $comprehensivePlan),
            'policy_not_joint_married' => $this->evaluatePolicyNotJoint($definition, $comprehensivePlan),
            'policy_expiring_soon' => $this->evaluatePolicyExpiringSoon($definition, $comprehensivePlan),
            'policy_expired' => $this->evaluatePolicyExpired($definition, $comprehensivePlan),
            'mortgage_no_decreasing_term' => $this->evaluateMortgageNoDecreasingTerm($definition, $comprehensivePlan),

            // Income protection policy-level
            'ip_any_occupation_definition' => $this->evaluateIpAnyOccupation($definition, $comprehensivePlan),
            'ip_short_benefit_period' => $this->evaluateIpShortBenefitPeriod($definition, $comprehensivePlan),
            'ip_long_deferred_period' => $this->evaluateIpLongDeferredPeriod($definition, $comprehensivePlan),

            // Critical illness
            'no_ci_with_mortgage' => $this->evaluateNoCiWithMortgage($definition, $comprehensivePlan),
            'ci_combined_risk' => $this->evaluateCiCombinedRisk($definition, $comprehensivePlan),

            // Dependants and spouse
            'dependants_no_life_cover' => $this->evaluateDependantsNoLifeCover($definition, $comprehensivePlan),
            'education_funding_gap' => $this->evaluateEducationFundingGap($definition, $comprehensivePlan),
            'non_earning_spouse_no_cover' => $this->evaluateNonEarningSpouse($definition, $comprehensivePlan),

            default => null,
        };
    }

    // =============================================
    // Existing Evaluators
    // =============================================

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

    // =============================================
    // Employer Benefits Evaluators
    // =============================================

    /**
     * Evaluate dis_reliance_warning — triggers when death in service exceeds threshold of total life cover.
     */
    private function evaluateDisReliance(ProtectionActionDefinition $definition, array $comprehensivePlan): ?array
    {
        $protectionNeeds = $comprehensivePlan['protection_needs'] ?? [];
        $financialSummary = $comprehensivePlan['financial_summary'] ?? [];
        $currentCoverage = $comprehensivePlan['current_coverage'] ?? [];

        // Get employer benefits from the coverage data if available
        $lifeCoverage = (float) ($currentCoverage['life_insurance']['total_coverage'] ?? 0);

        // Access profile data from the plan — employer benefit data is surfaced here
        $userProfile = $comprehensivePlan['user_profile'] ?? [];
        $disMultiple = $this->getProfileValue($comprehensivePlan, 'death_in_service_multiple');

        if ($disMultiple === null || $disMultiple <= 0 || $lifeCoverage <= 0) {
            return null;
        }

        // Calculate death in service amount
        $salary = (float) ($financialSummary['income_breakdown']['employment_income'] ?? 0);
        if ($salary <= 0) {
            return null;
        }

        $deathInService = $disMultiple * $salary;
        $disRelianceThreshold = (float) $this->taxConfig->get('protection.dis_reliance_percent', 0.50);

        // Death in service + personal life cover = total life cover
        // Check if DIS portion exceeds threshold of total
        $totalLifeIncludingDis = $lifeCoverage;
        if ($totalLifeIncludingDis > 0 && ($deathInService / $totalLifeIncludingDis) > $disRelianceThreshold) {
            return $this->buildRecommendation($definition, [], 0);
        }

        return null;
    }

    /**
     * Evaluate no_employer_benefits_recorded — triggers when employed but no employer benefits on profile.
     */
    private function evaluateNoEmployerBenefits(ProtectionActionDefinition $definition, array $comprehensivePlan): ?array
    {
        $financialSummary = $comprehensivePlan['financial_summary'] ?? [];
        $employmentIncome = (float) ($financialSummary['income_breakdown']['employment_income'] ?? 0);

        // Only triggers for employed users
        if ($employmentIncome <= 0) {
            return null;
        }

        // Check if any employer benefit has been recorded
        $disMultiple = $this->getProfileValue($comprehensivePlan, 'death_in_service_multiple');
        $groupIp = $this->getProfileValue($comprehensivePlan, 'group_ip_benefit_percent');
        $groupCi = $this->getProfileValue($comprehensivePlan, 'group_ci_amount');
        $hasPmi = $this->getProfileValue($comprehensivePlan, 'has_employer_pmi');

        $hasAnyBenefit = ($disMultiple !== null && $disMultiple > 0)
            || ($groupIp !== null && $groupIp > 0)
            || ($groupCi !== null && $groupCi > 0)
            || ($hasPmi === true);

        if ($hasAnyBenefit) {
            return null;
        }

        return $this->buildRecommendation($definition, [], 0);
    }

    /**
     * Evaluate group_ip_any_occupation — triggers when group IP uses 'any occupation' definition.
     */
    private function evaluateGroupIpDefinition(ProtectionActionDefinition $definition, array $comprehensivePlan): ?array
    {
        $groupIpDefinition = $this->getProfileValue($comprehensivePlan, 'group_ip_definition');

        if ($groupIpDefinition === null || strtolower((string) $groupIpDefinition) !== 'any') {
            return null;
        }

        return $this->buildRecommendation($definition, [], 0);
    }

    // =============================================
    // State Benefits Evaluators
    // =============================================

    /**
     * Evaluate ip_gap_after_state_benefits — triggers when IP gap remains after SSP offset.
     */
    private function evaluateIpGapAfterStateBenefits(ProtectionActionDefinition $definition, array $comprehensivePlan): ?array
    {
        $coverageAnalysis = $comprehensivePlan['coverage_analysis'] ?? [];
        $ipData = $coverageAnalysis['income_protection'] ?? [];
        $ipGap = (float) ($ipData['gap'] ?? 0);

        if ($ipGap <= 0) {
            return null;
        }

        // Get state benefit data from protection needs
        $protectionNeeds = $comprehensivePlan['protection_needs'] ?? [];
        $incomeAnalysis = $protectionNeeds['income_analysis'] ?? [];
        $stateBenefits = $this->getNestedValue($comprehensivePlan, 'protection_needs.state_benefits', []);

        // Use SSP total if available, otherwise calculate from config
        $sspTotal = 0.0;
        if (is_array($stateBenefits) && isset($stateBenefits['ssp_total_entitlement'])) {
            $sspTotal = (float) $stateBenefits['ssp_total_entitlement'];
        } else {
            $sspWeekly = (float) $this->taxConfig->get('benefits.ssp.weekly_rate', 116.75);
            $sspMaxWeeks = (int) $this->taxConfig->get('benefits.ssp.max_weeks', 28);
            $sspTotal = $sspWeekly * $sspMaxWeeks;
        }

        $vars = [
            'ssp_total' => $this->formatCurrency($sspTotal),
        ];

        return $this->buildRecommendation($definition, $vars, $ipGap);
    }

    /**
     * Evaluate self_employed_no_ip — triggers when self-employed user has no income protection.
     */
    private function evaluateSelfEmployedNoIp(ProtectionActionDefinition $definition, array $comprehensivePlan): ?array
    {
        $financialSummary = $comprehensivePlan['financial_summary'] ?? [];
        $employmentIncome = (float) ($financialSummary['income_breakdown']['employment_income'] ?? 0);
        $selfEmploymentIncome = (float) ($financialSummary['income_breakdown']['self_employment_income'] ?? 0);

        // Must be self-employed (has self-employment income, no employment income)
        $isSelfEmployed = $selfEmploymentIncome > 0 && $employmentIncome <= 0;
        if (! $isSelfEmployed) {
            return null;
        }

        // Check if user has any income protection policies
        $currentCoverage = $comprehensivePlan['current_coverage'] ?? [];
        $ipPolicies = $currentCoverage['income_protection']['policies'] ?? [];

        if (count($ipPolicies) > 0) {
            return null;
        }

        return $this->buildRecommendation($definition, [], 0);
    }

    // =============================================
    // Life Insurance Policy-Level Evaluators
    // =============================================

    /**
     * Evaluate policy_not_in_trust — triggers for life policies not in trust.
     * Returns multiple results (one per untrusted policy).
     */
    private function evaluatePolicyNotInTrust(ProtectionActionDefinition $definition, array $comprehensivePlan): ?array
    {
        $currentCoverage = $comprehensivePlan['current_coverage'] ?? [];
        $lifePolicies = $currentCoverage['life_insurance']['policies'] ?? [];

        if (empty($lifePolicies)) {
            return null;
        }

        // Query actual policy models to check in_trust field
        $userId = $this->extractUserId($comprehensivePlan);
        if ($userId === null) {
            return null;
        }

        $untrustedPolicies = LifeInsurancePolicy::where('user_id', $userId)
            ->whereNull('deleted_at')
            ->where(function ($q) {
                $q->where('in_trust', false)->orWhereNull('in_trust');
            })
            ->get();

        if ($untrustedPolicies->isEmpty()) {
            return null;
        }

        $results = [];
        foreach ($untrustedPolicies as $policy) {
            $vars = [
                'provider' => $policy->provider ?? 'your insurer',
            ];
            $results[] = $this->buildRecommendation($definition, $vars, 0);
        }

        return $results;
    }

    /**
     * Evaluate policy_not_joint_married — triggers when married user has non-joint life policy.
     */
    private function evaluatePolicyNotJoint(ProtectionActionDefinition $definition, array $comprehensivePlan): ?array
    {
        $userProfile = $comprehensivePlan['user_profile'] ?? [];
        $maritalStatus = strtolower((string) ($userProfile['marital_status'] ?? ''));

        if ($maritalStatus !== 'married' && $maritalStatus !== 'civil partnership') {
            return null;
        }

        $userId = $this->extractUserId($comprehensivePlan);
        if ($userId === null) {
            return null;
        }

        $nonJointPolicies = LifeInsurancePolicy::where('user_id', $userId)
            ->whereNull('deleted_at')
            ->where(function ($q) {
                $q->where('joint_life', false)->orWhereNull('joint_life');
            })
            ->get();

        if ($nonJointPolicies->isEmpty()) {
            return null;
        }

        $results = [];
        foreach ($nonJointPolicies as $policy) {
            $vars = [
                'provider' => $policy->provider ?? 'your insurer',
            ];
            $results[] = $this->buildRecommendation($definition, $vars, 0);
        }

        return $results;
    }

    /**
     * Evaluate policy_expiring_soon — triggers when policy end date is within configured months.
     */
    private function evaluatePolicyExpiringSoon(ProtectionActionDefinition $definition, array $comprehensivePlan): ?array
    {
        $userId = $this->extractUserId($comprehensivePlan);
        if ($userId === null) {
            return null;
        }

        $monthsThreshold = (int) ($definition->trigger_config['months_threshold'] ?? 24);
        $thresholdDate = now()->addMonths($monthsThreshold);

        $expiringPolicies = LifeInsurancePolicy::where('user_id', $userId)
            ->whereNull('deleted_at')
            ->whereNotNull('policy_end_date')
            ->where('policy_end_date', '>', now())
            ->where('policy_end_date', '<=', $thresholdDate)
            ->get();

        if ($expiringPolicies->isEmpty()) {
            return null;
        }

        $results = [];
        foreach ($expiringPolicies as $policy) {
            $vars = [
                'provider' => $policy->provider ?? 'your insurer',
                'end_date' => $policy->policy_end_date->format('j F Y'),
            ];
            $results[] = $this->buildRecommendation($definition, $vars, 0);
        }

        return $results;
    }

    /**
     * Evaluate policy_expired — triggers when policy end date is in the past.
     */
    private function evaluatePolicyExpired(ProtectionActionDefinition $definition, array $comprehensivePlan): ?array
    {
        $userId = $this->extractUserId($comprehensivePlan);
        if ($userId === null) {
            return null;
        }

        $expiredPolicies = LifeInsurancePolicy::where('user_id', $userId)
            ->whereNull('deleted_at')
            ->whereNotNull('policy_end_date')
            ->where('policy_end_date', '<', now())
            ->get();

        if ($expiredPolicies->isEmpty()) {
            return null;
        }

        $results = [];
        foreach ($expiredPolicies as $policy) {
            $vars = [
                'provider' => $policy->provider ?? 'your insurer',
                'end_date' => $policy->policy_end_date->format('j F Y'),
            ];
            $results[] = $this->buildRecommendation($definition, $vars, 0);
        }

        return $results;
    }

    /**
     * Evaluate mortgage_no_decreasing_term — triggers when user has mortgage but no decreasing term policy.
     */
    private function evaluateMortgageNoDecreasingTerm(ProtectionActionDefinition $definition, array $comprehensivePlan): ?array
    {
        $financialSummary = $comprehensivePlan['financial_summary'] ?? [];
        $totalDebt = (float) ($financialSummary['total_debt'] ?? 0);
        $debtBreakdown = $financialSummary['debt_breakdown'] ?? [];
        $mortgageDebt = (float) ($debtBreakdown['mortgage'] ?? 0);

        if ($mortgageDebt <= 0) {
            return null;
        }

        $userId = $this->extractUserId($comprehensivePlan);
        if ($userId === null) {
            return null;
        }

        // Check for decreasing term or mortgage protection policies
        $hasMortgageProtection = LifeInsurancePolicy::where('user_id', $userId)
            ->whereNull('deleted_at')
            ->where(function ($q) {
                $q->where('policy_type', 'decreasing_term')
                    ->orWhere('is_mortgage_protection', true);
            })
            ->exists();

        if ($hasMortgageProtection) {
            return null;
        }

        $vars = [
            'mortgage_amount' => $this->formatCurrency($mortgageDebt),
        ];

        return $this->buildRecommendation($definition, $vars, $mortgageDebt);
    }

    // =============================================
    // Income Protection Policy-Level Evaluators
    // =============================================

    /**
     * Evaluate ip_any_occupation_definition — triggers when personal IP uses 'any occupation'.
     */
    private function evaluateIpAnyOccupation(ProtectionActionDefinition $definition, array $comprehensivePlan): ?array
    {
        $userId = $this->extractUserId($comprehensivePlan);
        if ($userId === null) {
            return null;
        }

        $anyOccupationPolicies = IncomeProtectionPolicy::where('user_id', $userId)
            ->whereNull('deleted_at')
            ->where('occupation_class', 'any')
            ->get();

        if ($anyOccupationPolicies->isEmpty()) {
            return null;
        }

        $results = [];
        foreach ($anyOccupationPolicies as $policy) {
            $vars = [
                'provider' => $policy->provider ?? 'your insurer',
            ];
            $results[] = $this->buildRecommendation($definition, $vars, 0);
        }

        return $results;
    }

    /**
     * Evaluate ip_short_benefit_period — triggers when benefit period is below threshold.
     */
    private function evaluateIpShortBenefitPeriod(ProtectionActionDefinition $definition, array $comprehensivePlan): ?array
    {
        $userId = $this->extractUserId($comprehensivePlan);
        if ($userId === null) {
            return null;
        }

        $monthsThreshold = (int) ($definition->trigger_config['months_threshold'] ?? 24);

        $shortPolicies = IncomeProtectionPolicy::where('user_id', $userId)
            ->whereNull('deleted_at')
            ->whereNotNull('benefit_period_months')
            ->where('benefit_period_months', '>', 0)
            ->where('benefit_period_months', '<', $monthsThreshold)
            ->get();

        if ($shortPolicies->isEmpty()) {
            return null;
        }

        $results = [];
        foreach ($shortPolicies as $policy) {
            $vars = [
                'benefit_months' => (string) $policy->benefit_period_months,
                'provider' => $policy->provider ?? 'your insurer',
            ];
            $results[] = $this->buildRecommendation($definition, $vars, 0);
        }

        return $results;
    }

    /**
     * Evaluate ip_long_deferred_period — triggers when deferred period exceeds threshold
     * and user has no employer sick pay to bridge the gap.
     */
    private function evaluateIpLongDeferredPeriod(ProtectionActionDefinition $definition, array $comprehensivePlan): ?array
    {
        $userId = $this->extractUserId($comprehensivePlan);
        if ($userId === null) {
            return null;
        }

        $weeksThreshold = (int) ($definition->trigger_config['weeks_threshold'] ?? 26);

        // Check if employer provides sick pay (group IP covers the gap)
        $groupIpPercent = $this->getProfileValue($comprehensivePlan, 'group_ip_benefit_percent');
        $hasEmployerSickPay = $groupIpPercent !== null && $groupIpPercent > 0;

        if ($hasEmployerSickPay) {
            return null;
        }

        $longDeferredPolicies = IncomeProtectionPolicy::where('user_id', $userId)
            ->whereNull('deleted_at')
            ->whereNotNull('deferred_period_weeks')
            ->where('deferred_period_weeks', '>', $weeksThreshold)
            ->get();

        if ($longDeferredPolicies->isEmpty()) {
            return null;
        }

        $results = [];
        foreach ($longDeferredPolicies as $policy) {
            $vars = [
                'deferred_weeks' => (string) $policy->deferred_period_weeks,
                'provider' => $policy->provider ?? 'your insurer',
            ];
            $results[] = $this->buildRecommendation($definition, $vars, 0);
        }

        return $results;
    }

    // =============================================
    // Critical Illness Evaluators
    // =============================================

    /**
     * Evaluate no_ci_with_mortgage — triggers when user has mortgage but no CI cover.
     */
    private function evaluateNoCiWithMortgage(ProtectionActionDefinition $definition, array $comprehensivePlan): ?array
    {
        $financialSummary = $comprehensivePlan['financial_summary'] ?? [];
        $debtBreakdown = $financialSummary['debt_breakdown'] ?? [];
        $mortgageDebt = (float) ($debtBreakdown['mortgage'] ?? 0);

        if ($mortgageDebt <= 0) {
            return null;
        }

        $coverageAnalysis = $comprehensivePlan['coverage_analysis'] ?? [];
        $ciCoverage = (float) ($coverageAnalysis['critical_illness']['coverage'] ?? 0);

        if ($ciCoverage > 0) {
            return null;
        }

        $vars = [
            'mortgage_amount' => $this->formatCurrency($mortgageDebt),
        ];

        return $this->buildRecommendation($definition, $vars, $mortgageDebt);
    }

    /**
     * Evaluate ci_combined_risk — triggers when CI policy type is 'combined' (life + CI).
     */
    private function evaluateCiCombinedRisk(ProtectionActionDefinition $definition, array $comprehensivePlan): ?array
    {
        $userId = $this->extractUserId($comprehensivePlan);
        if ($userId === null) {
            return null;
        }

        $combinedPolicies = CriticalIllnessPolicy::where('user_id', $userId)
            ->whereNull('deleted_at')
            ->where('policy_type', 'combined')
            ->get();

        if ($combinedPolicies->isEmpty()) {
            return null;
        }

        $results = [];
        foreach ($combinedPolicies as $policy) {
            $vars = [
                'provider' => $policy->provider ?? 'your insurer',
            ];
            $results[] = $this->buildRecommendation($definition, $vars, 0);
        }

        return $results;
    }

    // =============================================
    // Dependants and Spouse Evaluators
    // =============================================

    /**
     * Evaluate dependants_no_life_cover — triggers when user has dependants but zero life cover.
     */
    private function evaluateDependantsNoLifeCover(ProtectionActionDefinition $definition, array $comprehensivePlan): ?array
    {
        $userProfile = $comprehensivePlan['user_profile'] ?? [];
        $dependants = (int) ($userProfile['number_of_dependents'] ?? 0);

        if ($dependants <= 0) {
            return null;
        }

        $currentCoverage = $comprehensivePlan['current_coverage'] ?? [];
        $lifeCoverage = (float) ($currentCoverage['life_insurance']['total_coverage'] ?? 0);

        if ($lifeCoverage > 0) {
            return null;
        }

        $vars = [
            'dependant_count' => (string) $dependants,
        ];

        return $this->buildRecommendation($definition, $vars, 0);
    }

    /**
     * Evaluate education_funding_gap — triggers when education funding gap exists.
     */
    private function evaluateEducationFundingGap(ProtectionActionDefinition $definition, array $comprehensivePlan): ?array
    {
        $protectionNeeds = $comprehensivePlan['protection_needs'] ?? [];
        $breakdown = $protectionNeeds['breakdown'] ?? [];
        $educationFunding = (float) ($breakdown['education_funding'] ?? 0);

        if ($educationFunding <= 0) {
            return null;
        }

        // Check if the education portion is covered by existing life cover
        // If there's an overall gap, education funding is at risk
        $coverageAnalysis = $comprehensivePlan['coverage_analysis'] ?? [];
        $lifeGap = (float) ($coverageAnalysis['life_insurance']['gap'] ?? 0);

        if ($lifeGap <= 0) {
            return null;
        }

        // Education gap is the lesser of the education funding need and the total life gap
        $educationGap = min($educationFunding, $lifeGap);

        $vars = [
            'gap_amount' => $this->formatCurrency($educationGap),
        ];

        return $this->buildRecommendation($definition, $vars, $educationGap);
    }

    /**
     * Evaluate non_earning_spouse_no_cover — triggers when non-earning spouse has no cover
     * and user has dependants.
     */
    private function evaluateNonEarningSpouse(ProtectionActionDefinition $definition, array $comprehensivePlan): ?array
    {
        $userProfile = $comprehensivePlan['user_profile'] ?? [];
        $maritalStatus = strtolower((string) ($userProfile['marital_status'] ?? ''));
        $dependants = (int) ($userProfile['number_of_dependents'] ?? 0);

        // Must be married/civil partnership with dependants
        if (! in_array($maritalStatus, ['married', 'civil partnership'])) {
            return null;
        }

        if ($dependants <= 0) {
            return null;
        }

        // Check if spouse has earned income
        $protectionNeeds = $comprehensivePlan['protection_needs'] ?? [];
        $spouseInfo = $protectionNeeds['spouse_info'] ?? [];
        $spouseGrossIncome = (float) ($spouseInfo['spouse_gross_income'] ?? 0);

        // If spouse has earned income, this trigger does not apply
        if ($spouseGrossIncome > 0) {
            return null;
        }

        // Check if spouse is included in analysis (permission granted)
        $spouseIncluded = (bool) ($spouseInfo['spouse_included'] ?? false);
        if (! $spouseIncluded) {
            // Cannot determine spouse income — skip trigger
            return null;
        }

        return $this->buildRecommendation($definition, [], 0);
    }

    // =============================================
    // Helper Methods
    // =============================================

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

    /**
     * Extract user ID from the comprehensive plan data.
     */
    private function extractUserId(array $comprehensivePlan): ?int
    {
        // The plan_metadata contains user_name, but we need the ID
        // Look through the plan for an email or retrieve from personal_information
        $personalInfo = $comprehensivePlan['personal_information'] ?? [];
        $email = $personalInfo['email'] ?? null;

        if ($email) {
            $user = User::where('email', $email)->first();

            return $user?->id;
        }

        // Fallback: check user_profile for email
        $userProfile = $comprehensivePlan['user_profile'] ?? [];
        $profileEmail = $userProfile['email'] ?? null;

        if ($profileEmail) {
            $user = User::where('email', $profileEmail)->first();

            return $user?->id;
        }

        return null;
    }

    /**
     * Get a profile value from the comprehensive plan data.
     * Checks multiple locations where profile data may be stored.
     */
    private function getProfileValue(array $comprehensivePlan, string $key): mixed
    {
        // Check user_profile first (from ComprehensiveProtectionPlanService::buildUserProfile)
        $userProfile = $comprehensivePlan['user_profile'] ?? [];
        if (isset($userProfile[$key])) {
            return $userProfile[$key];
        }

        // Check personal_information (from ProtectionPlanService::buildPersonalInformation)
        $personalInfo = $comprehensivePlan['personal_information'] ?? [];
        if (isset($personalInfo[$key])) {
            return $personalInfo[$key];
        }

        // Check financial_summary for employer benefit data
        $financialSummary = $comprehensivePlan['financial_summary'] ?? [];
        if (isset($financialSummary[$key])) {
            return $financialSummary[$key];
        }

        // For employer benefit fields, try to look up from the user's protection profile
        $email = $userProfile['email'] ?? ($personalInfo['email'] ?? null);
        if ($email) {
            $user = User::where('email', $email)->first();
            if ($user?->protectionProfile) {
                $profile = $user->protectionProfile;
                if (isset($profile->{$key})) {
                    return $profile->{$key};
                }
            }
        }

        return null;
    }

    /**
     * Get a nested value from the comprehensive plan using dot notation.
     */
    private function getNestedValue(array $data, string $dotPath, mixed $default = null): mixed
    {
        $keys = explode('.', $dotPath);
        $current = $data;

        foreach ($keys as $key) {
            if (! is_array($current) || ! array_key_exists($key, $current)) {
                return $default;
            }
            $current = $current[$key];
        }

        return $current;
    }
}
