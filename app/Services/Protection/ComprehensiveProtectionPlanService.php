<?php

declare(strict_types=1);

namespace App\Services\Protection;

use App\Agents\ProtectionAgent;
use App\Models\ProtectionProfile;
use App\Models\User;
use App\Traits\FormatsCurrency;

/**
 * Generates a comprehensive protection plan combining:
 * - User profile and financial situation
 * - Current protection coverage (Life, CI, IP policies)
 * - Protection needs analysis
 * - Coverage gaps and recommendations
 * - Scenario analysis (death, critical illness, disability)
 * - Optimized protection strategy
 */
class ComprehensiveProtectionPlanService
{
    use FormatsCurrency;

    public function __construct(
        private ProtectionAgent $protectionAgent,
        private CoverageGapAnalyzer $gapAnalyzer,
        private AdequacyScorer $adequacyScorer,
        private RecommendationEngine $recommendationEngine
    ) {}

    /**
     * Generate comprehensive protection plan
     */
    public function generateComprehensiveProtectionPlan(User $user): array
    {
        // Get protection analysis from agent
        $analysis = $this->protectionAgent->analyze($user->id);

        if (! $analysis['success']) {
            throw new \Exception($analysis['message']);
        }

        $data = $analysis['data'];
        $profile = ProtectionProfile::where('user_id', $user->id)->first();

        if (! $profile) {
            throw new \Exception('Protection profile not found');
        }

        // Extract profile completeness data
        $profileCompleteness = $data['profile_completeness'] ?? null;
        $completenessScore = $profileCompleteness['completeness_score'] ?? 100;
        $isComplete = $profileCompleteness['is_complete'] ?? true;

        return [
            'plan_metadata' => [
                'generated_date' => now()->format('d F Y'),
                'generated_time' => now()->format('H:i'),
                'plan_version' => 'v1.0',
                'user_name' => $user->name,
                'completeness_score' => $completenessScore,
                'is_complete' => $isComplete,
                'plan_type' => $isComplete ? 'Personalised' : 'Generic',
            ],
            'completeness_warning' => $this->generateCompletenessWarning($profileCompleteness),
            'executive_summary' => $this->generateExecutiveSummary($data, $profile, $profileCompleteness),
            'user_profile' => $this->buildUserProfile($user, $profile),
            'financial_summary' => $this->buildFinancialSummary($user, $data),
            'current_coverage' => $this->buildCurrentCoverage($data),
            'protection_needs' => $this->buildProtectionNeeds($data),
            'coverage_analysis' => $this->buildCoverageAnalysis($data),
            'recommendations' => $this->buildRecommendations($data, $profileCompleteness),
            'scenario_analysis' => $this->buildScenarioAnalysis($data),
            'optimized_strategy' => $this->generateOptimizedStrategy($data, $profile),
            'implementation_timeline' => $this->buildImplementationTimeline($data),
            'next_steps' => $this->generateNextSteps($data, $profileCompleteness),
        ];
    }

    /**
     * Generate completeness warning
     */
    private function generateCompletenessWarning(?array $profileCompleteness): ?array
    {
        if (! $profileCompleteness || $profileCompleteness['is_complete']) {
            return null;
        }

        $score = $profileCompleteness['completeness_score'];
        $missingFields = $profileCompleteness['missing_fields'] ?? [];

        // Determine severity
        $severity = match (true) {
            $score < 50 => 'critical',
            $score < 100 => 'warning',
            default => 'success',
        };

        // Build disclaimer text
        $disclaimer = match ($severity) {
            'critical' => 'This protection plan is highly generic due to incomplete profile information. Key data is missing, which significantly limits the accuracy and personalization of recommendations. Please complete your profile to receive a comprehensive and tailored protection strategy.',
            'warning' => 'This protection plan is partially generic as some profile information is incomplete. Completing the missing fields will enable more accurate calculations and personalized recommendations.',
            default => 'Your profile is complete. This protection plan is fully personalized based on your circumstances.',
        };

        // Extract top priority missing fields
        $topMissingFields = [];
        foreach ($missingFields as $key => $field) {
            if ($field['priority'] === 'high' && $field['required']) {
                $topMissingFields[] = [
                    'field' => $key,
                    'message' => $field['message'],
                    'link' => $field['link'],
                ];
            }
        }

        return [
            'score' => $score,
            'severity' => $severity,
            'disclaimer' => $disclaimer,
            'missing_fields' => $topMissingFields,
            'recommendations' => $profileCompleteness['recommendations'] ?? [],
        ];
    }

