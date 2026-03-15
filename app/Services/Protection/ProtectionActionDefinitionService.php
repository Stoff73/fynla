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
        $trace = [];

        $triggerConfig = $definition->trigger_config;
        $coverageType = $triggerConfig['coverage_type'] ?? '';
        $threshold = (float) ($triggerConfig['threshold'] ?? 0);

        $coverageAnalysis = $comprehensivePlan['coverage_analysis'] ?? [];
        $typeData = $coverageAnalysis[$coverageType] ?? [];
        $gap = (float) ($typeData['gap'] ?? 0);
        $coverage = (float) ($typeData['coverage'] ?? 0);
        $need = (float) ($typeData['need'] ?? 0);

        $coverageLabel = str_replace('_', ' ', $coverageType);

        $trace[] = [
            'question' => 'Is there a gap in your '.$coverageLabel.' cover?',
            'data_field' => ucfirst($coverageLabel).' gap',
            'data_value' => '£'.number_format($gap, 0),
            'threshold' => '£'.number_format($threshold, 0).' (minimum gap to trigger)',
            'passed' => $gap <= $threshold,
            'explanation' => $gap <= $threshold
                ? 'Your '.$coverageLabel.' gap is within acceptable limits.'
                : 'Your '.$coverageLabel.' has a shortfall of £'.number_format($gap, 0).' against your calculated need of £'.number_format($need, 0).'.',
        ];

        if ($gap <= $threshold) {
            return null;
        }

        $trace[] = [
            'question' => 'How much '.$coverageLabel.' cover do you currently have?',
            'data_field' => 'Current '.$coverageLabel.' cover',
            'data_value' => '£'.number_format($coverage, 0),
            'threshold' => '£'.number_format($need, 0).' (calculated need)',
            'passed' => false,
            'explanation' => 'You need an additional £'.number_format($gap, 0).' of '.$coverageLabel.' cover to meet your calculated need.',
        ];

        // Build description text based on coverage type
        $descriptionText = $this->buildGapDescription($coverageType, $gap, $coverage);

        $vars = [
            'gap_amount' => $this->formatCurrency($gap),
            'need_amount' => $this->formatCurrency($need),
            'coverage_amount' => $this->formatCurrency($coverage),
            'description_text' => $descriptionText,
        ];

        $rec = $this->buildRecommendation($definition, $vars, $gap);
        $rec['decision_trace'] = $trace;

        return $rec;
    }

    /**
     * Evaluate strategy_recommendation — matches optimized strategy recommendations by category.
     */
    private function evaluateStrategyCondition(ProtectionActionDefinition $definition, array $comprehensivePlan): ?array
    {
        $trace = [];

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

        $trace[] = [
            'question' => 'Does the optimised strategy include a recommendation for "'.$categoryMatch.'"?',
            'data_field' => 'Matching strategy recommendations',
            'data_value' => (string) count($matched).' found',
            'threshold' => 'At least 1 matching recommendation',
            'passed' => empty($matched),
            'explanation' => empty($matched)
                ? 'No strategy recommendations match the "'.$categoryMatch.'" category.'
                : count($matched).' strategy recommendation(s) found for "'.$categoryMatch.'".',
        ];

        if (empty($matched)) {
            return null;
        }

        // Use the first matching recommendation's data
        $rec = $matched[0];
        $coverageAmount = $rec['coverage_amount'] ?? $rec['monthly_benefit'] ?? 0;
        $monthlyCost = $rec['estimated_monthly_cost'] ?? 0;

        $trace[] = [
            'question' => 'What coverage amount and cost does the strategy recommend?',
            'data_field' => 'Recommended coverage',
            'data_value' => '£'.number_format((float) $coverageAmount, 0).' cover, £'.number_format((float) $monthlyCost, 0).' per month',
            'threshold' => 'N/A',
            'passed' => false,
            'explanation' => 'The strategy recommends £'.number_format((float) $coverageAmount, 0).' of coverage at an estimated cost of £'.number_format((float) $monthlyCost, 0).' per month.',
        ];

        $vars = [
            'action_text' => $rec['action'] ?? 'Review coverage',
            'details_text' => $rec['details'] ?? '',
            'coverage_amount' => $this->formatCurrency($coverageAmount),
            'monthly_cost' => $this->formatCurrency($monthlyCost),
        ];

        $result = [
            'priority' => $rec['priority'] ?? 3,
            'category' => $definition->category,
            'action' => $definition->renderTitle($vars),
            'rationale' => $definition->renderDescription($vars),
            'impact' => $rec['importance'] ?? 'Medium',
            'estimated_cost' => round((float) $monthlyCost, 2),
            'impact_parameters' => ['coverage_amount' => $coverageAmount],
            'timeframe' => $rec['timeframe'] ?? 'Within 3 months',
            'decision_trace' => $trace,
        ];

        return $result;
    }

    /**
     * Evaluate policies_exist_with_gaps — triggers when policies exist but gaps remain.
     */
    private function evaluatePoliciesExistWithGaps(ProtectionActionDefinition $definition, array $comprehensivePlan): ?array
    {
        $trace = [];

        $currentCoverage = $comprehensivePlan['current_coverage'] ?? [];
        $policyCount = $this->countPolicies($currentCoverage);

        $trace[] = [
            'question' => 'Do you have any existing protection policies?',
            'data_field' => 'Total policy count',
            'data_value' => (string) $policyCount.' policies',
            'threshold' => 'At least 1 policy',
            'passed' => $policyCount > 0,
            'explanation' => $policyCount > 0
                ? 'You have '.$policyCount.' existing protection policies.'
                : 'You have no existing protection policies.',
        ];

        if ($policyCount === 0) {
            return null;
        }

        $hasGap = $this->hasAnyGap($comprehensivePlan);

        $trace[] = [
            'question' => 'Are there any remaining coverage gaps despite your existing policies?',
            'data_field' => 'Coverage gaps remaining',
            'data_value' => $hasGap ? 'Yes' : 'No',
            'threshold' => 'No gaps',
            'passed' => ! $hasGap,
            'explanation' => $hasGap
                ? 'Despite having '.$policyCount.' policies, coverage gaps remain.'
                : 'Your existing policies fully cover your protection needs.',
        ];

        if (! $hasGap) {
            return null;
        }

        $vars = [
            'policy_count' => (string) $policyCount,
        ];

        $rec = $this->buildRecommendation($definition, $vars, 0);
        $rec['decision_trace'] = $trace;

        return $rec;
    }

    /**
     * Evaluate multiple_policies — triggers when policy count exceeds threshold.
     */
    private function evaluateMultiplePolicies(ProtectionActionDefinition $definition, array $comprehensivePlan): ?array
    {
        $trace = [];

        $threshold = (int) ($definition->trigger_config['threshold'] ?? 3);
        $currentCoverage = $comprehensivePlan['current_coverage'] ?? [];
        $policyCount = $this->countPolicies($currentCoverage);

        $trace[] = [
            'question' => 'Do you have a large number of separate protection policies?',
            'data_field' => 'Total policy count',
            'data_value' => (string) $policyCount.' policies',
            'threshold' => (string) $threshold.' or more policies',
            'passed' => $policyCount < $threshold,
            'explanation' => $policyCount < $threshold
                ? 'You have '.$policyCount.' policies, which is manageable.'
                : 'You have '.$policyCount.' policies. Consolidating could simplify your cover and potentially reduce costs.',
        ];

        if ($policyCount < $threshold) {
            return null;
        }

        $vars = [
            'policy_count' => (string) $policyCount,
        ];

        $rec = $this->buildRecommendation($definition, $vars, 0);
        $rec['decision_trace'] = $trace;

        return $rec;
    }

    /**
     * Evaluate profile_missing — triggers when no protection profile exists.
     */
    private function evaluateProfileMissing(ProtectionActionDefinition $definition, array $comprehensivePlan): ?array
    {
        $trace = [];

        $userProfile = $comprehensivePlan['user_profile'] ?? [];
        $hasProfile = ! empty($userProfile) && isset($userProfile['age']);

        $trace[] = [
            'question' => 'Have you completed your protection profile?',
            'data_field' => 'Protection profile',
            'data_value' => $hasProfile ? 'Complete' : 'Missing',
            'threshold' => 'Profile must exist with age data',
            'passed' => $hasProfile,
            'explanation' => $hasProfile
                ? 'Your protection profile is set up with the information needed for analysis.'
                : 'Your protection profile is incomplete. We need your personal details to calculate your cover requirements.',
        ];

        // If user profile has meaningful data, profile exists
        if ($hasProfile) {
            return null;
        }

        $rec = $this->buildRecommendation($definition, [], 0);
        $rec['decision_trace'] = $trace;

        return $rec;
    }

    /**
     * Evaluate no_policies_with_gaps — triggers when no policies exist and gaps are present.
     */
    private function evaluateNoPoliciesWithGaps(ProtectionActionDefinition $definition, array $comprehensivePlan): ?array
    {
        $trace = [];

        $currentCoverage = $comprehensivePlan['current_coverage'] ?? [];
        $policyCount = $this->countPolicies($currentCoverage);

        $trace[] = [
            'question' => 'Do you have any existing protection policies?',
            'data_field' => 'Total policy count',
            'data_value' => (string) $policyCount.' policies',
            'threshold' => '0 policies (no cover at all)',
            'passed' => $policyCount > 0,
            'explanation' => $policyCount > 0
                ? 'You have '.$policyCount.' existing protection policies.'
                : 'You have no protection policies in place.',
        ];

        if ($policyCount > 0) {
            return null;
        }

        $hasGap = $this->hasAnyGap($comprehensivePlan);

        $trace[] = [
            'question' => 'Do you have any protection needs that are not being met?',
            'data_field' => 'Coverage gaps exist',
            'data_value' => $hasGap ? 'Yes' : 'No',
            'threshold' => 'No gaps',
            'passed' => ! $hasGap,
            'explanation' => $hasGap
                ? 'You have unmet protection needs with no policies in place to cover them.'
                : 'No coverage gaps identified based on your circumstances.',
        ];

        if (! $hasGap) {
            return null;
        }

        $coverageAnalysis = $comprehensivePlan['coverage_analysis'] ?? [];
        $lifeGap = (float) ($coverageAnalysis['life_insurance']['gap'] ?? 0);
        $ciGap = (float) ($coverageAnalysis['critical_illness']['gap'] ?? 0);
        $ipGap = (float) ($coverageAnalysis['income_protection']['gap'] ?? 0);
        $totalGap = $lifeGap + $ciGap + ($ipGap * 12);

        $trace[] = [
            'question' => 'What is the total protection shortfall across all cover types?',
            'data_field' => 'Total protection gap',
            'data_value' => '£'.number_format($totalGap, 0),
            'threshold' => '£0 (fully covered)',
            'passed' => false,
            'explanation' => 'Your total protection shortfall is £'.number_format($totalGap, 0).', comprising life insurance (£'.number_format($lifeGap, 0).'), critical illness (£'.number_format($ciGap, 0).'), and income protection (£'.number_format($ipGap * 12, 0).' annualised).',
        ];

        $vars = [
            'total_gap' => $this->formatCurrency($totalGap),
        ];

        $rec = $this->buildRecommendation($definition, $vars, 0);
        $rec['decision_trace'] = $trace;

        return $rec;
    }

    // =============================================
    // Employer Benefits Evaluators
    // =============================================

    /**
     * Evaluate dis_reliance_warning — triggers when death in service exceeds threshold of total life cover.
     */
    private function evaluateDisReliance(ProtectionActionDefinition $definition, array $comprehensivePlan): ?array
    {
        $trace = [];

        $protectionNeeds = $comprehensivePlan['protection_needs'] ?? [];
        $financialSummary = $comprehensivePlan['financial_summary'] ?? [];
        $currentCoverage = $comprehensivePlan['current_coverage'] ?? [];

        // Get employer benefits from the coverage data if available
        $lifeCoverage = (float) ($currentCoverage['life_insurance']['total_coverage'] ?? 0);

        // Access profile data from the plan — employer benefit data is surfaced here
        $userProfile = $comprehensivePlan['user_profile'] ?? [];
        $disMultiple = $this->getProfileValue($comprehensivePlan, 'death_in_service_multiple');

        $trace[] = [
            'question' => 'Do you have a death in service benefit through your employer?',
            'data_field' => 'Death in service multiple',
            'data_value' => $disMultiple !== null && $disMultiple > 0 ? $disMultiple.'x salary' : 'None',
            'threshold' => 'Must have death in service benefit and life cover',
            'passed' => ! ($disMultiple !== null && $disMultiple > 0 && $lifeCoverage > 0),
            'explanation' => $disMultiple !== null && $disMultiple > 0
                ? 'You have a death in service benefit of '.$disMultiple.' times your salary.'
                : 'No death in service benefit recorded.',
        ];

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
        $disRatio = $totalLifeIncludingDis > 0 ? ($deathInService / $totalLifeIncludingDis) : 0;
        $overReliant = $totalLifeIncludingDis > 0 && $disRatio > $disRelianceThreshold;

        $trace[] = [
            'question' => 'Are you over-reliant on your employer death in service benefit?',
            'data_field' => 'Death in service as proportion of total life cover',
            'data_value' => round($disRatio * 100, 1).'% (£'.number_format($deathInService, 0).' of £'.number_format($totalLifeIncludingDis, 0).')',
            'threshold' => round($disRelianceThreshold * 100, 0).'% maximum reliance',
            'passed' => ! $overReliant,
            'explanation' => $overReliant
                ? 'Your death in service benefit makes up '.round($disRatio * 100, 1).'% of your total life cover. If you changed employer, you could lose a significant portion of your protection.'
                : 'Your death in service benefit is a reasonable proportion of your total life cover.',
        ];

        if ($overReliant) {
            $rec = $this->buildRecommendation($definition, [], 0);
            $rec['decision_trace'] = $trace;

            return $rec;
        }

        return null;
    }

    /**
     * Evaluate no_employer_benefits_recorded — triggers when employed but no employer benefits on profile.
     */
    private function evaluateNoEmployerBenefits(ProtectionActionDefinition $definition, array $comprehensivePlan): ?array
    {
        $trace = [];

        $financialSummary = $comprehensivePlan['financial_summary'] ?? [];
        $employmentIncome = (float) ($financialSummary['income_breakdown']['employment_income'] ?? 0);

        $trace[] = [
            'question' => 'Are you currently employed?',
            'data_field' => 'Employment income',
            'data_value' => '£'.number_format($employmentIncome, 0).' per year',
            'threshold' => 'Greater than £0',
            'passed' => $employmentIncome <= 0,
            'explanation' => $employmentIncome > 0
                ? 'You have employment income of £'.number_format($employmentIncome, 0).' per year, so you may have employer benefits to record.'
                : 'No employment income recorded, so employer benefits do not apply.',
        ];

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

        $trace[] = [
            'question' => 'Have you recorded any employer protection benefits?',
            'data_field' => 'Employer benefits recorded',
            'data_value' => $hasAnyBenefit ? 'Yes' : 'None recorded',
            'threshold' => 'At least 1 employer benefit',
            'passed' => $hasAnyBenefit,
            'explanation' => $hasAnyBenefit
                ? 'You have recorded employer protection benefits.'
                : 'No employer benefits have been recorded. Many employers provide death in service, group income protection, or private medical insurance. Recording these ensures your analysis is accurate.',
        ];

        if ($hasAnyBenefit) {
            return null;
        }

        $rec = $this->buildRecommendation($definition, [], 0);
        $rec['decision_trace'] = $trace;

        return $rec;
    }

    /**
     * Evaluate group_ip_any_occupation — triggers when group IP uses 'any occupation' definition.
     */
    private function evaluateGroupIpDefinition(ProtectionActionDefinition $definition, array $comprehensivePlan): ?array
    {
        $trace = [];

        $groupIpDefinition = $this->getProfileValue($comprehensivePlan, 'group_ip_definition');
        $isAnyOccupation = $groupIpDefinition !== null && strtolower((string) $groupIpDefinition) === 'any';

        $trace[] = [
            'question' => 'Does your employer group income protection use an "any occupation" definition?',
            'data_field' => 'Group income protection definition',
            'data_value' => $groupIpDefinition !== null ? ucfirst((string) $groupIpDefinition).' occupation' : 'Not recorded',
            'threshold' => 'Not "any occupation"',
            'passed' => ! $isAnyOccupation,
            'explanation' => $isAnyOccupation
                ? 'Your employer group income protection uses an "any occupation" definition, which means it only pays out if you cannot do any job at all. This is a weaker form of protection than "own occupation".'
                : 'Your employer group income protection does not use an "any occupation" definition, or no group scheme is recorded.',
        ];

        if (! $isAnyOccupation) {
            return null;
        }

        $rec = $this->buildRecommendation($definition, [], 0);
        $rec['decision_trace'] = $trace;

        return $rec;
    }

    // =============================================
    // State Benefits Evaluators
    // =============================================

    /**
     * Evaluate ip_gap_after_state_benefits — triggers when IP gap remains after SSP offset.
     */
    private function evaluateIpGapAfterStateBenefits(ProtectionActionDefinition $definition, array $comprehensivePlan): ?array
    {
        $trace = [];

        $coverageAnalysis = $comprehensivePlan['coverage_analysis'] ?? [];
        $ipData = $coverageAnalysis['income_protection'] ?? [];
        $ipGap = (float) ($ipData['gap'] ?? 0);

        $trace[] = [
            'question' => 'Do you have an income protection gap?',
            'data_field' => 'Income protection gap',
            'data_value' => '£'.number_format($ipGap, 0).' per month',
            'threshold' => '£0 (no gap)',
            'passed' => $ipGap <= 0,
            'explanation' => $ipGap > 0
                ? 'You have an income protection shortfall of £'.number_format($ipGap, 0).' per month.'
                : 'Your income protection cover meets your needs.',
        ];

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

        $trace[] = [
            'question' => 'Does the income protection gap persist even after accounting for Statutory Sick Pay?',
            'data_field' => 'Total Statutory Sick Pay entitlement',
            'data_value' => '£'.number_format($sspTotal, 0),
            'threshold' => 'Statutory Sick Pay is time-limited and may not cover the full gap',
            'passed' => false,
            'explanation' => 'Your total Statutory Sick Pay entitlement is £'.number_format($sspTotal, 0).'. This is a time-limited benefit that would not replace your income long-term. Your monthly gap of £'.number_format($ipGap, 0).' would remain after Statutory Sick Pay ends.',
        ];

        $vars = [
            'ssp_total' => $this->formatCurrency($sspTotal),
        ];

        $rec = $this->buildRecommendation($definition, $vars, $ipGap);
        $rec['decision_trace'] = $trace;

        return $rec;
    }

    /**
     * Evaluate self_employed_no_ip — triggers when self-employed user has no income protection.
     */
    private function evaluateSelfEmployedNoIp(ProtectionActionDefinition $definition, array $comprehensivePlan): ?array
    {
        $trace = [];

        $financialSummary = $comprehensivePlan['financial_summary'] ?? [];
        $employmentIncome = (float) ($financialSummary['income_breakdown']['employment_income'] ?? 0);
        $selfEmploymentIncome = (float) ($financialSummary['income_breakdown']['self_employment_income'] ?? 0);

        // Must be self-employed (has self-employment income, no employment income)
        $isSelfEmployed = $selfEmploymentIncome > 0 && $employmentIncome <= 0;

        $trace[] = [
            'question' => 'Are you self-employed?',
            'data_field' => 'Employment status',
            'data_value' => $isSelfEmployed ? 'Self-employed (£'.number_format($selfEmploymentIncome, 0).' per year)' : 'Not self-employed',
            'threshold' => 'Self-employment income with no employment income',
            'passed' => ! $isSelfEmployed,
            'explanation' => $isSelfEmployed
                ? 'You are self-employed with no employer to provide sick pay or group income protection.'
                : 'You are not solely self-employed, so this check does not apply.',
        ];

        if (! $isSelfEmployed) {
            return null;
        }

        // Check if user has any income protection policies
        $currentCoverage = $comprehensivePlan['current_coverage'] ?? [];
        $ipPolicies = $currentCoverage['income_protection']['policies'] ?? [];
        $ipCount = count($ipPolicies);

        $trace[] = [
            'question' => 'Do you have any personal income protection policies?',
            'data_field' => 'Income protection policies',
            'data_value' => (string) $ipCount.' policies',
            'threshold' => 'At least 1 policy',
            'passed' => $ipCount > 0,
            'explanation' => $ipCount > 0
                ? 'You have '.$ipCount.' income protection policies in place.'
                : 'You have no income protection policies. As a self-employed person, you have no employer sick pay to fall back on if you are unable to work.',
        ];

        if ($ipCount > 0) {
            return null;
        }

        $rec = $this->buildRecommendation($definition, [], 0);
        $rec['decision_trace'] = $trace;

        return $rec;
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
        $trace = [];

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

        $totalLifePolicies = count($lifePolicies);
        $untrustedCount = $untrustedPolicies->count();

        $trace[] = [
            'question' => 'Are any of your life insurance policies not held in trust?',
            'data_field' => 'Policies not in trust',
            'data_value' => (string) $untrustedCount.' of '.(string) $totalLifePolicies.' policies',
            'threshold' => '0 policies outside trust',
            'passed' => $untrustedCount === 0,
            'explanation' => $untrustedCount > 0
                ? $untrustedCount.' of your life insurance policies are not held in trust. Placing policies in trust can help ensure the proceeds are paid quickly to your beneficiaries and may reduce your inheritance tax liability.'
                : 'All your life insurance policies are held in trust.',
        ];

        if ($untrustedPolicies->isEmpty()) {
            return null;
        }

        $results = [];
        foreach ($untrustedPolicies as $policy) {
            $vars = [
                'provider' => $policy->provider ?? 'your insurer',
            ];
            $rec = $this->buildRecommendation($definition, $vars, 0);
            $rec['decision_trace'] = $trace;
            $results[] = $rec;
        }

        return $results;
    }

    /**
     * Evaluate policy_not_joint_married — triggers when married user has non-joint life policy.
     */
    private function evaluatePolicyNotJoint(ProtectionActionDefinition $definition, array $comprehensivePlan): ?array
    {
        $trace = [];

        $userProfile = $comprehensivePlan['user_profile'] ?? [];
        $maritalStatus = strtolower((string) ($userProfile['marital_status'] ?? ''));
        $isMarriedOrCp = $maritalStatus === 'married' || $maritalStatus === 'civil partnership';

        $trace[] = [
            'question' => 'Are you married or in a civil partnership?',
            'data_field' => 'Marital status',
            'data_value' => ucfirst($maritalStatus ?: 'Not recorded'),
            'threshold' => 'Married or civil partnership',
            'passed' => ! $isMarriedOrCp,
            'explanation' => $isMarriedOrCp
                ? 'You are '.$maritalStatus.', so joint life cover may be worth considering.'
                : 'This check only applies to those who are married or in a civil partnership.',
        ];

        if (! $isMarriedOrCp) {
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

        $nonJointCount = $nonJointPolicies->count();

        $trace[] = [
            'question' => 'Do you have any life insurance policies that are not joint life?',
            'data_field' => 'Non-joint life policies',
            'data_value' => (string) $nonJointCount.' policies',
            'threshold' => '0 non-joint policies',
            'passed' => $nonJointCount === 0,
            'explanation' => $nonJointCount > 0
                ? $nonJointCount.' of your life insurance policies are single life rather than joint. As you are '.$maritalStatus.', a joint life policy may offer better value or more appropriate cover.'
                : 'All your life insurance policies are joint life.',
        ];

        if ($nonJointPolicies->isEmpty()) {
            return null;
        }

        $results = [];
        foreach ($nonJointPolicies as $policy) {
            $vars = [
                'provider' => $policy->provider ?? 'your insurer',
            ];
            $rec = $this->buildRecommendation($definition, $vars, 0);
            $rec['decision_trace'] = $trace;
            $results[] = $rec;
        }

        return $results;
    }

    /**
     * Evaluate policy_expiring_soon — triggers when policy end date is within configured months.
     */
    private function evaluatePolicyExpiringSoon(ProtectionActionDefinition $definition, array $comprehensivePlan): ?array
    {
        $trace = [];

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

        $expiringCount = $expiringPolicies->count();

        $trace[] = [
            'question' => 'Do you have any life insurance policies expiring within the next '.$monthsThreshold.' months?',
            'data_field' => 'Policies expiring soon',
            'data_value' => (string) $expiringCount.' policies',
            'threshold' => 'Expiry within '.$monthsThreshold.' months',
            'passed' => $expiringCount === 0,
            'explanation' => $expiringCount > 0
                ? $expiringCount.' of your life insurance policies will expire within the next '.$monthsThreshold.' months. You should review your cover before it lapses.'
                : 'None of your life insurance policies are due to expire within the next '.$monthsThreshold.' months.',
        ];

        if ($expiringPolicies->isEmpty()) {
            return null;
        }

        $results = [];
        foreach ($expiringPolicies as $policy) {
            $vars = [
                'provider' => $policy->provider ?? 'your insurer',
                'end_date' => $policy->policy_end_date->format('j F Y'),
            ];
            $rec = $this->buildRecommendation($definition, $vars, 0);
            $rec['decision_trace'] = $trace;
            $results[] = $rec;
        }

        return $results;
    }

    /**
     * Evaluate policy_expired — triggers when policy end date is in the past.
     */
    private function evaluatePolicyExpired(ProtectionActionDefinition $definition, array $comprehensivePlan): ?array
    {
        $trace = [];

        $userId = $this->extractUserId($comprehensivePlan);
        if ($userId === null) {
            return null;
        }

        $expiredPolicies = LifeInsurancePolicy::where('user_id', $userId)
            ->whereNull('deleted_at')
            ->whereNotNull('policy_end_date')
            ->where('policy_end_date', '<', now())
            ->get();

        $expiredCount = $expiredPolicies->count();

        $trace[] = [
            'question' => 'Do you have any life insurance policies that have already expired?',
            'data_field' => 'Expired policies',
            'data_value' => (string) $expiredCount.' policies',
            'threshold' => '0 expired policies',
            'passed' => $expiredCount === 0,
            'explanation' => $expiredCount > 0
                ? $expiredCount.' of your life insurance policies have expired. You are no longer covered by these policies and should review whether replacement cover is needed.'
                : 'None of your life insurance policies have expired.',
        ];

        if ($expiredPolicies->isEmpty()) {
            return null;
        }

        $results = [];
        foreach ($expiredPolicies as $policy) {
            $vars = [
                'provider' => $policy->provider ?? 'your insurer',
                'end_date' => $policy->policy_end_date->format('j F Y'),
            ];
            $rec = $this->buildRecommendation($definition, $vars, 0);
            $rec['decision_trace'] = $trace;
            $results[] = $rec;
        }

        return $results;
    }

    /**
     * Evaluate mortgage_no_decreasing_term — triggers when user has mortgage but no decreasing term policy.
     */
    private function evaluateMortgageNoDecreasingTerm(ProtectionActionDefinition $definition, array $comprehensivePlan): ?array
    {
        $trace = [];

        $financialSummary = $comprehensivePlan['financial_summary'] ?? [];
        $totalDebt = (float) ($financialSummary['total_debt'] ?? 0);
        $debtBreakdown = $financialSummary['debt_breakdown'] ?? [];
        $mortgageDebt = (float) ($debtBreakdown['mortgage'] ?? 0);

        $trace[] = [
            'question' => 'Do you have a mortgage?',
            'data_field' => 'Outstanding mortgage balance',
            'data_value' => '£'.number_format($mortgageDebt, 0),
            'threshold' => 'Greater than £0',
            'passed' => $mortgageDebt <= 0,
            'explanation' => $mortgageDebt > 0
                ? 'You have an outstanding mortgage balance of £'.number_format($mortgageDebt, 0).'.'
                : 'No mortgage debt recorded.',
        ];

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

        $trace[] = [
            'question' => 'Do you have a decreasing term or mortgage protection policy?',
            'data_field' => 'Mortgage protection cover',
            'data_value' => $hasMortgageProtection ? 'Yes' : 'No',
            'threshold' => 'At least 1 decreasing term or mortgage protection policy',
            'passed' => $hasMortgageProtection,
            'explanation' => $hasMortgageProtection
                ? 'You have a decreasing term or mortgage protection policy in place to cover your mortgage.'
                : 'You have no decreasing term or mortgage protection policy. A decreasing term policy is designed to cover your mortgage balance and typically costs less than level term cover.',
        ];

        if ($hasMortgageProtection) {
            return null;
        }

        $vars = [
            'mortgage_amount' => $this->formatCurrency($mortgageDebt),
        ];

        $rec = $this->buildRecommendation($definition, $vars, $mortgageDebt);
        $rec['decision_trace'] = $trace;

        return $rec;
    }

    // =============================================
    // Income Protection Policy-Level Evaluators
    // =============================================

    /**
     * Evaluate ip_any_occupation_definition — triggers when personal IP uses 'any occupation'.
     */
    private function evaluateIpAnyOccupation(ProtectionActionDefinition $definition, array $comprehensivePlan): ?array
    {
        $trace = [];

        $userId = $this->extractUserId($comprehensivePlan);
        if ($userId === null) {
            return null;
        }

        $anyOccupationPolicies = IncomeProtectionPolicy::where('user_id', $userId)
            ->whereNull('deleted_at')
            ->where('occupation_class', 'any')
            ->get();

        $anyOccCount = $anyOccupationPolicies->count();

        $trace[] = [
            'question' => 'Do any of your income protection policies use an "any occupation" definition?',
            'data_field' => 'Policies with "any occupation" definition',
            'data_value' => (string) $anyOccCount.' policies',
            'threshold' => '0 policies with "any occupation"',
            'passed' => $anyOccCount === 0,
            'explanation' => $anyOccCount > 0
                ? $anyOccCount.' of your income protection policies use an "any occupation" definition. This means you would only receive a payout if you are unable to perform any job at all, not just your current occupation.'
                : 'None of your income protection policies use the weaker "any occupation" definition.',
        ];

        if ($anyOccupationPolicies->isEmpty()) {
            return null;
        }

        $results = [];
        foreach ($anyOccupationPolicies as $policy) {
            $vars = [
                'provider' => $policy->provider ?? 'your insurer',
            ];
            $rec = $this->buildRecommendation($definition, $vars, 0);
            $rec['decision_trace'] = $trace;
            $results[] = $rec;
        }

        return $results;
    }

    /**
     * Evaluate ip_short_benefit_period — triggers when benefit period is below threshold.
     */
    private function evaluateIpShortBenefitPeriod(ProtectionActionDefinition $definition, array $comprehensivePlan): ?array
    {
        $trace = [];

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

        $shortCount = $shortPolicies->count();

        $trace[] = [
            'question' => 'Do any of your income protection policies have a short benefit period?',
            'data_field' => 'Policies with benefit period under '.$monthsThreshold.' months',
            'data_value' => (string) $shortCount.' policies',
            'threshold' => 'Benefit period of at least '.$monthsThreshold.' months',
            'passed' => $shortCount === 0,
            'explanation' => $shortCount > 0
                ? $shortCount.' of your income protection policies have a benefit period shorter than '.$monthsThreshold.' months. A longer benefit period provides more sustained protection during extended illness or injury.'
                : 'All your income protection policies have a benefit period of at least '.$monthsThreshold.' months.',
        ];

        if ($shortPolicies->isEmpty()) {
            return null;
        }

        $results = [];
        foreach ($shortPolicies as $policy) {
            $vars = [
                'benefit_months' => (string) $policy->benefit_period_months,
                'provider' => $policy->provider ?? 'your insurer',
            ];
            $rec = $this->buildRecommendation($definition, $vars, 0);
            $rec['decision_trace'] = $trace;
            $results[] = $rec;
        }

        return $results;
    }

    /**
     * Evaluate ip_long_deferred_period — triggers when deferred period exceeds threshold
     * and user has no employer sick pay to bridge the gap.
     */
    private function evaluateIpLongDeferredPeriod(ProtectionActionDefinition $definition, array $comprehensivePlan): ?array
    {
        $trace = [];

        $userId = $this->extractUserId($comprehensivePlan);
        if ($userId === null) {
            return null;
        }

        $weeksThreshold = (int) ($definition->trigger_config['weeks_threshold'] ?? 26);

        // Check if employer provides sick pay (group IP covers the gap)
        $groupIpPercent = $this->getProfileValue($comprehensivePlan, 'group_ip_benefit_percent');
        $hasEmployerSickPay = $groupIpPercent !== null && $groupIpPercent > 0;

        $trace[] = [
            'question' => 'Does your employer provide group income protection or sick pay to bridge the deferred period?',
            'data_field' => 'Employer group income protection',
            'data_value' => $hasEmployerSickPay ? round((float) $groupIpPercent, 1).'% of salary' : 'None',
            'threshold' => 'Any employer sick pay provision',
            'passed' => $hasEmployerSickPay,
            'explanation' => $hasEmployerSickPay
                ? 'Your employer provides group income protection at '.round((float) $groupIpPercent, 1).'% of salary, which can bridge the deferred period.'
                : 'You have no employer sick pay or group income protection to cover you during the deferred period.',
        ];

        if ($hasEmployerSickPay) {
            return null;
        }

        $longDeferredPolicies = IncomeProtectionPolicy::where('user_id', $userId)
            ->whereNull('deleted_at')
            ->whereNotNull('deferred_period_weeks')
            ->where('deferred_period_weeks', '>', $weeksThreshold)
            ->get();

        $longDeferredCount = $longDeferredPolicies->count();

        $trace[] = [
            'question' => 'Do any of your income protection policies have a long deferred period?',
            'data_field' => 'Policies with deferred period over '.$weeksThreshold.' weeks',
            'data_value' => (string) $longDeferredCount.' policies',
            'threshold' => 'Deferred period of '.$weeksThreshold.' weeks or less',
            'passed' => $longDeferredCount === 0,
            'explanation' => $longDeferredCount > 0
                ? $longDeferredCount.' of your income protection policies have a deferred period longer than '.$weeksThreshold.' weeks. Without employer sick pay, you would have no income during this waiting period.'
                : 'None of your income protection policies have an excessively long deferred period.',
        ];

        if ($longDeferredPolicies->isEmpty()) {
            return null;
        }

        $results = [];
        foreach ($longDeferredPolicies as $policy) {
            $vars = [
                'deferred_weeks' => (string) $policy->deferred_period_weeks,
                'provider' => $policy->provider ?? 'your insurer',
            ];
            $rec = $this->buildRecommendation($definition, $vars, 0);
            $rec['decision_trace'] = $trace;
            $results[] = $rec;
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
        $trace = [];

        $financialSummary = $comprehensivePlan['financial_summary'] ?? [];
        $debtBreakdown = $financialSummary['debt_breakdown'] ?? [];
        $mortgageDebt = (float) ($debtBreakdown['mortgage'] ?? 0);

        $trace[] = [
            'question' => 'Do you have a mortgage?',
            'data_field' => 'Outstanding mortgage balance',
            'data_value' => '£'.number_format($mortgageDebt, 0),
            'threshold' => 'Greater than £0',
            'passed' => $mortgageDebt <= 0,
            'explanation' => $mortgageDebt > 0
                ? 'You have an outstanding mortgage balance of £'.number_format($mortgageDebt, 0).'.'
                : 'No mortgage debt recorded.',
        ];

        if ($mortgageDebt <= 0) {
            return null;
        }

        $coverageAnalysis = $comprehensivePlan['coverage_analysis'] ?? [];
        $ciCoverage = (float) ($coverageAnalysis['critical_illness']['coverage'] ?? 0);

        $trace[] = [
            'question' => 'Do you have any critical illness cover?',
            'data_field' => 'Critical illness coverage',
            'data_value' => '£'.number_format($ciCoverage, 0),
            'threshold' => 'Greater than £0',
            'passed' => $ciCoverage > 0,
            'explanation' => $ciCoverage > 0
                ? 'You have £'.number_format($ciCoverage, 0).' of critical illness cover.'
                : 'You have no critical illness cover. A serious diagnosis could leave you unable to meet your mortgage repayments of £'.number_format($mortgageDebt, 0).'.',
        ];

        if ($ciCoverage > 0) {
            return null;
        }

        $vars = [
            'mortgage_amount' => $this->formatCurrency($mortgageDebt),
        ];

        $rec = $this->buildRecommendation($definition, $vars, $mortgageDebt);
        $rec['decision_trace'] = $trace;

        return $rec;
    }

    /**
     * Evaluate ci_combined_risk — triggers when CI policy type is 'combined' (life + CI).
     */
    private function evaluateCiCombinedRisk(ProtectionActionDefinition $definition, array $comprehensivePlan): ?array
    {
        $trace = [];

        $userId = $this->extractUserId($comprehensivePlan);
        if ($userId === null) {
            return null;
        }

        $combinedPolicies = CriticalIllnessPolicy::where('user_id', $userId)
            ->whereNull('deleted_at')
            ->where('policy_type', 'combined')
            ->get();

        $combinedCount = $combinedPolicies->count();

        $trace[] = [
            'question' => 'Do you have any combined life and critical illness policies?',
            'data_field' => 'Combined life and critical illness policies',
            'data_value' => (string) $combinedCount.' policies',
            'threshold' => '0 combined policies',
            'passed' => $combinedCount === 0,
            'explanation' => $combinedCount > 0
                ? $combinedCount.' of your critical illness policies are combined with life cover. With a combined policy, if you claim for a critical illness, your life cover is also reduced or lost. Standalone policies provide independent protection for each risk.'
                : 'You do not have any combined life and critical illness policies.',
        ];

        if ($combinedPolicies->isEmpty()) {
            return null;
        }

        $results = [];
        foreach ($combinedPolicies as $policy) {
            $vars = [
                'provider' => $policy->provider ?? 'your insurer',
            ];
            $rec = $this->buildRecommendation($definition, $vars, 0);
            $rec['decision_trace'] = $trace;
            $results[] = $rec;
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
        $trace = [];

        $userProfile = $comprehensivePlan['user_profile'] ?? [];
        $dependants = (int) ($userProfile['number_of_dependents'] ?? 0);

        $trace[] = [
            'question' => 'Do you have any dependants?',
            'data_field' => 'Number of dependants',
            'data_value' => (string) $dependants,
            'threshold' => 'At least 1 dependant',
            'passed' => $dependants <= 0,
            'explanation' => $dependants > 0
                ? 'You have '.$dependants.' dependant(s) who rely on your income.'
                : 'No dependants recorded.',
        ];

        if ($dependants <= 0) {
            return null;
        }

        $currentCoverage = $comprehensivePlan['current_coverage'] ?? [];
        $lifeCoverage = (float) ($currentCoverage['life_insurance']['total_coverage'] ?? 0);

        $trace[] = [
            'question' => 'Do you have any life insurance cover?',
            'data_field' => 'Total life insurance coverage',
            'data_value' => '£'.number_format($lifeCoverage, 0),
            'threshold' => 'Greater than £0',
            'passed' => $lifeCoverage > 0,
            'explanation' => $lifeCoverage > 0
                ? 'You have £'.number_format($lifeCoverage, 0).' of life insurance cover.'
                : 'You have no life insurance cover. With '.$dependants.' dependant(s), life insurance is essential to protect their financial security if something were to happen to you.',
        ];

        if ($lifeCoverage > 0) {
            return null;
        }

        $vars = [
            'dependant_count' => (string) $dependants,
        ];

        $rec = $this->buildRecommendation($definition, $vars, 0);
        $rec['decision_trace'] = $trace;

        return $rec;
    }

    /**
     * Evaluate education_funding_gap — triggers when education funding gap exists.
     */
    private function evaluateEducationFundingGap(ProtectionActionDefinition $definition, array $comprehensivePlan): ?array
    {
        $trace = [];

        $protectionNeeds = $comprehensivePlan['protection_needs'] ?? [];
        $breakdown = $protectionNeeds['breakdown'] ?? [];
        $educationFunding = (float) ($breakdown['education_funding'] ?? 0);

        $trace[] = [
            'question' => 'Do you have an education funding need for your dependants?',
            'data_field' => 'Education funding requirement',
            'data_value' => '£'.number_format($educationFunding, 0),
            'threshold' => 'Greater than £0',
            'passed' => $educationFunding <= 0,
            'explanation' => $educationFunding > 0
                ? 'You have an education funding requirement of £'.number_format($educationFunding, 0).' for your dependants.'
                : 'No education funding need identified.',
        ];

        if ($educationFunding <= 0) {
            return null;
        }

        // Check if the education portion is covered by existing life cover
        // If there's an overall gap, education funding is at risk
        $coverageAnalysis = $comprehensivePlan['coverage_analysis'] ?? [];
        $lifeGap = (float) ($coverageAnalysis['life_insurance']['gap'] ?? 0);

        $trace[] = [
            'question' => 'Is your life insurance sufficient to cover your education funding need?',
            'data_field' => 'Life insurance gap',
            'data_value' => '£'.number_format($lifeGap, 0),
            'threshold' => '£0 (no gap)',
            'passed' => $lifeGap <= 0,
            'explanation' => $lifeGap > 0
                ? 'Your life insurance has a shortfall of £'.number_format($lifeGap, 0).', which puts your education funding at risk.'
                : 'Your life insurance fully covers your needs, including education funding.',
        ];

        if ($lifeGap <= 0) {
            return null;
        }

        // Education gap is the lesser of the education funding need and the total life gap
        $educationGap = min($educationFunding, $lifeGap);

        $trace[] = [
            'question' => 'How much of the education funding is at risk?',
            'data_field' => 'Education funding gap',
            'data_value' => '£'.number_format($educationGap, 0),
            'threshold' => '£0 (fully covered)',
            'passed' => false,
            'explanation' => '£'.number_format($educationGap, 0).' of the education funding need is at risk due to insufficient life cover.',
        ];

        $vars = [
            'gap_amount' => $this->formatCurrency($educationGap),
        ];

        $rec = $this->buildRecommendation($definition, $vars, $educationGap);
        $rec['decision_trace'] = $trace;

        return $rec;
    }

    /**
     * Evaluate non_earning_spouse_no_cover — triggers when non-earning spouse has no cover
     * and user has dependants.
     */
    private function evaluateNonEarningSpouse(ProtectionActionDefinition $definition, array $comprehensivePlan): ?array
    {
        $trace = [];

        $userProfile = $comprehensivePlan['user_profile'] ?? [];
        $maritalStatus = strtolower((string) ($userProfile['marital_status'] ?? ''));
        $dependants = (int) ($userProfile['number_of_dependents'] ?? 0);

        $isMarriedOrCp = in_array($maritalStatus, ['married', 'civil partnership']);

        $trace[] = [
            'question' => 'Are you married or in a civil partnership?',
            'data_field' => 'Marital status',
            'data_value' => ucfirst($maritalStatus ?: 'Not recorded'),
            'threshold' => 'Married or civil partnership',
            'passed' => ! $isMarriedOrCp,
            'explanation' => $isMarriedOrCp
                ? 'You are '.$maritalStatus.'.'
                : 'This check only applies to those who are married or in a civil partnership.',
        ];

        // Must be married/civil partnership with dependants
        if (! $isMarriedOrCp) {
            return null;
        }

        $trace[] = [
            'question' => 'Do you have dependants?',
            'data_field' => 'Number of dependants',
            'data_value' => (string) $dependants,
            'threshold' => 'At least 1 dependant',
            'passed' => $dependants <= 0,
            'explanation' => $dependants > 0
                ? 'You have '.$dependants.' dependant(s).'
                : 'No dependants recorded. This check requires dependants.',
        ];

        if ($dependants <= 0) {
            return null;
        }

        // Check if spouse has earned income
        $protectionNeeds = $comprehensivePlan['protection_needs'] ?? [];
        $spouseInfo = $protectionNeeds['spouse_info'] ?? [];
        $spouseGrossIncome = (float) ($spouseInfo['spouse_gross_income'] ?? 0);

        // Check if spouse is included in analysis (permission granted)
        $spouseIncluded = (bool) ($spouseInfo['spouse_included'] ?? false);

        if (! $spouseIncluded) {
            return null;
        }

        $trace[] = [
            'question' => 'Does your spouse or partner have their own earned income?',
            'data_field' => 'Spouse gross income',
            'data_value' => '£'.number_format($spouseGrossIncome, 0).' per year',
            'threshold' => 'Greater than £0',
            'passed' => $spouseGrossIncome > 0,
            'explanation' => $spouseGrossIncome > 0
                ? 'Your spouse earns £'.number_format($spouseGrossIncome, 0).' per year.'
                : 'Your spouse has no earned income. If they were unable to fulfil their role due to illness or death, the cost of replacing their contribution (childcare, household management) could be significant.',
        ];

        // If spouse has earned income, this trigger does not apply
        if ($spouseGrossIncome > 0) {
            return null;
        }

        $rec = $this->buildRecommendation($definition, [], 0);
        $rec['decision_trace'] = $trace;

        return $rec;
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
