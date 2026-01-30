<?php

declare(strict_types=1);

namespace App\Services\Retirement;

use App\Models\RetirementProfile;
use App\Models\User;
use App\Services\Settings\AssumptionsService;
use App\Services\UserProfile\UserProfileService;
use Carbon\Carbon;

/**
 * Required Capital Calculator
 *
 * Calculates the capital required at retirement to sustain target income,
 * with both Future Value (compounding) and Present Value (discounting) calculations.
 *
 * Formulas:
 * - Future Value: FV = PV × (1 + r/m)^(m×n)
 * - Present Value: PV = FV / (1 + r)^n
 */
class RequiredCapitalCalculator
{
    private const DEFAULT_WITHDRAWAL_RATE = 0.047; // 4.7% sustainable withdrawal rate

    private const DEFAULT_FEE_RATE = 0.01; // 1% default fees

    private const DEFAULT_INFLATION_RATE = 0.025; // 2.5% for discounting to today's money

    private const DEFAULT_COMPOUND_PERIODS = 4; // Quarterly compounding

    private const TARGET_INCOME_PERCENT = 0.75; // 75% of net income

    private const DEFAULT_RETIREMENT_AGE = 68;

    public function __construct(
        private AssumptionsService $assumptionsService,
        private UserProfileService $userProfileService
    ) {}

    /**
     * Calculate required capital with full breakdown.
     *
     * @return array{
     *     required_income: float,
     *     required_capital_at_retirement: float,
     *     required_capital_today: float,
     *     assumptions: array,
     *     retirement_info: array,
     *     year_by_year: array
     * }
     */
    public function calculate(int $userId): array
    {
        $user = User::with(['dcPensions.holdings', 'retirementProfile'])
            ->findOrFail($userId);

        // Get user assumptions
        $pensionAssumptions = $this->assumptionsService->getTypeAssumptions($user, 'pensions');

        // Get retirement info
        $retirementInfo = $this->getRetirementInfo($user);

        // Get required income (from profile or 75% of net income)
        $requiredIncome = $this->getRequiredIncome($user);

        // Get current pension pot value
        $currentPotValue = $this->getCurrentPensionPotValue($user);

        // Calculate required capital at retirement using withdrawal rate
        $withdrawalRate = self::DEFAULT_WITHDRAWAL_RATE;
        $requiredCapitalAtRetirement = $requiredIncome / $withdrawalRate;

        // Build assumptions array for response
        $returnRate = (float) $pensionAssumptions['return_rate'];
        $feesTotal = (float) ($pensionAssumptions['fees']['total'] ?? self::DEFAULT_FEE_RATE * 100);
        $netReturnRate = max(0, $returnRate - $feesTotal);
        $inflationRate = self::DEFAULT_INFLATION_RATE * 100; // 2.5%
        $compoundPeriods = (int) ($pensionAssumptions['compound_periods'] ?? self::DEFAULT_COMPOUND_PERIODS);

        $assumptions = [
            'return_rate' => round($returnRate, 2),
            'net_return_rate' => round($netReturnRate, 2),
            'inflation_rate' => round($inflationRate, 2),
            'compound_periods' => $compoundPeriods,
            'fees_total' => round($feesTotal, 2),
            'withdrawal_rate' => round($withdrawalRate * 100, 2), // As percentage
        ];

        // Build year-by-year projection table
        $yearByYear = $this->buildYearByYearTable(
            currentValue: $currentPotValue,
            netReturnRate: $netReturnRate / 100,
            inflationRate: $inflationRate / 100,
            compoundPeriods: $compoundPeriods,
            yearsToRetirement: $retirementInfo['years_to_retirement'],
            currentAge: $retirementInfo['current_age'],
            targetCapital: $requiredCapitalAtRetirement
        );

        // Calculate present value of required capital (what it's worth in today's money)
        $requiredCapitalToday = $this->calculatePresentValue(
            futureValue: $requiredCapitalAtRetirement,
            rate: $inflationRate / 100,
            years: $retirementInfo['years_to_retirement']
        );

        return [
            'required_income' => round($requiredIncome, 2),
            'required_capital_at_retirement' => round($requiredCapitalAtRetirement, 2),
            'required_capital_today' => round($requiredCapitalToday, 2),
            'current_pot_value' => round($currentPotValue, 2),
            'assumptions' => $assumptions,
            'retirement_info' => $retirementInfo,
            'year_by_year' => $yearByYear,
            'income_source' => $this->getIncomeSource($user),
        ];
    }

    /**
     * Get required income from retirement profile or calculate from net income.
     */
    private function getRequiredIncome(User $user): float
    {
        // First check retirement profile for target income
        if ($user->retirementProfile?->target_retirement_income) {
            return (float) $user->retirementProfile->target_retirement_income;
        }

        // Fallback: Calculate 75% of net income
        $netIncome = $this->calculateUserNetIncome($user);

        return $netIncome * self::TARGET_INCOME_PERCENT;
    }

    /**
     * Get the source of the income figure for display purposes.
     */
    private function getIncomeSource(User $user): string
    {
        if ($user->retirementProfile?->target_retirement_income) {
            return 'profile';
        }

        return 'calculated';
    }