    /**
     * Generate executive summary
     */
    private function generateExecutiveSummary(array $data, ProtectionProfile $profile, ?array $profileCompleteness): array
    {
        $adequacyScore = $data['adequacy_score'];
        $gaps = $data['gaps'];

        $criticalGaps = [];
        $totalGap = $gaps['total_gap'] ?? 0;
        $gapsByCategory = $gaps['gaps_by_category'] ?? [];

        if ($totalGap > 0) {
            $criticalGaps[] = 'Total Protection Gap: '.$this->formatCurrency($totalGap);
        }

        if (($gapsByCategory['human_capital_gap'] ?? 0) > 0) {
            $criticalGaps[] = 'Income Replacement Need: '.$this->formatCurrency($gapsByCategory['human_capital_gap']);
        }

        if (($gapsByCategory['debt_protection_gap'] ?? 0) > 0) {
            $criticalGaps[] = 'Debt Protection Need: '.$this->formatCurrency($gapsByCategory['debt_protection_gap']);
        }

        return [
            'title' => 'Comprehensive Protection Plan',
            'adequacy_rating' => [
                'overall' => $adequacyScore['rating'] ?? 'N/A',
                'life' => $this->getCoverageStatus($adequacyScore['life_insurance_score'] ?? 0),
                'critical_illness' => $this->getCoverageStatus($adequacyScore['critical_illness_score'] ?? 0),
                'income_protection' => $this->getCoverageStatus($adequacyScore['income_protection_score'] ?? 0),
            ],
            'critical_gaps' => $criticalGaps,
            'total_gap_amount' => $totalGap,
            'monthly_income_gap' => ($gapsByCategory['income_protection_gap'] ?? 0) / 12,
            'recommended_action' => $this->getRecommendedAction(
                $adequacyScore['rating'] ?? 'N/A',
                $this->getCoverageStatus($adequacyScore['life_insurance_score'] ?? 0),
                $this->getCoverageStatus($adequacyScore['critical_illness_score'] ?? 0),
                $this->getCoverageStatus($adequacyScore['income_protection_score'] ?? 0),
                ($profile->number_of_dependents ?? 0) > 0
            ),
        ];
    }

    /**
     * Build user profile
     */
    private function buildUserProfile(User $user, ProtectionProfile $profile): array
    {
        $maritalStatus = is_string($user->marital_status) ? $user->marital_status : 'single';

        // Calculate age from date_of_birth
        $age = 'Not provided';
        if ($user->date_of_birth) {
            $age = \Carbon\Carbon::parse($user->date_of_birth)->age;
        }

        // Determine smoker status - check user table first, fallback to profile
        $smokerStatus = isset($user->smoker) ? ($user->smoker ? 'Smoker' : 'Non-smoker') : ($profile->smoker_status ? 'Smoker' : 'Non-smoker');

        // Determine health status - check user table first, fallback to profile
        $healthStatus = 'Good'; // Default
        if (isset($user->good_health)) {
            $healthStatus = $user->good_health ? 'Good' : 'Pre-existing conditions';
        } elseif (isset($profile->health_status)) {
            $healthStatus = ucfirst($profile->health_status);
        }

        // Format education level for display
        $educationLevel = 'Not specified';
        if ($user->education_level) {
            $educationLevel = match ($user->education_level) {
                'secondary' => 'Secondary (GCSE/O-Levels)',
                'a_level' => 'A-Levels/Vocational',
                'undergraduate' => 'Undergraduate Degree',
                'postgraduate' => 'Postgraduate Degree',
                'professional' => 'Professional Qualification',
                'other' => 'Other',
                default => 'Not specified',
            };
        }

        return [
            'name' => $user->name,
            'email' => $user->email,
            'date_of_birth' => $user->date_of_birth ? \Carbon\Carbon::parse($user->date_of_birth)->format('d/m/Y') : 'Not provided',
            'age' => $age,
            'gender' => ucfirst($user->gender ?? 'Not specified'),
            'marital_status' => ucfirst(str_replace('_', ' ', $maritalStatus)),
            'occupation' => $user->occupation ?? 'Not specified',
            'education_level' => $educationLevel,
            'smoker_status' => $smokerStatus,
            'health_status' => $healthStatus,
            'number_of_dependents' => \App\Models\FamilyMember::where('user_id', $user->id)->where('is_dependent', true)->count(),
            'dependents_ages' => $profile->dependents_ages ?? [],
            'retirement_age' => $profile->retirement_age ?? 65,
            'death_in_service_multiple' => $profile->death_in_service_multiple,
            'group_ip_benefit_percent' => $profile->group_ip_benefit_percent,
            'group_ip_benefit_months' => $profile->group_ip_benefit_months,
            'group_ip_definition' => $profile->group_ip_definition,
            'group_ci_amount' => $profile->group_ci_amount,
            'has_employer_pmi' => (bool) ($profile->has_employer_pmi ?? false),
            'employer_name' => $profile->employer_name,
        ];
    }

