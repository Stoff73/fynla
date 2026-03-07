<?php

declare(strict_types=1);

namespace App\Services\Risk;

use App\Models\DCPension;
use App\Models\FamilyMember;
use App\Models\Investment\InvestmentAccount;
use App\Models\Investment\RiskProfile;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\NetWorth\NetWorthService;
use Carbon\Carbon;

/**
 * Automated Risk Profile Calculator
 *
 * Calculates a user's risk profile based on 9 financial factors:
 * 1. Capacity for Loss - (investments + pensions) / net worth
 * 2. Time Horizon - Years to retirement
 * 3. Investment Knowledge - Self-assessed knowledge level (novice/intermediate/experienced)
 * 4. Dependants - Number of dependent family members
 * 5. Employment - Employment status
 * 6. Emergency Cash - Emergency fund runway months
 * 7. Surplus Cash - Monthly income minus expenditure
 * 8. Age - Recovery time based on current age
 * 9. Income Stability - Employment type stability assessment
 *
 * Final risk level is determined by mode (most recurring level).
 */
class AutoRiskCalculator
{
    public function __construct(
        private readonly NetWorthService $netWorthService
    ) {}

    private const RISK_LEVEL_ORDER = [
        'low' => 1,
        'lower_medium' => 2,
        'medium' => 3,
        'upper_medium' => 4,
        'high' => 5,
    ];

    /**
     * Calculate risk profile based on 9 factors
     *
     * @return array{risk_level: string, factor_breakdown: array}
     */
    public function calculateRiskProfile(User $user): array
    {
        $factors = [
            $this->calculateCapacityForLoss($user),
            $this->calculateTimeHorizon($user),
            $this->calculateKnowledgeFactor($user),
            $this->calculateDependantsFactor($user),
            $this->calculateEmploymentFactor($user),
            $this->calculateEmergencyCashFactor($user),
            $this->calculateSurplusCashFactor($user),
            $this->calculateAgeFactor($user),
            $this->calculateIncomeStabilityFactor($user),
        ];

        return [
            'risk_level' => $this->determineFinalLevel($factors),
            'factor_breakdown' => $factors,
        ];
    }

    /**
     * Detect mismatch between user's stated risk tolerance and calculated capacity.
     *
     * Returns null if no mismatch or no stated preference.
     *
     * @return array{type: string, message: string, user_tolerance: string, calculated_capacity: string, difference: int}|null
     */
    public function detectRiskMismatch(RiskProfile $profile): ?array
    {
        $userTolerance = $profile->risk_tolerance;
        $calculatedLevel = $profile->risk_level;

        if (! $userTolerance || ! $calculatedLevel) {
            return null;
        }

        $toleranceNumeric = self::RISK_LEVEL_ORDER[$userTolerance] ?? null;
        $calculatedNumeric = self::RISK_LEVEL_ORDER[$calculatedLevel] ?? null;

        if ($toleranceNumeric === null || $calculatedNumeric === null) {
            return null;
        }

        $difference = abs($toleranceNumeric - $calculatedNumeric);

        if ($difference <= 2) {
            return null;
        }

        $direction = $toleranceNumeric > $calculatedNumeric ? 'higher' : 'lower';

        return [
            'type' => 'mismatch',
            'message' => sprintf(
                'Your chosen risk level is significantly %s than what your financial circumstances suggest. Consider reviewing your risk profile to ensure your investments align with your current situation.',
                $direction
            ),
            'user_tolerance' => $userTolerance,
            'calculated_capacity' => $calculatedLevel,
            'difference' => $difference,
        ];
    }

