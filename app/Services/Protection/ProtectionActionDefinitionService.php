<?php

declare(strict_types=1);

namespace App\Services\Protection;

use App\Models\CriticalIllnessPolicy;
use App\Models\IncomeProtectionPolicy;
use App\Models\LifeInsurancePolicy;
use App\Models\ProtectionActionDefinition;
use App\Models\ProtectionProfile;
use App\Models\User;
use App\Services\TaxConfigService;
use App\Traits\FormatsCurrency;
use Carbon\Carbon;

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

            // Premium affordability
            'premium_percent_of_income_above' => $this->evaluatePremiumAffordability($definition, $comprehensivePlan),

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
        $user = $this->resolveUser($comprehensivePlan);
        $firstName = $user->first_name ?? 'The user';

        // Step 1: User context
        $trace[] = $this->buildUserContextTrace($comprehensivePlan);

        $triggerConfig = $definition->trigger_config;
        $coverageType = $triggerConfig['coverage_type'] ?? '';
        $threshold = (float) ($triggerConfig['threshold'] ?? 0);

        $coverageAnalysis = $comprehensivePlan['coverage_analysis'] ?? [];
        $typeData = $coverageAnalysis[$coverageType] ?? [];
        $gap = (float) ($typeData['gap'] ?? 0);
        $coverage = (float) ($typeData['coverage'] ?? 0);
        $need = (float) ($typeData['need'] ?? 0);

        $coverageLabel = str_replace('_', ' ', $coverageType);

        // Step 2: Existing cover with specific policy details
        $userId = $this->extractUserId($comprehensivePlan);
        $policyDetails = 'No policies found';
        if ($userId) {
            if ($coverageType === 'life_insurance') {
                $policies = LifeInsurancePolicy::where('user_id', $userId)->whereNull('deleted_at')->get();
                $policyDetails = $policies->isEmpty() ? 'None' : $this->formatLifePolicySummary($policies);
            } elseif ($coverageType === 'critical_illness') {
                $policies = CriticalIllnessPolicy::where('user_id', $userId)->whereNull('deleted_at')->get();
                $policyDetails = $policies->isEmpty() ? 'None' : $this->formatCiPolicySummary($policies);
            } elseif ($coverageType === 'income_protection') {
                $policies = IncomeProtectionPolicy::where('user_id', $userId)->whereNull('deleted_at')->get();
                $policyDetails = $policies->isEmpty() ? 'None' : $this->formatIpPolicySummary($policies);
            }
        }

        $trace[] = [
            'question' => 'What '.$coverageLabel.' cover does '.$firstName.' currently have?',
            'data_field' => ucfirst($coverageLabel).' policies',
            'data_value' => 'Current cover: £'.number_format($coverage, 0).'. Policies: '.$policyDetails,
            'threshold' => '£'.number_format($need, 0).' (calculated need)',
            'passed' => $coverage >= $need,
            'explanation' => $firstName.' has £'.number_format($coverage, 0).' of '.$coverageLabel.' cover against a calculated need of £'.number_format($need, 0).'.',
        ];

        // Step 3: Gap assessment
        $trace[] = [
            'question' => 'Is the '.$coverageLabel.' gap above the trigger threshold?',
            'data_field' => ucfirst($coverageLabel).' gap',
            'data_value' => '£'.number_format($need, 0).' need - £'.number_format($coverage, 0).' cover = £'.number_format($gap, 0).' gap',
            'threshold' => '£'.number_format($threshold, 0).' (minimum gap to trigger)',
            'passed' => $gap <= $threshold,
            'explanation' => $gap <= $threshold
                ? $firstName.'\'s '.$coverageLabel.' gap of £'.number_format($gap, 0).' is within acceptable limits (threshold: £'.number_format($threshold, 0).').'
                : $firstName.'\'s '.$coverageLabel.' has a shortfall of £'.number_format($gap, 0).' which exceeds the £'.number_format($threshold, 0).' threshold.',
        ];

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
        $user = $this->resolveUser($comprehensivePlan);
        $firstName = $user->first_name ?? 'The user';

        // Step 1: User context
        $trace[] = $this->buildUserContextTrace($comprehensivePlan);

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

        // Step 2: Strategy match check
        $matchSummary = collect($matched)->map(fn ($m) => ($m['action'] ?? 'Unknown action').' (priority: '.($m['priority'] ?? 'N/A').')')->implode('; ');

        $trace[] = [
            'question' => 'Does the optimised strategy include a recommendation for "'.$categoryMatch.'" for '.$firstName.'?',
            'data_field' => 'Matching strategy recommendations',
            'data_value' => count($matched).' found'.($matchSummary ? ': '.$matchSummary : ''),
            'threshold' => 'At least 1 matching recommendation',
            'passed' => empty($matched),
            'explanation' => empty($matched)
                ? 'No strategy recommendations match the "'.$categoryMatch.'" category for '.$firstName.'.'
                : count($matched).' strategy recommendation(s) found for "'.$categoryMatch.'": '.$matchSummary.'.',
        ];

        if (empty($matched)) {
            return null;
        }

        // Use the first matching recommendation's data
        $rec = $matched[0];
        $coverageAmount = $rec['coverage_amount'] ?? $rec['monthly_benefit'] ?? 0;
        $monthlyCost = $rec['estimated_monthly_cost'] ?? 0;

        // Step 3: Recommended coverage details
        $trace[] = [
            'question' => 'What coverage amount and cost does the strategy recommend for '.$firstName.'?',
            'data_field' => 'Recommended coverage',
            'data_value' => '£'.number_format((float) $coverageAmount, 0).' cover, £'.number_format((float) $monthlyCost, 2).' per month estimated cost',
            'threshold' => 'N/A — strategy-driven recommendation',
            'passed' => false,
            'explanation' => 'The strategy recommends '.$firstName.' obtains £'.number_format((float) $coverageAmount, 0).' of coverage at an estimated cost of £'.number_format((float) $monthlyCost, 2).' per month. Action: '.($rec['action'] ?? 'Review coverage').'. Details: '.($rec['details'] ?? 'None specified').'.',
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
        $user = $this->resolveUser($comprehensivePlan);
        $firstName = $user->first_name ?? 'The user';
        $userId = $this->extractUserId($comprehensivePlan);

        // Step 1: User context
        $trace[] = $this->buildUserContextTrace($comprehensivePlan);

        $currentCoverage = $comprehensivePlan['current_coverage'] ?? [];
        $policyCount = $this->countPolicies($currentCoverage);

        // Step 2: Existing policies with details
        $allPolicyDetails = '';
        if ($userId) {
            $lifePolicies = LifeInsurancePolicy::where('user_id', $userId)->whereNull('deleted_at')->get();
            $ciPolicies = CriticalIllnessPolicy::where('user_id', $userId)->whereNull('deleted_at')->get();
            $ipPolicies = IncomeProtectionPolicy::where('user_id', $userId)->whereNull('deleted_at')->get();

            $detailParts = [];
            if ($lifePolicies->isNotEmpty()) {
                $detailParts[] = 'Life: '.$this->formatLifePolicySummary($lifePolicies);
            }
            if ($ciPolicies->isNotEmpty()) {
                $detailParts[] = 'Critical illness: '.$this->formatCiPolicySummary($ciPolicies);
            }
            if ($ipPolicies->isNotEmpty()) {
                $detailParts[] = 'Income protection: '.$this->formatIpPolicySummary($ipPolicies);
            }
            $allPolicyDetails = implode('. ', $detailParts) ?: 'None';
        }

        $trace[] = [
            'question' => 'What existing protection policies does '.$firstName.' have?',
            'data_field' => 'Total policy count',
            'data_value' => $policyCount.' policies. '.$allPolicyDetails,
            'threshold' => 'At least 1 policy',
            'passed' => $policyCount > 0,
            'explanation' => $policyCount > 0
                ? $firstName.' has '.$policyCount.' existing protection policies.'
                : $firstName.' has no existing protection policies.',
        ];

        if ($policyCount === 0) {
            return null;
        }

        // Step 3: Gap analysis with specific figures
        $coverageAnalysis = $comprehensivePlan['coverage_analysis'] ?? [];
        $lifeGap = (float) ($coverageAnalysis['life_insurance']['gap'] ?? 0);
        $ciGap = (float) ($coverageAnalysis['critical_illness']['gap'] ?? 0);
        $ipGap = (float) ($coverageAnalysis['income_protection']['gap'] ?? 0);
        $hasGap = $lifeGap > 0 || $ciGap > 0 || $ipGap > 0;

        $gapBreakdown = [];
        if ($lifeGap > 0) {
            $gapBreakdown[] = 'life insurance £'.number_format($lifeGap, 0);
        }
        if ($ciGap > 0) {
            $gapBreakdown[] = 'critical illness £'.number_format($ciGap, 0);
        }
        if ($ipGap > 0) {
            $gapBreakdown[] = 'income protection £'.number_format($ipGap, 0).'/month';
        }

        $trace[] = [
            'question' => 'Are there any remaining coverage gaps despite '.$firstName.'\'s existing policies?',
            'data_field' => 'Coverage gaps remaining',
            'data_value' => $hasGap ? 'Yes — '.implode(', ', $gapBreakdown) : 'No gaps',
            'threshold' => 'No gaps',
            'passed' => ! $hasGap,
            'explanation' => $hasGap
                ? 'Despite having '.$policyCount.' policies, '.$firstName.' has coverage gaps: '.implode(', ', $gapBreakdown).'.'
                : $firstName.'\'s existing policies fully cover their protection needs.',
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
        $user = $this->resolveUser($comprehensivePlan);
        $firstName = $user->first_name ?? 'The user';
        $userId = $this->extractUserId($comprehensivePlan);

        // Step 1: User context
        $trace[] = $this->buildUserContextTrace($comprehensivePlan);

        $threshold = (int) ($definition->trigger_config['threshold'] ?? 3);
        $currentCoverage = $comprehensivePlan['current_coverage'] ?? [];
        $policyCount = $this->countPolicies($currentCoverage);

        // Step 2: Detailed policy list
        $allPolicyDetails = '';
        if ($userId) {
            $lifePolicies = LifeInsurancePolicy::where('user_id', $userId)->whereNull('deleted_at')->get();
            $ciPolicies = CriticalIllnessPolicy::where('user_id', $userId)->whereNull('deleted_at')->get();
            $ipPolicies = IncomeProtectionPolicy::where('user_id', $userId)->whereNull('deleted_at')->get();

            $detailParts = [];
            if ($lifePolicies->isNotEmpty()) {
                $detailParts[] = $lifePolicies->count().' life: '.$this->formatLifePolicySummary($lifePolicies);
            }
            if ($ciPolicies->isNotEmpty()) {
                $detailParts[] = $ciPolicies->count().' critical illness: '.$this->formatCiPolicySummary($ciPolicies);
            }
            if ($ipPolicies->isNotEmpty()) {
                $detailParts[] = $ipPolicies->count().' income protection: '.$this->formatIpPolicySummary($ipPolicies);
            }
            $allPolicyDetails = implode('. ', $detailParts);
        }

        $trace[] = [
            'question' => 'Does '.$firstName.' have a large number of separate protection policies?',
            'data_field' => 'Total policy count',
            'data_value' => $policyCount.' policies. '.$allPolicyDetails,
            'threshold' => $threshold.' or more policies triggers consolidation review',
            'passed' => $policyCount < $threshold,
            'explanation' => $policyCount < $threshold
                ? $firstName.' has '.$policyCount.' policies, which is manageable.'
                : $firstName.' has '.$policyCount.' policies across multiple providers. Consolidating could simplify cover and potentially reduce costs.',
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
        $user = $this->resolveUser($comprehensivePlan);
        $firstName = $user->first_name ?? 'The user';
        $userId = $this->extractUserId($comprehensivePlan);

        $userProfile = $comprehensivePlan['user_profile'] ?? [];
        $hasProfile = ! empty($userProfile) && isset($userProfile['age']);

        // Step 1: Check what data exists for the user
        $missingFields = [];
        if ($userId) {
            $profile = ProtectionProfile::where('user_id', $userId)->first();
            if (! $profile) {
                $missingFields[] = 'No protection profile record exists';
            } else {
                if (! $profile->annual_income) {
                    $missingFields[] = 'annual income';
                }
                if (! $profile->monthly_expenditure) {
                    $missingFields[] = 'monthly expenditure';
                }
                if ($profile->number_of_dependents === null) {
                    $missingFields[] = 'number of dependants';
                }
                if (! $profile->retirement_age) {
                    $missingFields[] = 'retirement age';
                }
            }
        }

        $profileStatus = $hasProfile ? 'Complete' : 'Incomplete';
        $missingDetail = ! empty($missingFields) ? ' Missing: '.implode(', ', $missingFields).'.' : '';

        $trace[] = [
            'question' => 'Has '.$firstName.' completed their protection profile?',
            'data_field' => 'Protection profile',
            'data_value' => $profileStatus.'.'.$missingDetail,
            'threshold' => 'Profile must exist with age, income, and dependants data',
            'passed' => $hasProfile,
            'explanation' => $hasProfile
                ? $firstName.'\'s protection profile is set up with the information needed for analysis.'
                : $firstName.'\'s protection profile is incomplete.'.$missingDetail.' Personal details are needed to calculate cover requirements.',
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
        $user = $this->resolveUser($comprehensivePlan);
        $firstName = $user->first_name ?? 'The user';

        // Step 1: User context
        $trace[] = $this->buildUserContextTrace($comprehensivePlan);

        $currentCoverage = $comprehensivePlan['current_coverage'] ?? [];
        $policyCount = $this->countPolicies($currentCoverage);

        // Step 2: Policy check
        $trace[] = [
            'question' => 'Does '.$firstName.' have any existing protection policies?',
            'data_field' => 'Total policy count',
            'data_value' => $policyCount.' policies (life insurance, critical illness, and income protection)',
            'threshold' => '0 policies (no cover at all)',
            'passed' => $policyCount > 0,
            'explanation' => $policyCount > 0
                ? $firstName.' has '.$policyCount.' existing protection policies.'
                : $firstName.' has no protection policies in place — no life insurance, no critical illness cover, and no income protection.',
        ];

        if ($policyCount > 0) {
            return null;
        }

        // Step 3: Gap analysis with specific needs
        $coverageAnalysis = $comprehensivePlan['coverage_analysis'] ?? [];
        $lifeGap = (float) ($coverageAnalysis['life_insurance']['gap'] ?? 0);
        $lifeNeed = (float) ($coverageAnalysis['life_insurance']['need'] ?? 0);
        $ciGap = (float) ($coverageAnalysis['critical_illness']['gap'] ?? 0);
        $ciNeed = (float) ($coverageAnalysis['critical_illness']['need'] ?? 0);
        $ipGap = (float) ($coverageAnalysis['income_protection']['gap'] ?? 0);
        $ipNeed = (float) ($coverageAnalysis['income_protection']['need'] ?? 0);
        $hasGap = $lifeGap > 0 || $ciGap > 0 || $ipGap > 0;

        $needBreakdown = [];
        if ($lifeNeed > 0) {
            $needBreakdown[] = 'life insurance need £'.number_format($lifeNeed, 0);
        }
        if ($ciNeed > 0) {
            $needBreakdown[] = 'critical illness need £'.number_format($ciNeed, 0);
        }
        if ($ipNeed > 0) {
            $needBreakdown[] = 'income protection need £'.number_format($ipNeed, 0).'/month';
        }

        $trace[] = [
            'question' => 'Does '.$firstName.' have any protection needs that are not being met?',
            'data_field' => 'Calculated protection needs',
            'data_value' => $hasGap ? 'Yes — '.implode(', ', $needBreakdown) : 'No gaps identified',
            'threshold' => 'No gaps',
            'passed' => ! $hasGap,
            'explanation' => $hasGap
                ? $firstName.' has unmet protection needs with no policies in place: '.implode(', ', $needBreakdown).'.'
                : 'No coverage gaps identified based on '.$firstName.'\'s circumstances.',
        ];

        if (! $hasGap) {
            return null;
        }

        // Step 4: Total shortfall breakdown
        $totalGap = $lifeGap + $ciGap + ($ipGap * 12);

        $trace[] = [
            'question' => 'What is '.$firstName.'\'s total protection shortfall across all cover types?',
            'data_field' => 'Total protection gap',
            'data_value' => '£'.number_format($totalGap, 0).' (life: £'.number_format($lifeGap, 0).' + critical illness: £'.number_format($ciGap, 0).' + income protection: £'.number_format($ipGap, 0).'/month × 12 = £'.number_format($ipGap * 12, 0).')',
            'threshold' => '£0 (fully covered)',
            'passed' => false,
            'explanation' => $firstName.'\'s total protection shortfall is £'.number_format($totalGap, 0).', comprising life insurance (£'.number_format($lifeGap, 0).'), critical illness (£'.number_format($ciGap, 0).'), and income protection (£'.number_format($ipGap * 12, 0).' annualised).',
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
        $user = $this->resolveUser($comprehensivePlan);
        $firstName = $user->first_name ?? 'The user';

        // Step 1: User context
        $trace[] = $this->buildUserContextTrace($comprehensivePlan);

        $financialSummary = $comprehensivePlan['financial_summary'] ?? [];
        $currentCoverage = $comprehensivePlan['current_coverage'] ?? [];
        $lifeCoverage = (float) ($currentCoverage['life_insurance']['total_coverage'] ?? 0);

        $disMultiple = $this->getProfileValue($comprehensivePlan, 'death_in_service_multiple');
        $employerName = $this->getProfileValue($comprehensivePlan, 'employer_name');
        $employerLabel = $employerName ? ' through '.$employerName : ' through their employer';

        // Step 2: Death in service benefit check
        $trace[] = [
            'question' => 'Does '.$firstName.' have a death in service benefit'.$employerLabel.'?',
            'data_field' => 'Death in service multiple',
            'data_value' => $disMultiple !== null && $disMultiple > 0 ? $disMultiple.'× salary'.$employerLabel : 'None recorded',
            'threshold' => 'Must have death in service benefit and personal life cover to check reliance',
            'passed' => ! ($disMultiple !== null && $disMultiple > 0 && $lifeCoverage > 0),
            'explanation' => $disMultiple !== null && $disMultiple > 0
                ? $firstName.' has a death in service benefit of '.$disMultiple.'× salary'.$employerLabel.'. Total personal life cover: £'.number_format($lifeCoverage, 0).'.'
                : 'No death in service benefit recorded for '.$firstName.'.',
        ];

        if ($disMultiple === null || $disMultiple <= 0 || $lifeCoverage <= 0) {
            return null;
        }

        // Step 3: Calculate death in service amount
        $salary = (float) ($financialSummary['income_breakdown']['employment_income'] ?? 0);
        if ($salary <= 0) {
            return null;
        }

        $deathInService = $disMultiple * $salary;
        $disRelianceThreshold = (float) $this->taxConfig->get('protection.dis_reliance_percent', 0.50);

        $trace[] = [
            'question' => 'What is the death in service benefit worth?',
            'data_field' => 'Death in service calculation',
            'data_value' => '£'.number_format($salary, 0).' salary × '.$disMultiple.' = £'.number_format($deathInService, 0),
            'threshold' => 'Calculation input for reliance check',
            'passed' => true,
            'explanation' => $firstName.'\'s employment income is £'.number_format($salary, 0).' per year. At '.$disMultiple.'× salary, the death in service benefit is worth £'.number_format($deathInService, 0).'.',
        ];

        // Step 4: Reliance ratio
        $totalLifeIncludingDis = $lifeCoverage;
        $disRatio = $totalLifeIncludingDis > 0 ? ($deathInService / $totalLifeIncludingDis) : 0;
        $overReliant = $totalLifeIncludingDis > 0 && $disRatio > $disRelianceThreshold;

        $trace[] = [
            'question' => 'Is '.$firstName.' over-reliant on the employer death in service benefit?',
            'data_field' => 'Death in service as proportion of total life cover',
            'data_value' => '£'.number_format($deathInService, 0).' ÷ £'.number_format($totalLifeIncludingDis, 0).' = '.round($disRatio * 100, 1).'%',
            'threshold' => round($disRelianceThreshold * 100, 0).'% maximum reliance',
            'passed' => ! $overReliant,
            'explanation' => $overReliant
                ? $firstName.'\'s death in service benefit makes up '.round($disRatio * 100, 1).'% of total life cover (threshold: '.round($disRelianceThreshold * 100, 0).'%). If '.$firstName.' changed employer, they could lose £'.number_format($deathInService, 0).' of protection.'
                : $firstName.'\'s death in service benefit is '.round($disRatio * 100, 1).'% of total life cover, within the '.round($disRelianceThreshold * 100, 0).'% threshold.',
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
        $user = $this->resolveUser($comprehensivePlan);
        $firstName = $user->first_name ?? 'The user';

        // Step 1: User context
        $trace[] = $this->buildUserContextTrace($comprehensivePlan);

        $financialSummary = $comprehensivePlan['financial_summary'] ?? [];
        $employmentIncome = (float) ($financialSummary['income_breakdown']['employment_income'] ?? 0);
        $employmentStatus = $user?->employment_status ?? 'not specified';
        $employerName = $this->getProfileValue($comprehensivePlan, 'employer_name');

        // Step 2: Employment status check
        $trace[] = [
            'question' => 'Is '.$firstName.' currently employed?',
            'data_field' => 'Employment status and income',
            'data_value' => 'Status: '.$employmentStatus.', employment income: £'.number_format($employmentIncome, 0).' per year'.($employerName ? ', employer: '.$employerName : ''),
            'threshold' => 'Employment income greater than £0',
            'passed' => $employmentIncome <= 0,
            'explanation' => $employmentIncome > 0
                ? $firstName.' has employment income of £'.number_format($employmentIncome, 0).' per year'.($employerName ? ' from '.$employerName : '').', so they may have employer benefits to record.'
                : 'No employment income recorded for '.$firstName.', so employer benefits do not apply.',
        ];

        if ($employmentIncome <= 0) {
            return null;
        }

        // Step 3: Check each employer benefit type
        $disMultiple = $this->getProfileValue($comprehensivePlan, 'death_in_service_multiple');
        $groupIp = $this->getProfileValue($comprehensivePlan, 'group_ip_benefit_percent');
        $groupIpMonths = $this->getProfileValue($comprehensivePlan, 'group_ip_benefit_months');
        $groupCi = $this->getProfileValue($comprehensivePlan, 'group_ci_amount');
        $hasPmi = $this->getProfileValue($comprehensivePlan, 'has_employer_pmi');

        $hasAnyBenefit = ($disMultiple !== null && $disMultiple > 0)
            || ($groupIp !== null && $groupIp > 0)
            || ($groupCi !== null && $groupCi > 0)
            || ($hasPmi === true);

        $benefitDetails = [];
        $benefitDetails[] = 'Death in service: '.($disMultiple !== null && $disMultiple > 0 ? $disMultiple.'× salary (£'.number_format($disMultiple * $employmentIncome, 0).')' : 'Not recorded');
        $benefitDetails[] = 'Group income protection: '.($groupIp !== null && $groupIp > 0 ? round((float) $groupIp, 1).'% of salary'.($groupIpMonths ? ' for '.$groupIpMonths.' months' : '') : 'Not recorded');
        $benefitDetails[] = 'Group critical illness: '.($groupCi !== null && $groupCi > 0 ? '£'.number_format((float) $groupCi, 0) : 'Not recorded');
        $benefitDetails[] = 'Private medical insurance: '.($hasPmi === true ? 'Yes' : 'Not recorded');

        $trace[] = [
            'question' => 'Has '.$firstName.' recorded any employer protection benefits?',
            'data_field' => 'Employer benefits recorded',
            'data_value' => $hasAnyBenefit ? 'Yes' : 'None recorded. '.implode('. ', $benefitDetails),
            'threshold' => 'At least 1 employer benefit should be recorded for an employed person',
            'passed' => $hasAnyBenefit,
            'explanation' => $hasAnyBenefit
                ? $firstName.' has recorded employer protection benefits: '.implode('. ', $benefitDetails).'.'
                : 'No employer benefits have been recorded for '.$firstName.' despite having employment income of £'.number_format($employmentIncome, 0).'. Many employers provide death in service, group income protection, or private medical insurance. Recording these ensures the analysis is accurate.',
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
        $user = $this->resolveUser($comprehensivePlan);
        $firstName = $user->first_name ?? 'The user';

        // Step 1: User context
        $trace[] = $this->buildUserContextTrace($comprehensivePlan);

        $groupIpDefinition = $this->getProfileValue($comprehensivePlan, 'group_ip_definition');
        $groupIpPercent = $this->getProfileValue($comprehensivePlan, 'group_ip_benefit_percent');
        $groupIpMonths = $this->getProfileValue($comprehensivePlan, 'group_ip_benefit_months');
        $employerName = $this->getProfileValue($comprehensivePlan, 'employer_name');
        $isAnyOccupation = $groupIpDefinition !== null && strtolower((string) $groupIpDefinition) === 'any';

        $employerLabel = $employerName ? $employerName.'\'s' : 'the employer\'s';
        $benefitDetail = '';
        if ($groupIpPercent !== null && $groupIpPercent > 0) {
            $benefitDetail = ' at '.round((float) $groupIpPercent, 1).'% of salary';
            if ($groupIpMonths) {
                $benefitDetail .= ' for '.$groupIpMonths.' months';
            }
        }

        // Step 2: Group IP definition check
        $trace[] = [
            'question' => 'Does '.$employerLabel.' group income protection use an "any occupation" definition?',
            'data_field' => 'Group income protection definition',
            'data_value' => $groupIpDefinition !== null ? ucfirst((string) $groupIpDefinition).' occupation'.$benefitDetail : 'Not recorded',
            'threshold' => 'Should be "own occupation" not "any occupation"',
            'passed' => ! $isAnyOccupation,
            'explanation' => $isAnyOccupation
                ? $firstName.'\'s group income protection'.(($employerName ? ' through '.$employerName : '')).' uses an "any occupation" definition'.$benefitDetail.'. This means it only pays out if '.$firstName.' cannot do any job at all — a much harder test to meet than "own occupation".'
                : ($groupIpDefinition !== null
                    ? $firstName.'\'s group income protection uses an "'.ucfirst((string) $groupIpDefinition).' occupation" definition'.$benefitDetail.'.'
                    : 'No group income protection scheme recorded for '.$firstName.'.'),
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
        $user = $this->resolveUser($comprehensivePlan);
        $firstName = $user->first_name ?? 'The user';
        $userId = $this->extractUserId($comprehensivePlan);

        // Step 1: User context
        $trace[] = $this->buildUserContextTrace($comprehensivePlan);

        $coverageAnalysis = $comprehensivePlan['coverage_analysis'] ?? [];
        $ipData = $coverageAnalysis['income_protection'] ?? [];
        $ipGap = (float) ($ipData['gap'] ?? 0);
        $ipNeed = (float) ($ipData['need'] ?? 0);
        $ipCoverage = (float) ($ipData['coverage'] ?? 0);

        // Step 2: Existing IP cover details
        $ipPolicySummary = 'None';
        if ($userId) {
            $ipPolicies = IncomeProtectionPolicy::where('user_id', $userId)->whereNull('deleted_at')->get();
            $ipPolicySummary = $ipPolicies->isEmpty() ? 'None' : $this->formatIpPolicySummary($ipPolicies);
        }

        $trace[] = [
            'question' => 'Does '.$firstName.' have an income protection gap?',
            'data_field' => 'Income protection gap calculation',
            'data_value' => '£'.number_format($ipNeed, 0).'/month need - £'.number_format($ipCoverage, 0).'/month cover = £'.number_format($ipGap, 0).'/month gap. Policies: '.$ipPolicySummary,
            'threshold' => '£0 (no gap)',
            'passed' => $ipGap <= 0,
            'explanation' => $ipGap > 0
                ? $firstName.' has an income protection shortfall of £'.number_format($ipGap, 0).' per month (need: £'.number_format($ipNeed, 0).' vs cover: £'.number_format($ipCoverage, 0).').'
                : $firstName.'\'s income protection cover meets their needs.',
        ];

        if ($ipGap <= 0) {
            return null;
        }

        // Step 3: State benefit analysis
        $stateBenefits = $this->getNestedValue($comprehensivePlan, 'protection_needs.state_benefits', []);

        $sspTotal = 0.0;
        $sspWeekly = (float) $this->taxConfig->get('benefits.ssp.weekly_rate', 116.75);
        $sspMaxWeeks = (int) $this->taxConfig->get('benefits.ssp.max_weeks', 28);
        if (is_array($stateBenefits) && isset($stateBenefits['ssp_total_entitlement'])) {
            $sspTotal = (float) $stateBenefits['ssp_total_entitlement'];
        } else {
            $sspTotal = $sspWeekly * $sspMaxWeeks;
        }

        $sspMonthly = ($sspWeekly * 52) / 12;

        $trace[] = [
            'question' => 'Does the income protection gap persist even after accounting for Statutory Sick Pay?',
            'data_field' => 'Statutory Sick Pay entitlement',
            'data_value' => '£'.number_format($sspWeekly, 2).'/week × '.$sspMaxWeeks.' weeks = £'.number_format($sspTotal, 0).' total (≈ £'.number_format($sspMonthly, 0).'/month)',
            'threshold' => 'Statutory Sick Pay is time-limited ('.$sspMaxWeeks.' weeks) and covers only £'.number_format($sspMonthly, 0).'/month',
            'passed' => false,
            'explanation' => $firstName.'\'s Statutory Sick Pay entitlement is £'.number_format($sspWeekly, 2).' per week for up to '.$sspMaxWeeks.' weeks (£'.number_format($sspTotal, 0).' total). This is a time-limited benefit that would not replace income long-term. '.$firstName.'\'s monthly gap of £'.number_format($ipGap, 0).' would remain after Statutory Sick Pay ends.',
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
        $user = $this->resolveUser($comprehensivePlan);
        $firstName = $user->first_name ?? 'The user';
        $userId = $this->extractUserId($comprehensivePlan);

        // Step 1: User context
        $trace[] = $this->buildUserContextTrace($comprehensivePlan);

        $financialSummary = $comprehensivePlan['financial_summary'] ?? [];
        $employmentIncome = (float) ($financialSummary['income_breakdown']['employment_income'] ?? 0);
        $selfEmploymentIncome = (float) ($financialSummary['income_breakdown']['self_employment_income'] ?? 0);
        $employmentStatus = $user?->employment_status ?? 'not specified';

        // Must be self-employed (has self-employment income, no employment income)
        $isSelfEmployed = $selfEmploymentIncome > 0 && $employmentIncome <= 0;

        // Step 2: Employment status check
        $trace[] = [
            'question' => 'Is '.$firstName.' self-employed?',
            'data_field' => 'Employment status and income',
            'data_value' => 'Status: '.$employmentStatus.', self-employment income: £'.number_format($selfEmploymentIncome, 0).'/year, employment income: £'.number_format($employmentIncome, 0).'/year',
            'threshold' => 'Self-employment income with no employment income',
            'passed' => ! $isSelfEmployed,
            'explanation' => $isSelfEmployed
                ? $firstName.' is self-employed earning £'.number_format($selfEmploymentIncome, 0).' per year with no employer to provide sick pay or group income protection.'
                : $firstName.' is not solely self-employed, so this check does not apply.',
        ];

        if (! $isSelfEmployed) {
            return null;
        }

        // Step 3: Check for IP policies with details
        $ipPolicySummary = 'None';
        $ipCount = 0;
        if ($userId) {
            $ipPolicies = IncomeProtectionPolicy::where('user_id', $userId)->whereNull('deleted_at')->get();
            $ipCount = $ipPolicies->count();
            $ipPolicySummary = $ipPolicies->isEmpty() ? 'None' : $this->formatIpPolicySummary($ipPolicies);
        } else {
            $currentCoverage = $comprehensivePlan['current_coverage'] ?? [];
            $ipCount = count($currentCoverage['income_protection']['policies'] ?? []);
        }

        $monthlyIncome = $selfEmploymentIncome / 12;

        $trace[] = [
            'question' => 'Does '.$firstName.' have any personal income protection policies?',
            'data_field' => 'Income protection policies',
            'data_value' => $ipCount.' policies. '.$ipPolicySummary,
            'threshold' => 'At least 1 policy (self-employed with £'.number_format($monthlyIncome, 0).'/month income at risk)',
            'passed' => $ipCount > 0,
            'explanation' => $ipCount > 0
                ? $firstName.' has '.$ipCount.' income protection policies in place.'
                : $firstName.' has no income protection policies. As a self-employed person earning £'.number_format($selfEmploymentIncome, 0).' per year (£'.number_format($monthlyIncome, 0).'/month), there is no employer sick pay to fall back on if unable to work.',
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
        $currentCoverage = $comprehensivePlan['current_coverage'] ?? [];
        $lifePolicies = $currentCoverage['life_insurance']['policies'] ?? [];

        if (empty($lifePolicies)) {
            return null;
        }

        $userId = $this->extractUserId($comprehensivePlan);
        if ($userId === null) {
            return null;
        }

        $user = User::find($userId);
        $firstName = $user->first_name ?? 'The user';

        $trace = [];

        // Step 1: User context
        $trace[] = $this->buildUserContextTrace($comprehensivePlan);

        // Step 2: All life policies with trust status
        $allLifePolicies = LifeInsurancePolicy::where('user_id', $userId)->whereNull('deleted_at')->get();
        $untrustedPolicies = $allLifePolicies->filter(fn ($p) => ! $p->in_trust);
        $trustedPolicies = $allLifePolicies->filter(fn ($p) => $p->in_trust);

        $untrustedCount = $untrustedPolicies->count();
        $totalCount = $allLifePolicies->count();

        $untrustedDetails = $untrustedPolicies->map(fn ($p) => ($p->provider ?? 'Unknown').' (£'.number_format((float) $p->sum_assured, 0).', '.ucfirst(str_replace('_', ' ', $p->policy_type ?? 'standard')).')')->implode('; ');
        $trustedDetails = $trustedPolicies->map(fn ($p) => ($p->provider ?? 'Unknown').' (£'.number_format((float) $p->sum_assured, 0).')')->implode('; ');

        $trace[] = [
            'question' => 'Are any of '.$firstName.'\'s life insurance policies not held in trust?',
            'data_field' => 'Policies not in trust',
            'data_value' => $untrustedCount.' of '.$totalCount.' policies not in trust. Not in trust: '.($untrustedDetails ?: 'None').'. In trust: '.($trustedDetails ?: 'None'),
            'threshold' => '0 policies outside trust',
            'passed' => $untrustedCount === 0,
            'explanation' => $untrustedCount > 0
                ? $untrustedCount.' of '.$firstName.'\'s life insurance policies are not held in trust: '.$untrustedDetails.'. Placing policies in trust can help ensure the proceeds are paid quickly to beneficiaries and may reduce inheritance tax liability.'
                : 'All of '.$firstName.'\'s life insurance policies are held in trust.',
        ];

        if ($untrustedPolicies->isEmpty()) {
            return null;
        }

        $results = [];
        foreach ($untrustedPolicies as $policy) {
            $policyTrace = $trace;
            $policyTrace[] = [
                'question' => 'What are the details of the untrusted policy from '.($policy->provider ?? 'Unknown').'?',
                'data_field' => 'Policy details',
                'data_value' => ($policy->provider ?? 'Unknown').' — £'.number_format((float) $policy->sum_assured, 0).' sum assured, £'.number_format((float) $policy->premium_amount, 2).'/'.($policy->premium_frequency ?? 'month').', '.ucfirst(str_replace('_', ' ', $policy->policy_type ?? 'standard')).($policy->policy_start_date ? ', started '.$policy->policy_start_date->format('j F Y') : '').($policy->policy_end_date ? ', ends '.$policy->policy_end_date->format('j F Y') : ''),
                'threshold' => 'Policy should be placed in trust',
                'passed' => false,
                'explanation' => $firstName.'\'s '.($policy->provider ?? 'Unknown').' policy with £'.number_format((float) $policy->sum_assured, 0).' sum assured is not held in trust. If '.$firstName.' were to pass away, the proceeds could form part of the estate and be subject to inheritance tax.',
            ];

            $vars = [
                'provider' => $policy->provider ?? 'your insurer',
            ];
            $rec = $this->buildRecommendation($definition, $vars, 0);
            $rec['decision_trace'] = $policyTrace;
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
        $user = $this->resolveUser($comprehensivePlan);
        $firstName = $user->first_name ?? 'The user';

        // Step 1: User context
        $trace[] = $this->buildUserContextTrace($comprehensivePlan);

        $userProfile = $comprehensivePlan['user_profile'] ?? [];
        $maritalStatus = strtolower((string) ($userProfile['marital_status'] ?? ''));
        $isMarriedOrCp = $maritalStatus === 'married' || $maritalStatus === 'civil partnership';

        // Step 2: Marital status check
        $trace[] = [
            'question' => 'Is '.$firstName.' married or in a civil partnership?',
            'data_field' => 'Marital status',
            'data_value' => ucfirst($maritalStatus ?: 'Not recorded'),
            'threshold' => 'Married or civil partnership',
            'passed' => ! $isMarriedOrCp,
            'explanation' => $isMarriedOrCp
                ? $firstName.' is '.$maritalStatus.', so joint life cover may be worth considering.'
                : 'This check only applies to those who are married or in a civil partnership.',
        ];

        if (! $isMarriedOrCp) {
            return null;
        }

        $userId = $this->extractUserId($comprehensivePlan);
        if ($userId === null) {
            return null;
        }

        // Step 3: Non-joint policies with details
        $allLifePolicies = LifeInsurancePolicy::where('user_id', $userId)->whereNull('deleted_at')->get();
        $nonJointPolicies = $allLifePolicies->filter(fn ($p) => ! $p->joint_life);
        $jointPolicies = $allLifePolicies->filter(fn ($p) => $p->joint_life);

        $nonJointCount = $nonJointPolicies->count();
        $nonJointDetails = $nonJointPolicies->map(fn ($p) => ($p->provider ?? 'Unknown').' (£'.number_format((float) $p->sum_assured, 0).', '.ucfirst(str_replace('_', ' ', $p->policy_type ?? 'standard')).')')->implode('; ');
        $jointDetails = $jointPolicies->map(fn ($p) => ($p->provider ?? 'Unknown').' (£'.number_format((float) $p->sum_assured, 0).')')->implode('; ');

        $trace[] = [
            'question' => 'Does '.$firstName.' have any life insurance policies that are not joint life?',
            'data_field' => 'Non-joint life policies',
            'data_value' => $nonJointCount.' single-life policies: '.($nonJointDetails ?: 'None').'. Joint policies: '.($jointDetails ?: 'None'),
            'threshold' => '0 non-joint policies (married/civil partnership)',
            'passed' => $nonJointCount === 0,
            'explanation' => $nonJointCount > 0
                ? $nonJointCount.' of '.$firstName.'\'s life insurance policies are single life rather than joint: '.$nonJointDetails.'. As '.$firstName.' is '.$maritalStatus.', a joint life policy may offer better value or more appropriate cover.'
                : 'All of '.$firstName.'\'s life insurance policies are joint life.',
        ];

        if ($nonJointPolicies->isEmpty()) {
            return null;
        }

        $results = [];
        foreach ($nonJointPolicies as $policy) {
            $policyTrace = $trace;
            $policyTrace[] = [
                'question' => 'What are the details of the single-life policy from '.($policy->provider ?? 'Unknown').'?',
                'data_field' => 'Policy details',
                'data_value' => ($policy->provider ?? 'Unknown').' — £'.number_format((float) $policy->sum_assured, 0).' sum assured, £'.number_format((float) $policy->premium_amount, 2).'/'.($policy->premium_frequency ?? 'month').', '.ucfirst(str_replace('_', ' ', $policy->policy_type ?? 'standard')).($policy->policy_start_date ? ', started '.$policy->policy_start_date->format('j F Y') : ''),
                'threshold' => 'Consider converting to joint life policy',
                'passed' => false,
                'explanation' => $firstName.'\'s '.($policy->provider ?? 'Unknown').' policy (£'.number_format((float) $policy->sum_assured, 0).') is single life. A joint life first death policy could cover both '.$firstName.' and their spouse, potentially at a lower combined premium.',
            ];

            $vars = [
                'provider' => $policy->provider ?? 'your insurer',
            ];
            $rec = $this->buildRecommendation($definition, $vars, 0);
            $rec['decision_trace'] = $policyTrace;
            $results[] = $rec;
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

        $user = User::find($userId);
        $firstName = $user->first_name ?? 'The user';

        $trace = [];

        // Step 1: User context
        $trace[] = $this->buildUserContextTrace($comprehensivePlan);

        $monthsThreshold = (int) ($definition->trigger_config['months_threshold'] ?? 24);
        $thresholdDate = now()->addMonths($monthsThreshold);

        $expiringPolicies = LifeInsurancePolicy::where('user_id', $userId)
            ->whereNull('deleted_at')
            ->whereNotNull('policy_end_date')
            ->where('policy_end_date', '>', now())
            ->where('policy_end_date', '<=', $thresholdDate)
            ->get();

        $expiringCount = $expiringPolicies->count();

        // Step 2: Expiring policies with details
        $expiringDetails = $expiringPolicies->map(function ($p) {
            $daysLeft = now()->diffInDays($p->policy_end_date);

            return ($p->provider ?? 'Unknown').' (£'.number_format((float) $p->sum_assured, 0).', expires '.$p->policy_end_date->format('j F Y').' — '.$daysLeft.' days remaining)';
        })->implode('; ');

        $trace[] = [
            'question' => 'Does '.$firstName.' have any life insurance policies expiring within the next '.$monthsThreshold.' months?',
            'data_field' => 'Policies expiring soon',
            'data_value' => $expiringCount.' policies. '.($expiringDetails ?: 'None'),
            'threshold' => 'Expiry within '.$monthsThreshold.' months (before '.$thresholdDate->format('j F Y').')',
            'passed' => $expiringCount === 0,
            'explanation' => $expiringCount > 0
                ? $expiringCount.' of '.$firstName.'\'s life insurance policies will expire within the next '.$monthsThreshold.' months: '.$expiringDetails.'. Cover should be reviewed before it lapses.'
                : 'None of '.$firstName.'\'s life insurance policies are due to expire within the next '.$monthsThreshold.' months.',
        ];

        if ($expiringPolicies->isEmpty()) {
            return null;
        }

        $results = [];
        foreach ($expiringPolicies as $policy) {
            $daysLeft = now()->diffInDays($policy->policy_end_date);
            $policyTrace = $trace;
            $policyTrace[] = [
                'question' => 'What are the details of the expiring policy from '.($policy->provider ?? 'Unknown').'?',
                'data_field' => 'Policy details',
                'data_value' => ($policy->provider ?? 'Unknown').' — £'.number_format((float) $policy->sum_assured, 0).' sum assured, £'.number_format((float) $policy->premium_amount, 2).'/'.($policy->premium_frequency ?? 'month').', '.ucfirst(str_replace('_', ' ', $policy->policy_type ?? 'standard')).', started '.($policy->policy_start_date ? $policy->policy_start_date->format('j F Y') : 'unknown').', expires '.$policy->policy_end_date->format('j F Y').' ('.$daysLeft.' days)',
                'threshold' => 'Review and arrange replacement cover before expiry',
                'passed' => false,
                'explanation' => $firstName.'\'s '.($policy->provider ?? 'Unknown').' policy with £'.number_format((float) $policy->sum_assured, 0).' sum assured expires on '.$policy->policy_end_date->format('j F Y').' ('.$daysLeft.' days). Replacement cover should be arranged before this date to avoid a gap in protection.',
            ];

            $vars = [
                'provider' => $policy->provider ?? 'your insurer',
                'end_date' => $policy->policy_end_date->format('j F Y'),
            ];
            $rec = $this->buildRecommendation($definition, $vars, 0);
            $rec['decision_trace'] = $policyTrace;
            $results[] = $rec;
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

        $user = User::find($userId);
        $firstName = $user->first_name ?? 'The user';

        $trace = [];

        // Step 1: User context
        $trace[] = $this->buildUserContextTrace($comprehensivePlan);

        $expiredPolicies = LifeInsurancePolicy::where('user_id', $userId)
            ->whereNull('deleted_at')
            ->whereNotNull('policy_end_date')
            ->where('policy_end_date', '<', now())
            ->get();

        $expiredCount = $expiredPolicies->count();

        // Step 2: Expired policies with details
        $expiredDetails = $expiredPolicies->map(function ($p) {
            $daysSince = $p->policy_end_date->diffInDays(now());

            return ($p->provider ?? 'Unknown').' (£'.number_format((float) $p->sum_assured, 0).', expired '.$p->policy_end_date->format('j F Y').' — '.$daysSince.' days ago)';
        })->implode('; ');

        $totalExpiredCover = $expiredPolicies->sum('sum_assured');

        $trace[] = [
            'question' => 'Does '.$firstName.' have any life insurance policies that have already expired?',
            'data_field' => 'Expired policies',
            'data_value' => $expiredCount.' policies totalling £'.number_format((float) $totalExpiredCover, 0).' of lost cover. '.($expiredDetails ?: 'None'),
            'threshold' => '0 expired policies',
            'passed' => $expiredCount === 0,
            'explanation' => $expiredCount > 0
                ? $expiredCount.' of '.$firstName.'\'s life insurance policies have expired, representing £'.number_format((float) $totalExpiredCover, 0).' of lost cover: '.$expiredDetails.'. '.$firstName.' is no longer covered by these policies and should review whether replacement cover is needed.'
                : 'None of '.$firstName.'\'s life insurance policies have expired.',
        ];

        if ($expiredPolicies->isEmpty()) {
            return null;
        }

        $results = [];
        foreach ($expiredPolicies as $policy) {
            $daysSince = $policy->policy_end_date->diffInDays(now());
            $policyTrace = $trace;
            $policyTrace[] = [
                'question' => 'What are the details of the expired policy from '.($policy->provider ?? 'Unknown').'?',
                'data_field' => 'Policy details',
                'data_value' => ($policy->provider ?? 'Unknown').' — £'.number_format((float) $policy->sum_assured, 0).' sum assured, '.ucfirst(str_replace('_', ' ', $policy->policy_type ?? 'standard')).', started '.($policy->policy_start_date ? $policy->policy_start_date->format('j F Y') : 'unknown').', expired '.$policy->policy_end_date->format('j F Y').' ('.$daysSince.' days ago)',
                'threshold' => 'Expired — no longer providing cover',
                'passed' => false,
                'explanation' => $firstName.'\'s '.($policy->provider ?? 'Unknown').' policy with £'.number_format((float) $policy->sum_assured, 0).' sum assured expired on '.$policy->policy_end_date->format('j F Y').' ('.$daysSince.' days ago). This cover no longer exists and replacement should be considered.',
            ];

            $vars = [
                'provider' => $policy->provider ?? 'your insurer',
                'end_date' => $policy->policy_end_date->format('j F Y'),
            ];
            $rec = $this->buildRecommendation($definition, $vars, 0);
            $rec['decision_trace'] = $policyTrace;
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
        $user = $this->resolveUser($comprehensivePlan);
        $firstName = $user->first_name ?? 'The user';

        // Step 1: User context
        $trace[] = $this->buildUserContextTrace($comprehensivePlan);

        $financialSummary = $comprehensivePlan['financial_summary'] ?? [];
        $totalDebt = (float) ($financialSummary['total_debt'] ?? 0);
        $debtBreakdown = $financialSummary['debt_breakdown'] ?? [];
        $mortgageDebt = (float) ($debtBreakdown['mortgage'] ?? 0);
        $otherDebt = $totalDebt - $mortgageDebt;

        // Step 2: Mortgage check
        $trace[] = [
            'question' => 'Does '.$firstName.' have a mortgage?',
            'data_field' => 'Debt breakdown',
            'data_value' => 'Mortgage: £'.number_format($mortgageDebt, 0).', other debt: £'.number_format(max(0, $otherDebt), 0).', total: £'.number_format($totalDebt, 0),
            'threshold' => 'Mortgage balance greater than £0',
            'passed' => $mortgageDebt <= 0,
            'explanation' => $mortgageDebt > 0
                ? $firstName.' has an outstanding mortgage balance of £'.number_format($mortgageDebt, 0).'.'
                : 'No mortgage debt recorded for '.$firstName.'.',
        ];

        if ($mortgageDebt <= 0) {
            return null;
        }

        $userId = $this->extractUserId($comprehensivePlan);
        if ($userId === null) {
            return null;
        }

        // Step 3: Check for decreasing term or mortgage protection policies
        $mortgageProtectionPolicies = LifeInsurancePolicy::where('user_id', $userId)
            ->whereNull('deleted_at')
            ->where(function ($q) {
                $q->where('policy_type', 'decreasing_term')
                    ->orWhere('is_mortgage_protection', true);
            })
            ->get();

        $hasMortgageProtection = $mortgageProtectionPolicies->isNotEmpty();
        $protectionDetails = $mortgageProtectionPolicies->map(fn ($p) => ($p->provider ?? 'Unknown').' (£'.number_format((float) $p->sum_assured, 0).')')->implode('; ');

        // Also list existing life policies for context
        $allLifePolicies = LifeInsurancePolicy::where('user_id', $userId)->whereNull('deleted_at')->get();
        $allPolicySummary = $this->formatLifePolicySummary($allLifePolicies);

        $trace[] = [
            'question' => 'Does '.$firstName.' have a decreasing term or mortgage protection policy?',
            'data_field' => 'Mortgage protection cover',
            'data_value' => $hasMortgageProtection ? 'Yes: '.$protectionDetails : 'No. Existing life policies: '.$allPolicySummary,
            'threshold' => 'At least 1 decreasing term or mortgage protection policy for £'.number_format($mortgageDebt, 0).' mortgage',
            'passed' => $hasMortgageProtection,
            'explanation' => $hasMortgageProtection
                ? $firstName.' has mortgage protection in place: '.$protectionDetails.'.'
                : $firstName.' has no decreasing term or mortgage protection policy to cover the £'.number_format($mortgageDebt, 0).' mortgage. A decreasing term policy is designed to cover the mortgage balance as it reduces and typically costs less than level term cover.',
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
        $userId = $this->extractUserId($comprehensivePlan);
        if ($userId === null) {
            return null;
        }

        $user = User::find($userId);
        $firstName = $user->first_name ?? 'The user';

        $trace = [];

        // Step 1: User context
        $trace[] = $this->buildUserContextTrace($comprehensivePlan);

        // Step 2: Check all IP policies with occupation class details
        $allIpPolicies = IncomeProtectionPolicy::where('user_id', $userId)->whereNull('deleted_at')->get();
        $anyOccupationPolicies = $allIpPolicies->where('occupation_class', 'any');
        $ownOccupationPolicies = $allIpPolicies->where('occupation_class', '!=', 'any');

        $anyOccCount = $anyOccupationPolicies->count();
        $anyOccDetails = $anyOccupationPolicies->map(fn ($p) => ($p->provider ?? 'Unknown').' (£'.number_format((float) $p->benefit_amount, 2).'/'.($p->benefit_frequency ?? 'month').', any occupation)')->implode('; ');
        $ownOccDetails = $ownOccupationPolicies->map(fn ($p) => ($p->provider ?? 'Unknown').' (£'.number_format((float) $p->benefit_amount, 2).'/'.($p->benefit_frequency ?? 'month').', '.ucfirst($p->occupation_class ?? 'unknown').' occupation)')->implode('; ');

        $trace[] = [
            'question' => 'Do any of '.$firstName.'\'s income protection policies use an "any occupation" definition?',
            'data_field' => 'Policies with "any occupation" definition',
            'data_value' => $anyOccCount.' policies with "any occupation": '.($anyOccDetails ?: 'None').'. Other policies: '.($ownOccDetails ?: 'None'),
            'threshold' => '0 policies with "any occupation"',
            'passed' => $anyOccCount === 0,
            'explanation' => $anyOccCount > 0
                ? $anyOccCount.' of '.$firstName.'\'s income protection policies use an "any occupation" definition: '.$anyOccDetails.'. This means '.$firstName.' would only receive a payout if unable to perform any job at all, not just their current occupation.'
                : 'None of '.$firstName.'\'s income protection policies use the weaker "any occupation" definition.',
        ];

        if ($anyOccupationPolicies->isEmpty()) {
            return null;
        }

        $results = [];
        foreach ($anyOccupationPolicies as $policy) {
            $policyTrace = $trace;
            $policyTrace[] = [
                'question' => 'What are the details of the "any occupation" policy from '.($policy->provider ?? 'Unknown').'?',
                'data_field' => 'Policy details',
                'data_value' => ($policy->provider ?? 'Unknown').' — £'.number_format((float) $policy->benefit_amount, 2).'/'.($policy->benefit_frequency ?? 'month').', any occupation definition'.($policy->deferred_period_weeks ? ', '.$policy->deferred_period_weeks.'-week deferred period' : '').($policy->benefit_period_months ? ', '.$policy->benefit_period_months.'-month benefit period' : '').($policy->policy_start_date ? ', started '.$policy->policy_start_date->format('j F Y') : ''),
                'threshold' => 'Should be "own occupation" for stronger protection',
                'passed' => false,
                'explanation' => $firstName.'\'s '.($policy->provider ?? 'Unknown').' policy pays £'.number_format((float) $policy->benefit_amount, 2).' per '.($policy->benefit_frequency ?? 'month').' but only under the "any occupation" definition. Upgrading to "own occupation" would provide a payout if '.$firstName.' cannot perform their specific job.',
            ];

            $vars = [
                'provider' => $policy->provider ?? 'your insurer',
            ];
            $rec = $this->buildRecommendation($definition, $vars, 0);
            $rec['decision_trace'] = $policyTrace;
            $results[] = $rec;
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

        $user = User::find($userId);
        $firstName = $user->first_name ?? 'The user';

        $trace = [];

        // Step 1: User context
        $trace[] = $this->buildUserContextTrace($comprehensivePlan);

        $monthsThreshold = (int) ($definition->trigger_config['months_threshold'] ?? 24);

        // Step 2: Check all IP policies for benefit period
        $allIpPolicies = IncomeProtectionPolicy::where('user_id', $userId)->whereNull('deleted_at')->get();
        $shortPolicies = $allIpPolicies->filter(fn ($p) => $p->benefit_period_months !== null && $p->benefit_period_months > 0 && $p->benefit_period_months < $monthsThreshold);
        $adequatePolicies = $allIpPolicies->filter(fn ($p) => $p->benefit_period_months === null || $p->benefit_period_months === 0 || $p->benefit_period_months >= $monthsThreshold);

        $shortCount = $shortPolicies->count();
        $shortDetails = $shortPolicies->map(fn ($p) => ($p->provider ?? 'Unknown').' (£'.number_format((float) $p->benefit_amount, 2).'/'.($p->benefit_frequency ?? 'month').', '.$p->benefit_period_months.'-month benefit period)')->implode('; ');
        $adequateDetails = $adequatePolicies->map(fn ($p) => ($p->provider ?? 'Unknown').' ('.($p->benefit_period_months ? $p->benefit_period_months.' months' : 'until retirement/no limit').')')->implode('; ');

        $trace[] = [
            'question' => 'Do any of '.$firstName.'\'s income protection policies have a short benefit period?',
            'data_field' => 'Policies with benefit period under '.$monthsThreshold.' months',
            'data_value' => $shortCount.' short-period policies: '.($shortDetails ?: 'None').'. Adequate policies: '.($adequateDetails ?: 'None'),
            'threshold' => 'Benefit period of at least '.$monthsThreshold.' months',
            'passed' => $shortCount === 0,
            'explanation' => $shortCount > 0
                ? $shortCount.' of '.$firstName.'\'s income protection policies have a benefit period shorter than '.$monthsThreshold.' months: '.$shortDetails.'. A longer benefit period provides more sustained protection during extended illness or injury.'
                : 'All of '.$firstName.'\'s income protection policies have a benefit period of at least '.$monthsThreshold.' months.',
        ];

        if ($shortPolicies->isEmpty()) {
            return null;
        }

        $results = [];
        foreach ($shortPolicies as $policy) {
            $policyTrace = $trace;
            $policyTrace[] = [
                'question' => 'What are the details of the short-period policy from '.($policy->provider ?? 'Unknown').'?',
                'data_field' => 'Policy details',
                'data_value' => ($policy->provider ?? 'Unknown').' — £'.number_format((float) $policy->benefit_amount, 2).'/'.($policy->benefit_frequency ?? 'month').', '.$policy->benefit_period_months.'-month benefit period'.($policy->deferred_period_weeks ? ', '.$policy->deferred_period_weeks.'-week deferred period' : '').($policy->occupation_class ? ', '.ucfirst($policy->occupation_class).' occupation' : '').($policy->policy_start_date ? ', started '.$policy->policy_start_date->format('j F Y') : ''),
                'threshold' => 'Minimum '.$monthsThreshold.' months benefit period recommended',
                'passed' => false,
                'explanation' => $firstName.'\'s '.($policy->provider ?? 'Unknown').' policy only pays for '.$policy->benefit_period_months.' months (threshold: '.$monthsThreshold.' months). If '.$firstName.' had a long-term illness or injury, this cover would run out after '.$policy->benefit_period_months.' months.',
            ];

            $vars = [
                'benefit_months' => (string) $policy->benefit_period_months,
                'provider' => $policy->provider ?? 'your insurer',
            ];
            $rec = $this->buildRecommendation($definition, $vars, 0);
            $rec['decision_trace'] = $policyTrace;
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
        $userId = $this->extractUserId($comprehensivePlan);
        if ($userId === null) {
            return null;
        }

        $user = User::find($userId);
        $firstName = $user->first_name ?? 'The user';

        $trace = [];

        // Step 1: User context
        $trace[] = $this->buildUserContextTrace($comprehensivePlan);

        $weeksThreshold = (int) ($definition->trigger_config['weeks_threshold'] ?? 26);

        // Step 2: Check employer sick pay
        $groupIpPercent = $this->getProfileValue($comprehensivePlan, 'group_ip_benefit_percent');
        $groupIpMonths = $this->getProfileValue($comprehensivePlan, 'group_ip_benefit_months');
        $employerName = $this->getProfileValue($comprehensivePlan, 'employer_name');
        $hasEmployerSickPay = $groupIpPercent !== null && $groupIpPercent > 0;

        $employerLabel = $employerName ? $employerName : 'the employer';

        $trace[] = [
            'question' => 'Does '.$firstName.'\'s employer provide group income protection or sick pay to bridge the deferred period?',
            'data_field' => 'Employer group income protection',
            'data_value' => $hasEmployerSickPay ? round((float) $groupIpPercent, 1).'% of salary via '.$employerLabel.($groupIpMonths ? ' for '.$groupIpMonths.' months' : '') : 'None recorded',
            'threshold' => 'Any employer sick pay provision bridges the deferred period gap',
            'passed' => $hasEmployerSickPay,
            'explanation' => $hasEmployerSickPay
                ? $firstName.'\'s employer'.($employerName ? ' ('.$employerName.')' : '').' provides group income protection at '.round((float) $groupIpPercent, 1).'% of salary'.($groupIpMonths ? ' for '.$groupIpMonths.' months' : '').', which can bridge the deferred period.'
                : $firstName.' has no employer sick pay or group income protection to cover them during the deferred period.',
        ];

        if ($hasEmployerSickPay) {
            return null;
        }

        // Step 3: Check IP policies for long deferred periods
        $allIpPolicies = IncomeProtectionPolicy::where('user_id', $userId)->whereNull('deleted_at')->get();
        $longDeferredPolicies = $allIpPolicies->filter(fn ($p) => $p->deferred_period_weeks !== null && $p->deferred_period_weeks > $weeksThreshold);
        $shortDeferredPolicies = $allIpPolicies->filter(fn ($p) => $p->deferred_period_weeks === null || $p->deferred_period_weeks <= $weeksThreshold);

        $longDeferredCount = $longDeferredPolicies->count();
        $longDetails = $longDeferredPolicies->map(fn ($p) => ($p->provider ?? 'Unknown').' (£'.number_format((float) $p->benefit_amount, 2).'/'.($p->benefit_frequency ?? 'month').', '.$p->deferred_period_weeks.'-week deferred period)')->implode('; ');
        $shortDetails = $shortDeferredPolicies->map(fn ($p) => ($p->provider ?? 'Unknown').' ('.($p->deferred_period_weeks ?? 0).'-week deferred period)')->implode('; ');

        $trace[] = [
            'question' => 'Do any of '.$firstName.'\'s income protection policies have a long deferred period?',
            'data_field' => 'Policies with deferred period over '.$weeksThreshold.' weeks',
            'data_value' => $longDeferredCount.' long-deferred policies: '.($longDetails ?: 'None').'. Others: '.($shortDetails ?: 'None'),
            'threshold' => 'Deferred period of '.$weeksThreshold.' weeks or less (without employer sick pay)',
            'passed' => $longDeferredCount === 0,
            'explanation' => $longDeferredCount > 0
                ? $longDeferredCount.' of '.$firstName.'\'s income protection policies have a deferred period longer than '.$weeksThreshold.' weeks: '.$longDetails.'. Without employer sick pay, '.$firstName.' would have no income during this waiting period.'
                : 'None of '.$firstName.'\'s income protection policies have an excessively long deferred period.',
        ];

        if ($longDeferredPolicies->isEmpty()) {
            return null;
        }

        $results = [];
        foreach ($longDeferredPolicies as $policy) {
            $deferredMonths = round($policy->deferred_period_weeks / 4.33, 1);
            $policyTrace = $trace;
            $policyTrace[] = [
                'question' => 'What are the details of the long-deferred policy from '.($policy->provider ?? 'Unknown').'?',
                'data_field' => 'Policy details',
                'data_value' => ($policy->provider ?? 'Unknown').' — £'.number_format((float) $policy->benefit_amount, 2).'/'.($policy->benefit_frequency ?? 'month').', '.$policy->deferred_period_weeks.'-week deferred period (≈ '.$deferredMonths.' months)'.($policy->benefit_period_months ? ', '.$policy->benefit_period_months.'-month benefit period' : '').($policy->occupation_class ? ', '.ucfirst($policy->occupation_class).' occupation' : '').($policy->policy_start_date ? ', started '.$policy->policy_start_date->format('j F Y') : ''),
                'threshold' => 'Maximum '.$weeksThreshold.'-week deferred period recommended without employer sick pay',
                'passed' => false,
                'explanation' => $firstName.'\'s '.($policy->provider ?? 'Unknown').' policy has a '.$policy->deferred_period_weeks.'-week deferred period (≈ '.$deferredMonths.' months). Without employer sick pay, '.$firstName.' would have no income for this entire waiting period before the benefit begins.',
            ];

            $vars = [
                'deferred_weeks' => (string) $policy->deferred_period_weeks,
                'provider' => $policy->provider ?? 'your insurer',
            ];
            $rec = $this->buildRecommendation($definition, $vars, 0);
            $rec['decision_trace'] = $policyTrace;
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
        $user = $this->resolveUser($comprehensivePlan);
        $firstName = $user->first_name ?? 'The user';
        $userId = $this->extractUserId($comprehensivePlan);

        // Step 1: User context
        $trace[] = $this->buildUserContextTrace($comprehensivePlan);

        $financialSummary = $comprehensivePlan['financial_summary'] ?? [];
        $debtBreakdown = $financialSummary['debt_breakdown'] ?? [];
        $mortgageDebt = (float) ($debtBreakdown['mortgage'] ?? 0);

        // Step 2: Mortgage check
        $trace[] = [
            'question' => 'Does '.$firstName.' have a mortgage?',
            'data_field' => 'Outstanding mortgage balance',
            'data_value' => '£'.number_format($mortgageDebt, 0),
            'threshold' => 'Greater than £0',
            'passed' => $mortgageDebt <= 0,
            'explanation' => $mortgageDebt > 0
                ? $firstName.' has an outstanding mortgage balance of £'.number_format($mortgageDebt, 0).'.'
                : 'No mortgage debt recorded for '.$firstName.'.',
        ];

        if ($mortgageDebt <= 0) {
            return null;
        }

        // Step 3: Critical illness cover check with policy details
        $coverageAnalysis = $comprehensivePlan['coverage_analysis'] ?? [];
        $ciCoverage = (float) ($coverageAnalysis['critical_illness']['coverage'] ?? 0);

        $ciPolicySummary = 'None';
        if ($userId) {
            $ciPolicies = CriticalIllnessPolicy::where('user_id', $userId)->whereNull('deleted_at')->get();
            $ciPolicySummary = $ciPolicies->isEmpty() ? 'None' : $this->formatCiPolicySummary($ciPolicies);
        }

        // Also check group CI
        $groupCi = $this->getProfileValue($comprehensivePlan, 'group_ci_amount');
        $groupCiStr = ($groupCi !== null && $groupCi > 0) ? '. Group critical illness: £'.number_format((float) $groupCi, 0) : '';

        $trace[] = [
            'question' => 'Does '.$firstName.' have any critical illness cover?',
            'data_field' => 'Critical illness coverage',
            'data_value' => '£'.number_format($ciCoverage, 0).' total. Policies: '.$ciPolicySummary.$groupCiStr,
            'threshold' => 'Greater than £0 (mortgage of £'.number_format($mortgageDebt, 0).' at risk)',
            'passed' => $ciCoverage > 0,
            'explanation' => $ciCoverage > 0
                ? $firstName.' has £'.number_format($ciCoverage, 0).' of critical illness cover.'
                : $firstName.' has no critical illness cover. A serious diagnosis such as cancer, heart attack, or stroke could leave '.$firstName.' unable to meet the £'.number_format($mortgageDebt, 0).' mortgage repayments.',
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
        $userId = $this->extractUserId($comprehensivePlan);
        if ($userId === null) {
            return null;
        }

        $user = User::find($userId);
        $firstName = $user->first_name ?? 'The user';

        $trace = [];

        // Step 1: User context
        $trace[] = $this->buildUserContextTrace($comprehensivePlan);

        // Step 2: Check for combined policies with details
        $allCiPolicies = CriticalIllnessPolicy::where('user_id', $userId)->whereNull('deleted_at')->get();
        $combinedPolicies = $allCiPolicies->where('policy_type', 'combined');
        $standalonePolicies = $allCiPolicies->where('policy_type', '!=', 'combined');

        $combinedCount = $combinedPolicies->count();
        $combinedDetails = $combinedPolicies->map(fn ($p) => ($p->provider ?? 'Unknown').' (£'.number_format((float) $p->sum_assured, 0).', combined life and critical illness)')->implode('; ');
        $standaloneDetails = $standalonePolicies->map(fn ($p) => ($p->provider ?? 'Unknown').' (£'.number_format((float) $p->sum_assured, 0).', '.ucfirst(str_replace('_', ' ', $p->policy_type ?? 'standard')).')')->implode('; ');

        $totalCombinedCover = $combinedPolicies->sum('sum_assured');

        $trace[] = [
            'question' => 'Does '.$firstName.' have any combined life and critical illness policies?',
            'data_field' => 'Combined life and critical illness policies',
            'data_value' => $combinedCount.' combined policies totalling £'.number_format((float) $totalCombinedCover, 0).': '.($combinedDetails ?: 'None').'. Standalone policies: '.($standaloneDetails ?: 'None'),
            'threshold' => '0 combined policies (standalone preferred)',
            'passed' => $combinedCount === 0,
            'explanation' => $combinedCount > 0
                ? $combinedCount.' of '.$firstName.'\'s critical illness policies are combined with life cover: '.$combinedDetails.'. With a combined policy, if '.$firstName.' claims for a critical illness, the life cover of £'.number_format((float) $totalCombinedCover, 0).' is also reduced or lost. Standalone policies provide independent protection for each risk.'
                : $firstName.' does not have any combined life and critical illness policies.',
        ];

        if ($combinedPolicies->isEmpty()) {
            return null;
        }

        $results = [];
        foreach ($combinedPolicies as $policy) {
            $policyTrace = $trace;
            $policyTrace[] = [
                'question' => 'What are the details of the combined policy from '.($policy->provider ?? 'Unknown').'?',
                'data_field' => 'Policy details',
                'data_value' => ($policy->provider ?? 'Unknown').' — £'.number_format((float) $policy->sum_assured, 0).' sum assured, £'.number_format((float) $policy->premium_amount, 2).'/'.($policy->premium_frequency ?? 'month').', combined life and critical illness'.($policy->policy_start_date ? ', started '.$policy->policy_start_date->format('j F Y') : '').($policy->policy_term_years ? ', '.$policy->policy_term_years.'-year term' : ''),
                'threshold' => 'Standalone policies recommended for independent protection',
                'passed' => false,
                'explanation' => $firstName.'\'s '.($policy->provider ?? 'Unknown').' combined policy (£'.number_format((float) $policy->sum_assured, 0).') means a critical illness claim would eliminate the life cover. If '.$firstName.' survived a critical illness but later died, there would be no death benefit for dependants.',
            ];

            $vars = [
                'provider' => $policy->provider ?? 'your insurer',
            ];
            $rec = $this->buildRecommendation($definition, $vars, 0);
            $rec['decision_trace'] = $policyTrace;
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
        $user = $this->resolveUser($comprehensivePlan);
        $firstName = $user->first_name ?? 'The user';
        $userId = $this->extractUserId($comprehensivePlan);

        // Step 1: User context
        $trace[] = $this->buildUserContextTrace($comprehensivePlan);

        $userProfile = $comprehensivePlan['user_profile'] ?? [];
        $dependants = (int) ($userProfile['number_of_dependents'] ?? 0);
        $dependantsAges = $userProfile['dependents_ages'] ?? [];
        $agesStr = ! empty($dependantsAges) ? ' (ages: '.implode(', ', $dependantsAges).')' : '';

        // Step 2: Dependants check
        $trace[] = [
            'question' => 'Does '.$firstName.' have any dependants?',
            'data_field' => 'Number of dependants',
            'data_value' => $dependants.' dependant(s)'.$agesStr,
            'threshold' => 'At least 1 dependant',
            'passed' => $dependants <= 0,
            'explanation' => $dependants > 0
                ? $firstName.' has '.$dependants.' dependant(s)'.$agesStr.' who rely on their income.'
                : 'No dependants recorded for '.$firstName.'.',
        ];

        if ($dependants <= 0) {
            return null;
        }

        // Step 3: Life cover check with policy details
        $currentCoverage = $comprehensivePlan['current_coverage'] ?? [];
        $lifeCoverage = (float) ($currentCoverage['life_insurance']['total_coverage'] ?? 0);

        $lifePolicySummary = 'None';
        if ($userId) {
            $lifePolicies = LifeInsurancePolicy::where('user_id', $userId)->whereNull('deleted_at')->get();
            $lifePolicySummary = $lifePolicies->isEmpty() ? 'None' : $this->formatLifePolicySummary($lifePolicies);
        }

        // Also check death in service
        $disMultiple = $this->getProfileValue($comprehensivePlan, 'death_in_service_multiple');
        $financialSummary = $comprehensivePlan['financial_summary'] ?? [];
        $salary = (float) ($financialSummary['income_breakdown']['employment_income'] ?? 0);
        $disAmount = ($disMultiple !== null && $disMultiple > 0 && $salary > 0) ? $disMultiple * $salary : 0;
        $disStr = $disAmount > 0 ? '. Death in service: £'.number_format($disAmount, 0).' ('.$disMultiple.'× salary)' : '';

        $trace[] = [
            'question' => 'Does '.$firstName.' have any life insurance cover?',
            'data_field' => 'Total life insurance coverage',
            'data_value' => '£'.number_format($lifeCoverage, 0).'. Policies: '.$lifePolicySummary.$disStr,
            'threshold' => 'Greater than £0 (has '.$dependants.' dependant(s))',
            'passed' => $lifeCoverage > 0,
            'explanation' => $lifeCoverage > 0
                ? $firstName.' has £'.number_format($lifeCoverage, 0).' of life insurance cover.'
                : $firstName.' has no life insurance cover. With '.$dependants.' dependant(s)'.$agesStr.', life insurance is essential to protect their financial security if something were to happen to '.$firstName.'.',
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
        $user = $this->resolveUser($comprehensivePlan);
        $firstName = $user->first_name ?? 'The user';

        // Step 1: User context
        $trace[] = $this->buildUserContextTrace($comprehensivePlan);

        $userProfile = $comprehensivePlan['user_profile'] ?? [];
        $dependants = (int) ($userProfile['number_of_dependents'] ?? 0);
        $dependantsAges = $userProfile['dependents_ages'] ?? [];
        $agesStr = ! empty($dependantsAges) ? ' (ages: '.implode(', ', $dependantsAges).')' : '';

        $protectionNeeds = $comprehensivePlan['protection_needs'] ?? [];
        $breakdown = $protectionNeeds['breakdown'] ?? [];
        $educationFunding = (float) ($breakdown['education_funding'] ?? 0);

        // Step 2: Education funding need
        $trace[] = [
            'question' => 'Does '.$firstName.' have an education funding need for dependants?',
            'data_field' => 'Education funding requirement',
            'data_value' => '£'.number_format($educationFunding, 0).' for '.$dependants.' dependant(s)'.$agesStr,
            'threshold' => 'Greater than £0',
            'passed' => $educationFunding <= 0,
            'explanation' => $educationFunding > 0
                ? $firstName.' has an education funding requirement of £'.number_format($educationFunding, 0).' for '.$dependants.' dependant(s)'.$agesStr.'.'
                : 'No education funding need identified for '.$firstName.'.',
        ];

        if ($educationFunding <= 0) {
            return null;
        }

        // Step 3: Life insurance gap check
        $coverageAnalysis = $comprehensivePlan['coverage_analysis'] ?? [];
        $lifeGap = (float) ($coverageAnalysis['life_insurance']['gap'] ?? 0);
        $lifeNeed = (float) ($coverageAnalysis['life_insurance']['need'] ?? 0);
        $lifeCoverage = (float) ($coverageAnalysis['life_insurance']['coverage'] ?? 0);

        $trace[] = [
            'question' => 'Is '.$firstName.'\'s life insurance sufficient to cover the education funding need?',
            'data_field' => 'Life insurance gap',
            'data_value' => '£'.number_format($lifeNeed, 0).' need - £'.number_format($lifeCoverage, 0).' cover = £'.number_format($lifeGap, 0).' gap',
            'threshold' => '£0 (no gap)',
            'passed' => $lifeGap <= 0,
            'explanation' => $lifeGap > 0
                ? $firstName.'\'s life insurance has a shortfall of £'.number_format($lifeGap, 0).' (need: £'.number_format($lifeNeed, 0).' vs cover: £'.number_format($lifeCoverage, 0).'), which puts the education funding at risk.'
                : $firstName.'\'s life insurance fully covers all needs, including education funding.',
        ];

        if ($lifeGap <= 0) {
            return null;
        }

        // Step 4: Education funding at risk
        $educationGap = min($educationFunding, $lifeGap);

        $trace[] = [
            'question' => 'How much of the education funding is at risk?',
            'data_field' => 'Education funding gap',
            'data_value' => 'min(£'.number_format($educationFunding, 0).' education need, £'.number_format($lifeGap, 0).' life gap) = £'.number_format($educationGap, 0),
            'threshold' => '£0 (fully covered)',
            'passed' => false,
            'explanation' => '£'.number_format($educationGap, 0).' of '.$firstName.'\'s education funding need is at risk due to insufficient life cover. If '.$firstName.' were to pass away, the shortfall could prevent dependants from completing their education.',
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
        $user = $this->resolveUser($comprehensivePlan);
        $firstName = $user->first_name ?? 'The user';

        // Step 1: User context
        $trace[] = $this->buildUserContextTrace($comprehensivePlan);

        $userProfile = $comprehensivePlan['user_profile'] ?? [];
        $maritalStatus = strtolower((string) ($userProfile['marital_status'] ?? ''));
        $dependants = (int) ($userProfile['number_of_dependents'] ?? 0);
        $dependantsAges = $userProfile['dependents_ages'] ?? [];
        $agesStr = ! empty($dependantsAges) ? ' (ages: '.implode(', ', $dependantsAges).')' : '';

        $isMarriedOrCp = in_array($maritalStatus, ['married', 'civil partnership']);

        // Step 2: Marital status check
        $trace[] = [
            'question' => 'Is '.$firstName.' married or in a civil partnership?',
            'data_field' => 'Marital status',
            'data_value' => ucfirst($maritalStatus ?: 'Not recorded'),
            'threshold' => 'Married or civil partnership',
            'passed' => ! $isMarriedOrCp,
            'explanation' => $isMarriedOrCp
                ? $firstName.' is '.$maritalStatus.'.'
                : 'This check only applies to those who are married or in a civil partnership.',
        ];

        if (! $isMarriedOrCp) {
            return null;
        }

        // Step 3: Dependants check
        $trace[] = [
            'question' => 'Does '.$firstName.' have dependants?',
            'data_field' => 'Number of dependants',
            'data_value' => $dependants.' dependant(s)'.$agesStr,
            'threshold' => 'At least 1 dependant',
            'passed' => $dependants <= 0,
            'explanation' => $dependants > 0
                ? $firstName.' has '.$dependants.' dependant(s)'.$agesStr.'.'
                : 'No dependants recorded for '.$firstName.'. This check requires dependants.',
        ];

        if ($dependants <= 0) {
            return null;
        }

        // Step 4: Spouse income check
        $protectionNeeds = $comprehensivePlan['protection_needs'] ?? [];
        $spouseInfo = $protectionNeeds['spouse_info'] ?? [];
        $spouseGrossIncome = (float) ($spouseInfo['spouse_gross_income'] ?? 0);
        $spouseIncluded = (bool) ($spouseInfo['spouse_included'] ?? false);

        if (! $spouseIncluded) {
            return null;
        }

        $trace[] = [
            'question' => 'Does '.$firstName.'\'s spouse or partner have their own earned income?',
            'data_field' => 'Spouse gross income',
            'data_value' => '£'.number_format($spouseGrossIncome, 0).' per year',
            'threshold' => 'Greater than £0',
            'passed' => $spouseGrossIncome > 0,
            'explanation' => $spouseGrossIncome > 0
                ? $firstName.'\'s spouse earns £'.number_format($spouseGrossIncome, 0).' per year.'
                : $firstName.'\'s spouse has no earned income. With '.$dependants.' dependant(s)'.$agesStr.', if the spouse were unable to fulfil their role due to illness or death, the cost of replacing their contribution (childcare, household management) could be significant.',
        ];

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

    /**
     * Build a user profile context trace entry with real field data from the User model.
     */
    private function buildUserContextTrace(array $comprehensivePlan): array
    {
        $userId = $this->extractUserId($comprehensivePlan);
        $user = $userId ? User::find($userId) : null;

        if (! $user) {
            $userProfile = $comprehensivePlan['user_profile'] ?? [];
            $name = $userProfile['name'] ?? 'Unknown';
            $age = $userProfile['age'] ?? 'unknown';
            $maritalStatus = $userProfile['marital_status'] ?? 'not specified';

            return [
                'question' => 'What is the user\'s profile?',
                'data_field' => 'User profile',
                'data_value' => $name.', age '.$age.', '.$maritalStatus,
                'threshold' => 'Required for protection analysis',
                'passed' => true,
                'explanation' => 'User profile data gathered for protection gap analysis.',
            ];
        }

        $userName = trim(($user->first_name ?? '').' '.($user->surname ?? '')) ?: 'Unknown';
        $age = $user->date_of_birth ? Carbon::parse($user->date_of_birth)->age : 'unknown';
        $employmentStatus = $user->employment_status ?? 'not specified';
        $income = (float) ($user->annual_employment_income ?? 0);
        $selfEmploymentIncome = (float) ($user->annual_self_employment_income ?? 0);
        $maritalStatus = $user->marital_status ?? 'not specified';

        $incomeStr = '£'.number_format($income, 0).' employment';
        if ($selfEmploymentIncome > 0) {
            $incomeStr .= ', £'.number_format($selfEmploymentIncome, 0).' self-employment';
        }

        return [
            'question' => 'What is the user\'s profile?',
            'data_field' => 'User profile',
            'data_value' => $userName.', age '.$age.', '.$employmentStatus.', income '.$incomeStr.', '.($maritalStatus ?: 'marital status not specified'),
            'threshold' => 'Required for protection analysis',
            'passed' => true,
            'explanation' => $userName.'\'s profile data gathered for protection gap analysis. Date of birth: '.($user->date_of_birth ? Carbon::parse($user->date_of_birth)->format('d/m/Y') : 'not provided').', employment status: '.$employmentStatus.'.',
        ];
    }

    /**
     * Retrieve the User model from the comprehensive plan data.
     */
    private function resolveUser(array $comprehensivePlan): ?User
    {
        $userId = $this->extractUserId($comprehensivePlan);

        return $userId ? User::find($userId) : null;
    }

    /**
     * Format a list of life insurance policies into a readable summary string.
     */
    private function formatLifePolicySummary(\Illuminate\Support\Collection $policies): string
    {
        if ($policies->isEmpty()) {
            return 'None';
        }

        return $policies->map(function ($p) {
            $parts = ($p->provider ?? 'Unknown provider');
            $parts .= ' — £'.number_format((float) $p->sum_assured, 0);
            if ($p->premium_amount) {
                $parts .= ', £'.number_format((float) $p->premium_amount, 2).'/'.($p->premium_frequency ?? 'month');
            }
            if ($p->policy_type) {
                $parts .= ', '.ucfirst(str_replace('_', ' ', $p->policy_type));
            }

            return $parts;
        })->implode('; ');
    }

    /**
     * Format a list of income protection policies into a readable summary string.
     */
    private function formatIpPolicySummary(\Illuminate\Support\Collection $policies): string
    {
        if ($policies->isEmpty()) {
            return 'None';
        }

        return $policies->map(function ($p) {
            $parts = ($p->provider ?? 'Unknown provider');
            $parts .= ' — £'.number_format((float) $p->benefit_amount, 2).'/'.($p->benefit_frequency ?? 'month');
            if ($p->deferred_period_weeks) {
                $parts .= ', '.$p->deferred_period_weeks.'-week deferred';
            }
            if ($p->occupation_class) {
                $parts .= ', '.ucfirst($p->occupation_class).' occupation';
            }

            return $parts;
        })->implode('; ');
    }

    /**
     * Format a list of critical illness policies into a readable summary string.
     */
    private function formatCiPolicySummary(\Illuminate\Support\Collection $policies): string
    {
        if ($policies->isEmpty()) {
            return 'None';
        }

        return $policies->map(function ($p) {
            $parts = ($p->provider ?? 'Unknown provider');
            $parts .= ' — £'.number_format((float) $p->sum_assured, 0);
            if ($p->premium_amount) {
                $parts .= ', £'.number_format((float) $p->premium_amount, 2).'/'.($p->premium_frequency ?? 'month');
            }
            if ($p->policy_type) {
                $parts .= ', '.ucfirst(str_replace('_', ' ', $p->policy_type));
            }

            return $parts;
        })->implode('; ');
    }

    // =============================================
    // Premium Affordability Evaluators
    // =============================================

    /**
     * Premium affordability: triggers when total annual premiums exceed a percentage of gross income.
     */
    private function evaluatePremiumAffordability(ProtectionActionDefinition $definition, array $comprehensivePlan): ?array
    {
        $threshold = (float) ($definition->trigger_config['threshold'] ?? 0.05);
        $userId = $comprehensivePlan['user_id'] ?? null;

        if (! $userId) {
            return null;
        }

        $profile = ProtectionProfile::where('user_id', $userId)->first();
        if (! $profile || ! $profile->annual_income || $profile->annual_income <= 0) {
            return null;
        }

        $annualIncome = (float) $profile->annual_income;

        // Calculate total annual premiums across all policy types
        $totalAnnualPremiums = 0.0;

        $lifePolicies = LifeInsurancePolicy::where('user_id', $userId)->get();
        foreach ($lifePolicies as $policy) {
            $totalAnnualPremiums += $this->convertToAnnualPremium(
                (float) ($policy->premium_amount ?? 0),
                $policy->premium_frequency ?? 'monthly'
            );
        }

        $ciPolicies = CriticalIllnessPolicy::where('user_id', $userId)->get();
        foreach ($ciPolicies as $policy) {
            $totalAnnualPremiums += $this->convertToAnnualPremium(
                (float) ($policy->premium_amount ?? 0),
                $policy->premium_frequency ?? 'monthly'
            );
        }

        $ipPolicies = IncomeProtectionPolicy::where('user_id', $userId)->get();
        foreach ($ipPolicies as $policy) {
            $totalAnnualPremiums += $this->convertToAnnualPremium(
                (float) ($policy->premium_amount ?? 0),
                $policy->premium_frequency ?? 'monthly'
            );
        }

        if ($totalAnnualPremiums <= 0) {
            return null;
        }

        $premiumPercent = $totalAnnualPremiums / $annualIncome;

        if ($premiumPercent <= $threshold) {
            return null;
        }

        $vars = [
            'annual_premiums' => '£'.number_format($totalAnnualPremiums, 0),
            'premium_percent' => number_format($premiumPercent * 100, 1),
        ];

        $trace = [[
            'question' => 'Do total protection premiums exceed '.number_format($threshold * 100, 0).'% of gross income?',
            'data_field' => 'premium_percent_of_income',
            'data_value' => number_format($premiumPercent * 100, 1).'% (£'.number_format($totalAnnualPremiums, 0).' on £'.number_format($annualIncome, 0).' income)',
            'threshold' => number_format($threshold * 100, 0).'% of gross income',
            'passed' => false,
            'explanation' => 'Total annual premiums of £'.number_format($totalAnnualPremiums, 0).' represent '.number_format($premiumPercent * 100, 1).'% of gross income (£'.number_format($annualIncome, 0).'), exceeding the '.number_format($threshold * 100, 0).'% threshold.',
        ]];

        return [
            'priority' => $this->getPriorityValue($definition->priority),
            'category' => $definition->category,
            'title' => $definition->renderTitle($vars),
            'description' => $definition->renderDescription($vars),
            'action' => $definition->renderAction($vars),
            'impact' => ucfirst($definition->priority),
            'scope' => $definition->scope,
            'decision_trace' => $trace,
        ];
    }

    private function convertToAnnualPremium(float $amount, string $frequency): float
    {
        return match ($frequency) {
            'monthly' => $amount * 12,
            'quarterly' => $amount * 4,
            'annually' => $amount,
            default => $amount * 12,
        };
    }

    private function getPriorityValue(string $priority): int
    {
        return match ($priority) {
            'critical' => 1,
            'high' => 2,
            'medium' => 3,
            'low' => 4,
            default => 5,
        };
    }
}