    /**
     * Build financial summary
     */
    private function buildFinancialSummary(User $user, array $data): array
    {
        $totalAnnualIncome = $data['profile']['total_annual_income'];
        $monthlyIncome = $totalAnnualIncome / 12;

        // Build income breakdown from user's actual income fields
        $incomeBreakdown = [
            'employment_income' => (float) ($user->annual_employment_income ?? 0),
            'self_employment_income' => (float) ($user->annual_self_employment_income ?? 0),
            'rental_income' => (float) ($user->annual_rental_income ?? 0),
            'dividend_income' => (float) ($user->annual_dividend_income ?? 0),
            'other_income' => (float) ($user->annual_other_income ?? 0),
        ];

        // Get expenditure from user table (annual) and calculate monthly
        $annualExpenditure = (float) ($user->annual_expenditure ?? 0);
        $monthlyExpenditure = $annualExpenditure / 12;

        return [
            'total_annual_income' => $totalAnnualIncome,
            'annual_income' => $totalAnnualIncome, // Alias for frontend compatibility
            'monthly_income' => $monthlyIncome,
            'income_breakdown' => $incomeBreakdown,
            'monthly_expenditure' => $monthlyExpenditure,
            'annual_expenditure' => $annualExpenditure,
            'debt_breakdown' => $data['debt_breakdown'],
            'total_debt' => $data['debt_breakdown']['total'],
        ];
    }

    /**
     * Build current coverage summary
     */
    private function buildCurrentCoverage(array $data): array
    {
        $coverage = $data['coverage'];
        $policies = $data['policies'] ?? [];

        $lifePolicies = [];
        foreach (collect($policies['life_insurance'] ?? []) as $policy) {
            $policyType = is_string($policy['policy_type'] ?? '') ? $policy['policy_type'] : 'standard';
            $lifePolicies[] = [
                'provider' => $policy['provider'] ?? 'Not specified',
                'type' => ucfirst(str_replace('_', ' ', $policyType)),
                'sum_assured' => $policy['sum_assured'] ?? 0,
                'annual_premium' => $this->convertToAnnualPremium(
                    $policy['premium_amount'] ?? 0,
                    $policy['premium_frequency'] ?? 'monthly'
                ),
            ];
        }

        $ciPolicies = [];
        foreach (collect($policies['critical_illness'] ?? []) as $policy) {
            $policyType = is_string($policy['policy_type'] ?? '') ? $policy['policy_type'] : 'standard';
            $ciPolicies[] = [
                'provider' => $policy['provider'] ?? 'Not specified',
                'type' => ucfirst(str_replace('_', ' ', $policyType)),
                'sum_assured' => $policy['sum_assured'] ?? 0,
                'annual_premium' => $this->convertToAnnualPremium(
                    $policy['premium_amount'] ?? 0,
                    $policy['premium_frequency'] ?? 'monthly'
                ),
            ];
        }

        $ipPolicies = [];
        foreach (collect($policies['income_protection'] ?? []) as $policy) {
            $ipPolicies[] = [
                'provider' => $policy['provider'] ?? 'Not specified',
                'benefit_amount' => $policy['benefit_amount'] ?? 0,
                'benefit_frequency' => ucfirst($policy['benefit_frequency'] ?? 'monthly'),
                'deferred_period_weeks' => $policy['deferred_period_weeks'] ?? 0,
            ];
        }

        $totalAnnualPremiums = 0;
        foreach (array_merge($lifePolicies, $ciPolicies) as $policy) {
            $totalAnnualPremiums += $policy['annual_premium'] ?? 0;
        }
        // Note: IP policies don't have premium info in the current data structure

        return [
            'life_insurance' => [
                'total_coverage' => $coverage['life_coverage'] ?? 0,
                'policies' => $lifePolicies,
                'policy_count' => count($lifePolicies),
            ],
            'critical_illness' => [
                'total_coverage' => $coverage['critical_illness_coverage'] ?? 0,
                'policies' => $ciPolicies,
                'policy_count' => count($ciPolicies),
            ],
            'income_protection' => [
                'monthly_benefit' => ($coverage['income_protection_coverage'] ?? 0) / 12, // Convert annual to monthly
                'policies' => $ipPolicies,
                'policy_count' => count($ipPolicies),
            ],
            'total_annual_premiums' => $totalAnnualPremiums,
            'total_monthly_premiums' => $totalAnnualPremiums / 12,
        ];
    }