    /**
     * Calculate user's gross income minus pension contributions.
     *
     * For retirement planning, we use gross income less pension contributions
     * because the 75% multiplier already accounts for tax. This gives a more
     * realistic target retirement income.
     *
     * Formula: (Gross Income - Pension Contributions) × 75%
     */
    private function calculateUserNetIncome(User $user): float
    {
        $profile = $this->userProfileService->getCompleteProfile($user);
        $incomeOccupation = $profile['income_occupation'] ?? [];

        // Get total gross income
        $grossIncome = (float) ($incomeOccupation['total_annual_income'] ?? 0);

        // Subtract pension contributions (user won't be making these in retirement)
        $pensionContributions = (float) ($incomeOccupation['annual_pension_contributions'] ?? 0);

        return max(0, $grossIncome - $pensionContributions);
    }

    /**
     * Get retirement info for the user.
     */
    private function getRetirementInfo(User $user): array
    {
        $currentAge = $user->date_of_birth?->age ?? 40;
        $retirementAge = $this->getRetirementAge($user);
        $yearsToRetirement = max(0, $retirementAge - $currentAge);

        return [
            'current_age' => $currentAge,
            'retirement_age' => $retirementAge,
            'years_to_retirement' => $yearsToRetirement,
        ];
    }

    /**
     * Get user's retirement age from profile or pensions.
     */
    private function getRetirementAge(User $user): int
    {
        // First check user profile
        if ($user->target_retirement_age) {
            return $user->target_retirement_age;
        }

        // Then check retirement profile
        if ($user->retirementProfile?->target_retirement_age) {
            return $user->retirementProfile->target_retirement_age;
        }

        // Then check DC pensions
        foreach ($user->dcPensions as $pension) {
            if ($pension->retirement_age) {
                return $pension->retirement_age;
            }
        }

        return self::DEFAULT_RETIREMENT_AGE;
    }

    /**
     * Get current total DC pension pot value.
     */
    private function getCurrentPensionPotValue(User $user): float
    {
        return $user->dcPensions->sum(fn ($p) => (float) ($p->current_fund_value ?? 0));
    }

    /**
     * Build year-by-year projection table.
     *
     * @return array<int, array{
     *     year_number: int,
     *     calendar_year: int,
     *     age: int,
     *     accumulated_value: float,
     *     present_value_today: float,
     *     target_in_today_money: float,
     *     is_retirement_year: bool
     * }>
     */
    private function buildYearByYearTable(
        float $currentValue,
        float $netReturnRate,
        float $inflationRate,
        int $compoundPeriods,
        int $yearsToRetirement,
        int $currentAge,
        float $targetCapital
    ): array {
        $currentYear = Carbon::now()->year;
        $table = [];

        for ($year = 0; $year <= $yearsToRetirement; $year++) {
            // Calculate accumulated value using compound interest
            // FV = PV × (1 + r/m)^(m×n)
            $accumulatedValue = $this->calculateFutureValue(
                presentValue: $currentValue,
                rate: $netReturnRate,
                periods: $compoundPeriods,
                years: $year
            );

            // Calculate present value of accumulated value (discount back to today's money)
            $presentValueToday = $this->calculatePresentValue(
                futureValue: $accumulatedValue,
                rate: $inflationRate,
                years: $year
            );

            // Calculate target capital in today's money
            $targetInTodayMoney = $this->calculatePresentValue(
                futureValue: $targetCapital,
                rate: $inflationRate,
                years: $yearsToRetirement - $year
            );

            $table[] = [
                'year_number' => $year,
                'calendar_year' => $currentYear + $year,
                'age' => $currentAge + $year,
                'accumulated_value' => round($accumulatedValue, 2),
                'present_value_today' => round($presentValueToday, 2),
                'target_in_today_money' => round($targetInTodayMoney, 2),
                'is_retirement_year' => $year === $yearsToRetirement,
            ];
        }

        return $table;
    }

    /**
     * Calculate Future Value with compound interest.
     *
     * FV = PV × (1 + r/m)^(m×n)
     *
     * @param  float  $presentValue  Starting value (PV)
     * @param  float  $rate  Annual return rate as decimal (e.g., 0.05 for 5%)
     * @param  int  $periods  Compounding periods per year (m)
     * @param  int  $years  Number of years (n)
     */
    private function calculateFutureValue(
        float $presentValue,
        float $rate,
        int $periods,
        int $years
    ): float {
        if ($years <= 0) {
            return $presentValue;
        }

        $periodicRate = $rate / $periods;
        $totalPeriods = $periods * $years;

        return $presentValue * pow(1 + $periodicRate, $totalPeriods);
    }

    /**
     * Calculate Present Value (discount a future value to today's money).
     *
     * PV = FV / (1 + r)^n
     *
     * @param  float  $futureValue  Future value (FV)
     * @param  float  $rate  Discount rate as decimal (e.g., 0.025 for 2.5% inflation)
     * @param  int  $years  Number of years (n)
     */
    private function calculatePresentValue(
        float $futureValue,
        float $rate,
        int $years
    ): float {
        if ($years <= 0) {
            return $futureValue;
        }

        return $futureValue / pow(1 + $rate, $years);
    }
}