    /**
     * Factor 1: Capacity for Loss
     * (investments + pensions) / net worth
     *
     * 0-15% = HIGH (less at risk, more capacity to lose)
     * 15-50% = MEDIUM
     * 50-75% = LOWER_MEDIUM (medium-low capacity)
     * >75% = LOW (most at risk, least capacity to lose)
     */
    private function calculateCapacityForLoss(User $user): array
    {
        $netWorthData = $this->netWorthService->calculateNetWorth($user);
        $netWorth = $netWorthData['net_worth'];

        // Sum investments and pensions
        $investmentsTotal = InvestmentAccount::where('user_id', $user->id)->sum('current_value');
        $pensionsTotal = DCPension::where('user_id', $user->id)->sum('current_fund_value');
        $atRiskAssets = $investmentsTotal + $pensionsTotal;

        // Calculate ratio
        $ratio = $netWorth > 0 ? ($atRiskAssets / $netWorth) * 100 : 0;
        $roundedRatio = round($ratio, 1);

        // Determine level
        if ($ratio <= 15) {
            $level = 'high';
            $description = $roundedRatio.'% of your net worth is in investments/pensions, giving you high capacity to take risk.';
        } elseif ($ratio <= 50) {
            $level = 'medium';
            $description = $roundedRatio.'% of your net worth is in investments/pensions, indicating moderate capacity for loss.';
        } elseif ($ratio <= 75) {
            $level = 'lower_medium';
            $description = $roundedRatio.'% of your net worth is in investments/pensions, suggesting medium-low capacity for loss.';
        } else {
            $level = 'low';
            $description = $roundedRatio.'% of your net worth is in investments/pensions, indicating low capacity to absorb losses.';
        }

        return [
            'factor' => 'capacity_for_loss',
            'display_name' => 'Capacity for Loss',
            'level' => $level,
            'value' => $roundedRatio.'%',
            'raw_value' => $roundedRatio,
            'description' => $description,
            'icon' => 'shield',
            'components' => [
                'investments_total' => round((float) $investmentsTotal, 2),
                'pensions_total' => round((float) $pensionsTotal, 2),
                'net_worth' => round((float) $netWorth, 2),
            ],
        ];
    }

    /**
     * Factor 2: Time Horizon
     * Years to retirement
     *
     * Retired or <3 years = LOWER_MEDIUM
     * 3-15 years = MEDIUM
     * 15-20 years = UPPER_MEDIUM
     * 20+ years = HIGH
     */
    private function calculateTimeHorizon(User $user): array
    {
        $yearsToRetirement = $this->calculateYearsToRetirement($user);

        if ($yearsToRetirement === null || $yearsToRetirement <= 0) {
            $level = 'lower_medium';
            $description = 'You are retired or approaching retirement, favouring lower risk to protect your capital.';
            $value = 'Retired';
        } elseif ($yearsToRetirement < 3) {
            $level = 'lower_medium';
            $description = 'With less than 3 years to retirement, capital preservation is important.';
            $value = $yearsToRetirement.' years';
        } elseif ($yearsToRetirement <= 15) {
            $level = 'medium';
            $description = 'With 3-15 years to retirement, you have time for moderate risk and recovery.';
            $value = $yearsToRetirement.' years';
        } elseif ($yearsToRetirement <= 20) {
            $level = 'upper_medium';
            $description = 'With 15-20 years to retirement, you can accept higher volatility for growth.';
            $value = $yearsToRetirement.' years';
        } else {
            $level = 'high';
            $description = 'With 20+ years to retirement, you have ample time to ride out market volatility.';
            $value = $yearsToRetirement.' years';
        }

        $age = $user->date_of_birth ? Carbon::parse($user->date_of_birth)->age : null;

        return [
            'factor' => 'time_horizon',
            'display_name' => 'Time Horizon',
            'level' => $level,
            'value' => $value,
            'raw_value' => $yearsToRetirement ?? 0,
            'description' => $description,
            'icon' => 'clock',
            'components' => [
                'current_age' => $age,
                'target_retirement_age' => $user->target_retirement_age,
                'employment_status' => $user->employment_status,
                'years_to_retirement' => $yearsToRetirement,
            ],
        ];
    }

    /**
     * Factor 3: Investment Knowledge
     *
     * Uses self-assessed knowledge_level from risk profile:
     * novice (or null) = LOWER_MEDIUM
     * intermediate = MEDIUM
     * experienced = UPPER_MEDIUM
     */
    private function calculateKnowledgeFactor(User $user): array
    {
        $riskProfile = RiskProfile::where('user_id', $user->id)->first();
        $knowledgeLevel = $riskProfile->knowledge_level ?? null;

        if ($knowledgeLevel === 'experienced') {
            $level = 'upper_medium';
            $description = 'Strong investment knowledge and experience supports higher risk tolerance.';
            $displayValue = 'Experienced';
        } elseif ($knowledgeLevel === 'intermediate') {
            $level = 'medium';
            $description = 'Some investment knowledge allows for a balanced approach to risk.';
            $displayValue = 'Intermediate';
        } else {
            $level = 'lower_medium';
            $description = 'Limited investment knowledge suggests a more cautious approach.';
            $displayValue = $knowledgeLevel ? ucfirst($knowledgeLevel) : 'Not specified';
        }

        return [
            'factor' => 'knowledge_level',
            'display_name' => 'Investment Knowledge',
            'level' => $level,
            'value' => $displayValue,
            'raw_value' => $knowledgeLevel,
            'description' => $description,
            'icon' => 'academic-cap',
            'components' => [
                'knowledge_level' => $knowledgeLevel,
            ],
        ];
    }