    /**
     * Build protection needs
     */
    private function buildProtectionNeeds(array $data): array
    {
        $needs = $data['needs'];

        return [
            'total_need' => $needs['total_need'] ?? 0,
            'breakdown' => [
                'human_capital' => $needs['human_capital'] ?? 0,
                'debt_protection' => $needs['debt_protection'] ?? 0,
                'education_funding' => $needs['education_funding'] ?? 0,
                'final_expenses' => $needs['final_expenses'] ?? 0,
            ],
            'income_analysis' => [
                'gross_income' => $needs['gross_income'] ?? 0,
                'net_income' => $needs['net_income'] ?? 0,
                'continuing_income' => $needs['continuing_income'] ?? 0,
                'income_that_stops' => $needs['income_that_stops'] ?? 0,
                'income_that_continues' => $needs['income_that_continues'] ?? 0,
                'net_income_difference' => $needs['net_income_difference'] ?? 0,
            ],
            'spouse_info' => [
                'spouse_included' => $needs['spouse_included'] ?? false,
                'spouse_gross_income' => $needs['spouse_gross_income'] ?? 0,
                'spouse_net_income' => $needs['spouse_net_income'] ?? 0,
                'spouse_continuing_income' => $needs['spouse_continuing_income'] ?? 0,
            ],
            'state_benefits' => $needs['state_benefits'] ?? [],
        ];
    }

    /**
     * Build coverage analysis
     */
    private function buildCoverageAnalysis(array $data): array
    {
        $needs = $data['needs'] ?? [];
        $coverage = $data['coverage'] ?? [];
        $gaps = $data['gaps'] ?? [];
        $adequacyScore = $data['adequacy_score'] ?? [];

        // Life insurance uses total need vs total coverage
        $lifeCoverage = $coverage['life_coverage'] ?? 0;
        $lifeNeed = $needs['total_need'] ?? 0;
        $lifeGap = $gaps['total_gap'] ?? 0;

        // Critical illness - use a simple 3x annual income estimate
        $annualIncome = $needs['gross_income'] ?? 0;
        $ciNeed = $annualIncome * 3;
        $ciCoverage = $coverage['critical_illness_coverage'] ?? 0;
        $ciGap = max(0, $ciNeed - $ciCoverage);

        // Income protection - monthly benefit needed (70% of net income)
        $monthlyNetIncome = ($needs['net_income'] ?? 0) / 12;
        $ipMonthlyNeed = $monthlyNetIncome * 0.7;
        $ipMonthlyCoverage = ($coverage['income_protection_coverage'] ?? 0) / 12;
        $ipMonthlyGap = max(0, $ipMonthlyNeed - $ipMonthlyCoverage);

        return [
            'life_insurance' => [
                'need' => $lifeNeed,
                'coverage' => $lifeCoverage,
                'gap' => $lifeGap,
                'coverage_percentage' => $lifeNeed > 0 ?
                    round(($lifeCoverage / $lifeNeed) * 100, 1) : 100,
                'status' => $this->getCoverageStatus($adequacyScore['life_insurance_score'] ?? 0),
            ],
            'critical_illness' => [
                'need' => $ciNeed,
                'coverage' => $ciCoverage,
                'gap' => $ciGap,
                'coverage_percentage' => $ciNeed > 0 ?
                    round(($ciCoverage / $ciNeed) * 100, 1) : 100,
                'status' => $this->getCoverageStatus($adequacyScore['critical_illness_score'] ?? 0),
            ],
            'income_protection' => [
                'need' => $ipMonthlyNeed,
                'coverage' => $ipMonthlyCoverage,
                'gap' => $ipMonthlyGap,
                'coverage_percentage' => $ipMonthlyNeed > 0 ?
                    round(($ipMonthlyCoverage / $ipMonthlyNeed) * 100, 1) : 100,
                'status' => $this->getCoverageStatus($adequacyScore['income_protection_score'] ?? 0),
            ],
            'overall_rating' => $adequacyScore['rating'] ?? 'N/A',
        ];
    }

    /**
     * Build recommendations with plan type indicators
     */
    private function buildRecommendations(array $data, ?array $profileCompleteness): array
    {
        $recommendations = $data['recommendations'];
        $isComplete = $profileCompleteness['is_complete'] ?? true;
        $completenessScore = $profileCompleteness['completeness_score'] ?? 100;

        // Determine plan type for recommendations
        $planType = match (true) {
            $completenessScore >= 100 => 'Personalised',
            $completenessScore >= 70 => 'Mostly Personalised',
            $completenessScore >= 50 => 'Partially Generic',
            default => 'Generic',
        };

        // Add plan type metadata to recommendations
        $enhancedRecommendations = [
            'plan_type' => $planType,
            'is_complete' => $isComplete,
            'completeness_score' => $completenessScore,
            'disclaimer' => ! $isComplete
                ? 'Some recommendations are generic due to incomplete profile information. Complete your profile for more personalized advice.'
                : null,
            'categories' => $recommendations,
        ];

        return $enhancedRecommendations;
    }

    /**
     * Build scenario analysis
     */
    private function buildScenarioAnalysis(array $data): array
    {
        return $data['scenarios'];
    }

    /**
     * Generate optimized protection strategy
     */
    private function generateOptimizedStrategy(array $data, ProtectionProfile $profile): array
    {
        $gaps = $data['gaps'] ?? [];
        $gapsByCategory = $gaps['gaps_by_category'] ?? [];
        $needs = $data['needs'] ?? [];
        $coverage = $data['coverage'] ?? [];
        $recommendations = $data['recommendations'] ?? [];

        $strategyRecommendations = [];
        $totalEstimatedCost = 0;
        $totalCoverageIncrease = 0;

        $totalGap = $gaps['total_gap'] ?? 0;

        // Priority 1: Life Insurance (if gap exists and user has dependants)
        $hasDependants = ($profile->number_of_dependents ?? 0) > 0;
        if ($totalGap > 10000 && $hasDependants) {
            $estimatedMonthlyPremium = $this->estimateLifePremium(
                $totalGap,
                $profile->age ?? 40,
                $profile->smoker_status
            );

            $strategyRecommendations[] = [
                'priority' => 1,
                'category' => 'Life Insurance',
                'action' => 'Increase Life Insurance Coverage',
                'details' => 'Add £'.number_format($totalGap, 0).' life insurance coverage to protect your dependants',
                'coverage_amount' => $totalGap,
                'estimated_monthly_cost' => $estimatedMonthlyPremium,
                'timeframe' => 'Immediate',
                'importance' => 'Critical',
            ];

            $totalEstimatedCost += $estimatedMonthlyPremium;
            $totalCoverageIncrease += $totalGap;
        }

        // Priority 2: Critical Illness (if gap exists)
        $annualIncome = $needs['gross_income'] ?? 0;
        $ciNeed = $annualIncome * 3;
        $ciCoverage = $coverage['critical_illness_coverage'] ?? 0;
        $ciGap = max(0, $ciNeed - $ciCoverage);

        if ($ciGap > 10000) {
            $estimatedMonthlyPremium = $this->estimateCIPremium(
                $ciGap,
                $profile->age ?? 40,
                $profile->smoker_status
            );

            $strategyRecommendations[] = [
                'priority' => 2,
                'category' => 'Critical Illness',
                'action' => 'Add Critical Illness Coverage',
                'details' => 'Add £'.number_format($ciGap, 0).' critical illness coverage for financial security',
                'coverage_amount' => $ciGap,
                'estimated_monthly_cost' => $estimatedMonthlyPremium,
                'timeframe' => 'Immediate',
                'importance' => 'High',
            ];

            $totalEstimatedCost += $estimatedMonthlyPremium;
            $totalCoverageIncrease += $ciGap;
        }

        // Priority 3: Income Protection (if gap exists)
        $monthlyNetIncome = ($needs['net_income'] ?? 0) / 12;
        $ipMonthlyNeed = $monthlyNetIncome * 0.7;
        $ipMonthlyCoverage = ($coverage['income_protection_coverage'] ?? 0) / 12;
        $ipMonthlyGap = max(0, $ipMonthlyNeed - $ipMonthlyCoverage);

        if ($ipMonthlyGap > 100) {
            $estimatedMonthlyPremium = $this->estimateIPPremium(
                $ipMonthlyGap,
                $profile->age ?? 40
            );

            $strategyRecommendations[] = [
                'priority' => 3,
                'category' => 'Income Protection',
                'action' => 'Add Income Protection Coverage',
                'details' => 'Add £'.number_format($ipMonthlyGap, 0).'/month income protection for long-term disability',
                'monthly_benefit' => $ipMonthlyGap,
                'estimated_monthly_cost' => $estimatedMonthlyPremium,
                'timeframe' => 'Within 3 months',
                'importance' => 'Medium',
            ];

            $totalEstimatedCost += $estimatedMonthlyPremium;
        }

        return [
            'strategy_name' => 'Optimised Protection Strategy',
            'recommendations' => $strategyRecommendations,
            'summary' => [
                'total_coverage_increase' => $totalCoverageIncrease,
                'total_estimated_monthly_cost' => $totalEstimatedCost,
                'total_estimated_annual_cost' => $totalEstimatedCost * 12,
                'affordability_percentage' => $profile->monthly_expenditure > 0 ?
                    round(($totalEstimatedCost / ($profile->monthly_expenditure * 0.1)) * 100, 1) : 0,
            ],
        ];
    }