    /**
     * Factor 4: Dependants
     * Count of family members with is_dependent=true
     *
     * 0 = UPPER_MEDIUM
     * 1 = MEDIUM
     * 2+ = LOWER_MEDIUM
     */
    private function calculateDependantsFactor(User $user): array
    {
        $dependants = FamilyMember::where('user_id', $user->id)
            ->where('is_dependent', true)
            ->get(['first_name', 'relationship']);

        $dependantCount = $dependants->count();

        if ($dependantCount === 0) {
            $level = 'upper_medium';
            $description = 'No dependants means you can afford to take more investment risk.';
        } elseif ($dependantCount === 1) {
            $level = 'medium';
            $description = 'One dependant suggests a balanced approach to risk.';
        } else {
            $level = 'lower_medium';
            $description = 'Multiple dependants means financial stability is a priority.';
        }

        return [
            'factor' => 'dependants',
            'display_name' => 'Dependants',
            'level' => $level,
            'value' => (string) $dependantCount,
            'raw_value' => $dependantCount,
            'description' => $description,
            'icon' => 'users',
            'components' => [
                'count' => $dependantCount,
                'dependants' => $dependants->map(fn ($d) => [
                    'name' => $d->first_name,
                    'relationship' => $d->relationship,
                ])->toArray(),
            ],
        ];
    }

    /**
     * Factor 5: Employment Status
     *
     * Employed/Self-employed = MEDIUM
     * Retired = LOWER_MEDIUM
     */
    private function calculateEmploymentFactor(User $user): array
    {
        $employmentStatus = $user->employment_status;

        $workingStatuses = ['employed', 'self_employed', 'full_time', 'part_time', 'contractor'];
        $retiredStatuses = ['retired', 'semi_retired'];

        if (in_array($employmentStatus, $workingStatuses, true)) {
            $level = 'medium';
            $description = 'Active employment provides income to rebuild if investments fall.';
        } elseif (in_array($employmentStatus, $retiredStatuses, true)) {
            $level = 'lower_medium';
            $description = 'Without regular employment income, capital preservation becomes important.';
        } else {
            // Unemployed, student, or unknown - default to lower_medium for safety
            $level = 'lower_medium';
            $description = 'Without stable employment income, a cautious approach is recommended.';
        }

        $displayValue = ucwords(str_replace('_', ' ', $employmentStatus ?: 'Not specified'));

        return [
            'factor' => 'employment',
            'display_name' => 'Employment Status',
            'level' => $level,
            'value' => $displayValue,
            'raw_value' => $employmentStatus,
            'description' => $description,
            'icon' => 'briefcase',
            'components' => [
                'employment_status' => $employmentStatus,
                'is_working' => in_array($employmentStatus, $workingStatuses, true),
            ],
        ];
    }

    /**
     * Factor 6: Emergency Cash
     * Emergency fund runway in months
     *
     * 0-3 months = LOWER_MEDIUM
     * 3-6 months = MEDIUM
     * 6+ months = UPPER_MEDIUM
     */
    private function calculateEmergencyCashFactor(User $user): array
    {
        // Get emergency fund savings
        $emergencyFundTotal = SavingsAccount::where('user_id', $user->id)
            ->where('is_emergency_fund', true)
            ->sum('current_balance');

        // Get monthly expenditure
        $monthlyExpenditure = $user->monthly_expenditure ?? 0;

        // Calculate runway
        $runwayMonths = $monthlyExpenditure > 0
            ? $emergencyFundTotal / $monthlyExpenditure
            : ($emergencyFundTotal > 0 ? 12 : 0); // Assume 12 months if no expenditure set but has funds

        if ($runwayMonths < 3) {
            $level = 'lower_medium';
            $description = 'Less than 3 months emergency fund suggests keeping investments more conservative.';
        } elseif ($runwayMonths < 6) {
            $level = 'medium';
            $description = '3-6 months emergency fund provides reasonable buffer for investment risk.';
        } else {
            $level = 'upper_medium';
            $description = '6+ months emergency fund gives you cushion to ride out market volatility.';
        }

        $value = round($runwayMonths, 1).' months';

        return [
            'factor' => 'emergency_cash',
            'display_name' => 'Emergency Fund',
            'level' => $level,
            'value' => $value,
            'raw_value' => round($runwayMonths, 1),
            'description' => $description,
            'icon' => 'cash',
            'components' => [
                'emergency_fund_total' => round((float) $emergencyFundTotal, 2),
                'monthly_expenditure' => round((float) $monthlyExpenditure, 2),
                'runway_months' => round($runwayMonths, 1),
            ],
        ];
    }

    /**
     * Factor 7: Surplus Cash
     * Monthly income - expenditure, assessed as percentage of income
     *
     * Negative to 0 = LOWER_MEDIUM
     * 0-10% of income = MEDIUM
     * >10% of income = UPPER_MEDIUM
     */
    private function calculateSurplusCashFactor(User $user): array
    {
        // Calculate monthly income
        $annualIncome = ($user->annual_employment_income ?? 0)
            + ($user->annual_self_employment_income ?? 0)
            + ($user->annual_rental_income ?? 0)
            + ($user->annual_dividend_income ?? 0)
            + ($user->annual_interest_income ?? 0)
            + ($user->annual_other_income ?? 0)
            + ($user->annual_trust_income ?? 0);

        $monthlyIncome = $annualIncome / 12;
        $monthlyExpenditure = $user->monthly_expenditure ?? 0;
        $surplus = $monthlyIncome - $monthlyExpenditure;

        if ($surplus <= 0) {
            $level = 'lower_medium';
            $description = 'No monthly surplus means limited ability to top up investments if needed.';
        } elseif ($monthlyIncome > 0 && ($surplus / $monthlyIncome) > 0.10) {
            $level = 'upper_medium';
            $surplusPercent = ($surplus / $monthlyIncome) * 100;
            $description = sprintf(
                'Monthly surplus of £%s (%.0f%% of income) allows regular investing and risk tolerance.',
                number_format($surplus, 0),
                $surplusPercent
            );
        } else {
            $level = 'medium';
            $description = 'Modest monthly surplus provides some capacity for investment contributions.';
        }

        return [
            'factor' => 'surplus_cash',
            'display_name' => 'Monthly Surplus',
            'level' => $level,
            'value' => $surplus >= 0 ? '£'.number_format($surplus, 0) : '-£'.number_format(abs($surplus), 0),
            'raw_value' => round($surplus, 2),
            'description' => $description,
            'icon' => 'trending-up',
            'components' => [
                'annual_income' => round($annualIncome, 2),
                'monthly_income' => round($monthlyIncome, 2),
                'monthly_expenditure' => round((float) $monthlyExpenditure, 2),
                'surplus' => round($surplus, 2),
                'surplus_percent' => $monthlyIncome > 0 ? round(($surplus / $monthlyIncome) * 100, 1) : null,
            ],
        ];
    }

    /**
     * Factor 8: Age
     * Younger users have more time to recover from losses
     *
     * Under 30 = HIGH
     * 30-45 = UPPER_MEDIUM
     * 45-55 = MEDIUM
     * 55-65 = LOWER_MEDIUM
     * 65+ = LOW
     */
    private function calculateAgeFactor(User $user): array
    {
        $age = $user->date_of_birth ? Carbon::parse($user->date_of_birth)->age : null;

        if ($age === null) {
            $level = 'medium';
            $description = 'Age not specified; a balanced approach is assumed.';
            $value = 'Not specified';
        } elseif ($age < 30) {
            $level = 'high';
            $description = 'At '.$age.', you have decades to recover from market downturns, supporting higher risk.';
            $value = (string) $age;
        } elseif ($age < 45) {
            $level = 'upper_medium';
            $description = 'At '.$age.', you have significant time for recovery, allowing above-average risk.';
            $value = (string) $age;
        } elseif ($age < 55) {
            $level = 'medium';
            $description = 'At '.$age.', a balanced approach is appropriate as recovery time narrows.';
            $value = (string) $age;
        } elseif ($age < 65) {
            $level = 'lower_medium';
            $description = 'At '.$age.', capital preservation becomes increasingly important.';
            $value = (string) $age;
        } else {
            $level = 'low';
            $description = 'At '.$age.', protecting your capital is the priority with limited recovery time.';
            $value = (string) $age;
        }

        return [
            'factor' => 'age',
            'display_name' => 'Age',
            'level' => $level,
            'value' => $value,
            'raw_value' => $age,
            'description' => $description,
            'icon' => 'calendar',
            'components' => [
                'age' => $age,
                'date_of_birth' => $user->date_of_birth?->toDateString(),
            ],
        ];
    }