    /**
     * Build implementation timeline
     */
    private function buildImplementationTimeline(array $data): array
    {
        $recommendations = $data['recommendations'];
        $timeline = [];

        $priority = 1;
        foreach ($recommendations as $category => $recs) {
            if (is_array($recs)) {
                foreach ($recs as $rec) {
                    $categoryName = is_string($category) ? str_replace('_', ' ', $category) : 'Protection';
                    $timeline[] = [
                        'priority' => $priority++,
                        'category' => ucfirst($categoryName),
                        'action' => is_string($rec) ? $rec : ($rec['recommendation'] ?? 'Review coverage'),
                        'timeframe' => 'Immediate',
                    ];
                }
            }
        }

        return $timeline;
    }

    /**
     * Generate next steps
     */
    private function generateNextSteps(array $data, ?array $profileCompleteness): array
    {
        $gaps = $data['gaps'];
        $gapsByCategory = $gaps['gaps_by_category'] ?? [];

        $immediate = [];
        $shortTerm = [];
        $ongoing = [];

        // Add profile completeness steps if incomplete
        if ($profileCompleteness && ! $profileCompleteness['is_complete']) {
            $completenessScore = $profileCompleteness['completeness_score'];

            if ($completenessScore < 70) {
                $immediate[] = '⚠️ PRIORITY: Complete your profile information for accurate protection planning';

                // Add specific missing fields
                $missingFields = $profileCompleteness['missing_fields'] ?? [];
                foreach ($missingFields as $key => $field) {
                    if ($field['priority'] === 'high' && $field['required']) {
                        $immediate[] = '  → '.$field['message'];
                    }
                }
            }
        }

        // Check if there are any significant protection gaps
        $totalGap = $gaps['total_gap'] ?? 0;
        $hasGaps = $totalGap > 0
            || ($gapsByCategory['human_capital_gap'] ?? 0) > 0
            || ($gapsByCategory['debt_protection_gap'] ?? 0) > 0
            || ($gapsByCategory['income_protection_gap'] ?? 0) > 0;

        if ($hasGaps) {
            $immediate[] = 'Consult with an independent financial adviser to discuss protection needs';
            $immediate[] = 'Obtain quotes from multiple providers for recommended coverage';
            $immediate[] = 'Review existing policy terms and conditions';
        }

        $shortTerm[] = 'Complete medical underwriting if required';
        $shortTerm[] = 'Set up policies in trust to avoid IHT and ensure swift payout';
        $shortTerm[] = 'Update beneficiaries on all policies';

        $ongoing[] = 'Review protection coverage annually or after major life events';
        $ongoing[] = 'Monitor premium costs and consider switching if better rates available';
        $ongoing[] = 'Ensure policies are written in trust and wills are up to date';

        return [
            'Immediate Actions' => $immediate,
            'Short-term (1-3 months)' => $shortTerm,
            'Ongoing Management' => $ongoing,
        ];
    }