    /**
     * Factor 9: Income Stability
     * Employment type affects income predictability and risk capacity
     *
     * employed (full-time) = UPPER_MEDIUM (stable income)
     * self_employed = LOWER_MEDIUM (variable income)
     * contractor = MEDIUM (some stability)
     * part_time = LOWER_MEDIUM (reduced income)
     * retired = LOWER_MEDIUM (fixed income)
     * unemployed = LOW (no income)
     */
    private function calculateIncomeStabilityFactor(User $user): array
    {
        $employmentStatus = $user->employment_status;

        $stabilityMap = [
            'employed' => ['level' => 'upper_medium', 'label' => 'Stable', 'description' => 'Full-time employment provides predictable income, supporting higher risk capacity.'],
            'full_time' => ['level' => 'upper_medium', 'label' => 'Stable', 'description' => 'Full-time employment provides predictable income, supporting higher risk capacity.'],
            'self_employed' => ['level' => 'lower_medium', 'label' => 'Variable', 'description' => 'Self-employment income can be unpredictable, suggesting a more cautious approach.'],
            'contractor' => ['level' => 'medium', 'label' => 'Moderate', 'description' => 'Contract work provides income but with less long-term certainty than permanent employment.'],
            'part_time' => ['level' => 'lower_medium', 'label' => 'Reduced', 'description' => 'Part-time income is lower, limiting capacity to absorb investment losses.'],
            'retired' => ['level' => 'lower_medium', 'label' => 'Fixed', 'description' => 'Retirement income is largely fixed, making capital preservation important.'],
            'semi_retired' => ['level' => 'lower_medium', 'label' => 'Fixed', 'description' => 'Semi-retirement income is largely fixed, making capital preservation important.'],
            'unemployed' => ['level' => 'low', 'label' => 'None', 'description' => 'Without employment income, investment risk should be minimised.'],
        ];

        $config = $stabilityMap[$employmentStatus] ?? [
            'level' => 'lower_medium',
            'label' => 'Unknown',
            'description' => 'Employment status not specified; a cautious approach is recommended.',
        ];

        return [
            'factor' => 'income_stability',
            'display_name' => 'Income Stability',
            'level' => $config['level'],
            'value' => $config['label'],
            'raw_value' => $employmentStatus,
            'description' => $config['description'],
            'icon' => 'currency-pound',
            'components' => [
                'employment_status' => $employmentStatus,
                'stability_level' => $config['label'],
            ],
        ];
    }

    /**
     * Determine final risk level using mode (most frequent level)
     *
     * In case of tie, prefer the lower risk level for safety
     */
    private function determineFinalLevel(array $factors): string
    {
        $levelCounts = [];

        foreach ($factors as $factor) {
            $level = $factor['level'];
            $levelCounts[$level] = ($levelCounts[$level] ?? 0) + 1;
        }

        // Sort by count descending
        arsort($levelCounts);

        // Get the highest count
        $maxCount = reset($levelCounts);

        // Get all levels with the max count (handles ties)
        $topLevels = array_keys(array_filter($levelCounts, fn ($count) => $count === $maxCount));

        // If tie, prefer lower risk level
        $riskOrder = ['low', 'lower_medium', 'medium', 'upper_medium', 'high'];

        foreach ($riskOrder as $level) {
            if (in_array($level, $topLevels, true)) {
                return $level;
            }
        }

        // Default to medium if something goes wrong
        return 'medium';
    }

    /**
     * Calculate years to retirement
     */
    private function calculateYearsToRetirement(User $user): ?int
    {
        // Check if already retired
        if (in_array($user->employment_status, ['retired', 'semi_retired'], true)) {
            return 0;
        }

        // Calculate from target retirement age
        if ($user->target_retirement_age && $user->date_of_birth) {
            $age = Carbon::parse($user->date_of_birth)->age;
            $yearsToRetirement = $user->target_retirement_age - $age;

            return max(0, $yearsToRetirement);
        }

        // Calculate from retirement_date if set
        if ($user->retirement_date) {
            $retirementDate = Carbon::parse($user->retirement_date);
            $yearsToRetirement = (int) Carbon::now()->diffInYears($retirementDate, false);

            return max(0, $yearsToRetirement);
        }

        // Default: Assume state pension age of 67
        if ($user->date_of_birth) {
            $age = Carbon::parse($user->date_of_birth)->age;
            $defaultRetirementAge = 67;

            return max(0, $defaultRetirementAge - $age);
        }

        // Cannot determine - return null
        return null;
    }
}