    // Helper methods

    private function getRecommendedAction(string $overallRating, string $lifeRating, string $ciRating, string $ipRating, bool $hasDependants = false): string
    {
        $missingCoverage = [];

        // Check for missing policy types (Critical rating with no coverage)
        if ($ciRating === 'Critical') {
            $missingCoverage[] = 'Critical Illness';
        }

        if ($ipRating === 'Critical') {
            $missingCoverage[] = 'Income Protection';
        }

        // If life coverage is excellent but other types are missing, recommend them
        if ($lifeRating === 'Excellent' && ! empty($missingCoverage)) {
            $types = implode(' and ', $missingCoverage);

            return "Your life insurance coverage is excellent. Consider adding {$types} to provide comprehensive protection.";
        }

        // If life coverage has gaps (Good or Fair), prioritise that
        if (in_array($lifeRating, ['Good', 'Fair'], true)) {
            $recommendation = 'Priority: Increase life insurance coverage to adequate levels.';
            if (! empty($missingCoverage)) {
                $types = implode(' and ', $missingCoverage);
                $recommendation .= " Also consider adding {$types}.";
            }

            return $recommendation;
        }

        // If life coverage is critical (no or very low coverage)
        if ($lifeRating === 'Critical') {
            return $hasDependants
                ? 'Critical: No life insurance coverage detected. Immediate action required to protect your family\'s financial future.'
                : 'Critical: No life insurance coverage detected. Consider adding cover to protect your loved ones and cover outstanding debts.';
        }

        // All coverage types present and adequate
        if ($overallRating === 'Excellent' && empty($missingCoverage)) {
            return 'Your protection coverage is comprehensive. Review annually to ensure it remains adequate.';
        }

        // Fallback to overall rating-based recommendations
        if ($overallRating === 'Good') {
            return 'Your protection coverage is adequate but could be improved. Consider addressing the gaps identified.';
        } elseif ($overallRating === 'Fair') {
            return $hasDependants
                ? 'Your protection coverage has significant gaps. Priority action required to protect your family.'
                : 'Your protection coverage has significant gaps. Priority action recommended to address these.';
        } else {
            return $hasDependants
                ? 'Your protection coverage is critically inadequate. Urgent action required to secure your family\'s financial future.'
                : 'Your protection coverage is critically inadequate. Urgent action required to improve your financial security.';
        }
    }

    private function getCoverageStatus(float $score): string
    {
        if ($score >= 80) {
            return 'Excellent';
        }
        if ($score >= 60) {
            return 'Good';
        }
        if ($score >= 40) {
            return 'Fair';
        }

        return 'Critical';
    }

    private function convertToAnnualPremium(float $amount, string $frequency): float
    {
        return match ($frequency) {
            'monthly' => $amount * 12,
            'quarterly' => $amount * 4,
            'annually', 'annual' => $amount,
            default => $amount * 12,
        };
    }

    private function estimateLifePremium(float $coverage, int $age, bool $smoker): float
    {
        // Simplified premium estimation (£ per £1000 of coverage per month)
        $baseRate = $smoker ? 1.5 : 0.8;
        $ageMultiplier = 1 + (($age - 30) * 0.05);

        return ($coverage / 1000) * $baseRate * $ageMultiplier;
    }

    private function estimateCIPremium(float $coverage, int $age, bool $smoker): float
    {
        // CI is typically 50% more expensive than life insurance
        return $this->estimateLifePremium($coverage, $age, $smoker) * 1.5;
    }

    private function estimateIPPremium(float $monthlyBenefit, int $age): float
    {
        // IP typically costs 1-3% of benefit per month
        $rate = 0.02 + (($age - 30) * 0.001);

        return $monthlyBenefit * $rate;
    }
}
