<?php

declare(strict_types=1);

namespace App\Services\Coordination;

use App\Constants\TaxDefaults;
use App\Models\User;
use App\Services\TaxConfigService;

/**
 * CrossModuleStrategyService
 *
 * Generates cross-cutting strategies that span multiple financial planning modules.
 * Identifies optimisation opportunities where the intersection of two or more modules
 * can deliver better outcomes than each module acting independently.
 */
class CrossModuleStrategyService
{
    private const PRIORITY_HIGH = 'high';

    private const PRIORITY_MEDIUM = 'medium';

    private const PRIORITY_LOW = 'low';

    /** @var int Years threshold for short-term goals */
    private const SHORT_TERM_YEARS = 3;

    /** @var int Years threshold for long-term goals */
    private const LONG_TERM_YEARS = 10;

    /** @var int Years before retirement to trigger de-risking advice */
    private const DERISKING_HORIZON_YEARS = 5;

    /** @var float Equity allocation threshold for de-risking warning */
    private const HIGH_EQUITY_THRESHOLD = 0.80;

    /** @var int Years before retirement to trigger protection phase-out advice */
    private const PROTECTION_RETIREMENT_HORIZON = 10;

    /** @var int Minimum emergency fund months considered adequate */
    private const ADEQUATE_EMERGENCY_MONTHS = 6;

    public function __construct(
        private readonly TaxConfigService $taxConfig
    ) {}

    /**
     * Generate cross-module strategies from collected module analysis.
     *
     * @param  array  $moduleAnalysis  Analysis data from all modules
     * @param  User  $user  The user being analysed
     * @return array Array of cross-module strategy objects, sorted by priority
     */
    public function generateCrossModuleStrategies(array $moduleAnalysis, User $user): array
    {
        $strategies = [];

        $strategies = array_merge($strategies, $this->generateTaxInvestmentStrategies($moduleAnalysis));
        $strategies = array_merge($strategies, $this->generateTaxRetirementStrategies($moduleAnalysis, $user));
        $strategies = array_merge($strategies, $this->generateProtectionRetirementStrategies($moduleAnalysis, $user));
        $strategies = array_merge($strategies, $this->generateInvestmentGoalsStrategies($moduleAnalysis));
        $strategies = array_merge($strategies, $this->generateInvestmentRetirementStrategies($moduleAnalysis, $user));

        // Sort by priority: high first, then medium, then low
        usort($strategies, function (array $a, array $b): int {
            $order = [self::PRIORITY_HIGH => 0, self::PRIORITY_MEDIUM => 1, self::PRIORITY_LOW => 2];

            return ($order[$a['priority']] ?? 2) <=> ($order[$b['priority']] ?? 2);
        });

        return $strategies;
    }

    /**
     * Tax + Investment strategies
     */
    private function generateTaxInvestmentStrategies(array $moduleAnalysis): array
    {
        $strategies = [];
        $investment = $moduleAnalysis['investment'] ?? [];
        $taxOptimisation = $moduleAnalysis['tax_optimisation'] ?? [];

        // Check for GIA holdings
        $hasGIA = $this->userHasGIAHoldings($investment);
        $isaAllowances = $this->taxConfig->getISAAllowances();
        $isaAllowance = $isaAllowances['annual_allowance'] ?? TaxDefaults::ISA_ALLOWANCE;

        // Check unused ISA allowance from tax optimisation data
        $isaUsed = $this->getISAUsed($taxOptimisation, $moduleAnalysis);
        $unusedISA = $isaAllowance - $isaUsed;

        if ($hasGIA && $unusedISA > 1000) {
            $strategies[] = $this->buildStrategy(
                'isa_before_gia',
                ['tax_optimisation', 'investment'],
                self::PRIORITY_HIGH,
                'Prioritise ISA Before Taxable Accounts',
                'You have holdings in a General Investment Account while your ISA allowance remains partially unused. Moving investments into an ISA wrapper shields future growth and income from tax.',
                'Review your General Investment Account holdings and consider transferring or redirecting new contributions to your ISA.',
                'Up to '.$this->formatCurrency($unusedISA).' can be sheltered from tax this year'
            );
        }

        // Check CGT exposure
        $cgtConfig = $this->taxConfig->getCapitalGainsTax();
        $cgtExempt = $cgtConfig['annual_exempt_amount'] ?? 3000;
        $estimatedGains = $this->getEstimatedGains($investment);

        if ($hasGIA && $estimatedGains > $cgtExempt) {
            $strategies[] = $this->buildStrategy(
                'staged_gain_realisation',
                ['tax_optimisation', 'investment'],
                self::PRIORITY_MEDIUM,
                'Consider Staged Gain Realisation',
                'Your estimated unrealised gains in taxable accounts exceed the annual Capital Gains Tax exempt amount. Selling holdings in stages across tax years can reduce or eliminate the tax liability.',
                'Review unrealised gains and consider realising up to the exempt amount each tax year.',
                'Potential to save Capital Gains Tax by using the annual exempt amount each year'
            );
        }

        // Check dividend-paying funds in GIA
        if ($hasGIA && $this->hasDividendExposureInGIA($investment)) {
            $strategies[] = $this->buildStrategy(
                'dividend_funds_to_isa',
                ['tax_optimisation', 'investment'],
                self::PRIORITY_LOW,
                'Move Dividend-Producing Funds to ISA',
                'Dividend-producing investments held in a General Investment Account are subject to dividend tax above the allowance. Moving these to an ISA wrapper eliminates the tax liability on dividends.',
                'Consider transferring dividend-paying funds from your General Investment Account into your ISA.',
                'Dividend income sheltered from tax within the ISA wrapper'
            );
        }

        return $strategies;
    }

    /**
     * Tax + Retirement strategies
     */
    private function generateTaxRetirementStrategies(array $moduleAnalysis, User $user): array
    {
        $strategies = [];
        $retirement = $moduleAnalysis['retirement'] ?? [];
        $taxOptimisation = $moduleAnalysis['tax_optimisation'] ?? [];

        $incomeTax = $this->taxConfig->getIncomeTax();
        $higherRateThreshold = (float) ($incomeTax['bands'][0]['upper_limit'] ?? TaxDefaults::HIGHER_RATE_THRESHOLD);

        // Determine if higher-rate taxpayer
        $annualIncome = $this->getUserAnnualIncome($moduleAnalysis);
        $isHigherRate = $annualIncome > $higherRateThreshold;

        // Check pension Annual Allowance usage
        $pensionAllowances = $this->taxConfig->getPensionAllowances();
        $annualAllowance = (float) $pensionAllowances['annual_allowance'];
        $pensionContributions = $this->getPensionContributions($retirement);
        $unusedAA = $annualAllowance - $pensionContributions;

        if ($isHigherRate && $unusedAA > 5000) {
            $strategies[] = $this->buildStrategy(
                'pension_before_isa',
                ['tax_optimisation', 'retirement'],
                self::PRIORITY_HIGH,
                'Maximise Pension Before ISA',
                'As a higher-rate taxpayer, pension contributions attract 40% tax relief compared to 0% for ISA contributions. Maximising your pension Annual Allowance before contributing to an ISA provides significantly greater tax efficiency.',
                'Consider increasing pension contributions to use more of your Annual Allowance before directing surplus to ISA.',
                'Up to 40% tax relief on additional pension contributions'
            );
        }

        // Check carry forward availability
        $carryForward = $this->getCarryForwardAvailable($retirement, $taxOptimisation);

        if ($carryForward > 0) {
            $strategies[] = $this->buildStrategy(
                'carry_forward_expiry',
                ['tax_optimisation', 'retirement'],
                self::PRIORITY_MEDIUM,
                'Use Carry Forward Before It Expires',
                'You have unused pension Annual Allowance from prior years that can be carried forward. This allowance expires after three years, so using it sooner prevents it being lost.',
                'Review your carry forward availability and consider making additional pension contributions to use the expiring allowance.',
                'Carry forward allowance available from prior tax years'
            );
        }

        return $strategies;
    }

    /**
     * Protection + Retirement strategies
     */
    private function generateProtectionRetirementStrategies(array $moduleAnalysis, User $user): array
    {
        $strategies = [];
        $protection = $moduleAnalysis['protection'] ?? [];
        $savings = $moduleAnalysis['savings'] ?? [];

        $userAge = $moduleAnalysis['user']['age'] ?? ($user->date_of_birth ? $user->date_of_birth->age : 40);
        $retirementAge = 67; // UK state pension age
        $yearsToRetirement = $retirementAge - $userAge;

        // Income protection within 10 years of retirement
        $hasIncomeProtection = $this->userHasIncomeProtection($protection);

        if ($hasIncomeProtection && $yearsToRetirement <= self::PROTECTION_RETIREMENT_HORIZON && $yearsToRetirement > 0) {
            $strategies[] = $this->buildStrategy(
                'income_protection_retirement',
                ['protection', 'retirement'],
                self::PRIORITY_MEDIUM,
                'Review Income Protection as Retirement Approaches',
                'You have income protection cover and are within '.$yearsToRetirement.' years of retirement. As your need for income replacement reduces closer to retirement, you may be able to reduce cover and redirect the premium savings.',
                'Review your income protection policy and consider reducing cover or adjusting the benefit period to align with your retirement date.',
                'Premium savings can be redirected to pension or other priorities'
            );
        }

        // Emergency fund adequacy reducing short-term protection need
        $emergencyFundMonths = $savings['emergency_fund_months'] ?? 0;

        if ($emergencyFundMonths >= self::ADEQUATE_EMERGENCY_MONTHS && $hasIncomeProtection) {
            $strategies[] = $this->buildStrategy(
                'emergency_fund_deferred_period',
                ['protection', 'savings'],
                self::PRIORITY_LOW,
                'Emergency Fund Supports Longer Deferred Period',
                'Your emergency fund covers '.$emergencyFundMonths.' months of expenses. This means you could extend the deferred period on your income protection policy, which typically reduces the premium.',
                'Consider extending your income protection deferred period to match your emergency fund coverage.',
                'Potential premium reduction from extending the deferred period'
            );
        }

        return $strategies;
    }

    /**
     * Investment + Goals strategies
     */
    private function generateInvestmentGoalsStrategies(array $moduleAnalysis): array
    {
        $strategies = [];
        $goals = $moduleAnalysis['goals'] ?? [];

        if (! ($goals['has_goals'] ?? false)) {
            return $strategies;
        }

        $goalsList = $goals['goals'] ?? $goals['full_analysis']['goals'] ?? [];

        foreach ($goalsList as $goal) {
            $yearsToTarget = $this->getYearsToGoalTarget($goal);
            $fundingType = $this->getGoalFundingType($goal);

            // Short-term goal funded by equity
            if ($yearsToTarget !== null && $yearsToTarget < self::SHORT_TERM_YEARS && $fundingType === 'equity') {
                $strategies[] = $this->buildStrategy(
                    'short_term_goal_cash',
                    ['investment', 'goals'],
                    self::PRIORITY_HIGH,
                    'Use Cash or Bonds for Short-Term Goals',
                    'You have a goal due within '.max(1, (int) $yearsToTarget).' years that is linked to equity investments. Short-term goals are better served by cash or bond holdings to avoid the risk of a market downturn reducing your funds when you need them.',
                    'Consider moving the funds earmarked for this goal into a cash or bond holding.',
                    'Reduced risk of market volatility affecting your goal'
                );
                break; // One strategy per type is enough
            }

            // Long-term goal in cash
            if ($yearsToTarget !== null && $yearsToTarget > self::LONG_TERM_YEARS && $fundingType === 'cash') {
                $strategies[] = $this->buildStrategy(
                    'long_term_goal_equity',
                    ['investment', 'goals'],
                    self::PRIORITY_MEDIUM,
                    'Consider Equity Exposure for Long-Term Goals',
                    'You have a goal more than '.(int) $yearsToTarget.' years away that is funded by cash holdings. Over longer time horizons, equity investments have historically provided higher returns and can help your savings grow faster.',
                    'Review whether some of the funds for this long-term goal could be moved into a diversified equity fund.',
                    'Potential for higher long-term returns with equity exposure'
                );
                break;
            }
        }

        return $strategies;
    }

    /**
     * Investment + Retirement strategies
     */
    private function generateInvestmentRetirementStrategies(array $moduleAnalysis, User $user): array
    {
        $strategies = [];
        $investment = $moduleAnalysis['investment'] ?? [];

        $userAge = $moduleAnalysis['user']['age'] ?? ($user->date_of_birth ? $user->date_of_birth->age : 40);
        $retirementAge = 67;
        $yearsToRetirement = $retirementAge - $userAge;

        if ($yearsToRetirement > 0 && $yearsToRetirement <= self::DERISKING_HORIZON_YEARS) {
            $equityAllocation = $this->getEquityAllocation($investment);

            if ($equityAllocation > self::HIGH_EQUITY_THRESHOLD) {
                $equityPercent = (int) round($equityAllocation * 100);
                $strategies[] = $this->buildStrategy(
                    'derisking_near_retirement',
                    ['investment', 'retirement'],
                    self::PRIORITY_HIGH,
                    'Consider De-Risking as Retirement Approaches',
                    'Your portfolio is '.$equityPercent.'% in equities and you are within '.$yearsToRetirement.' years of retirement. A significant market downturn close to retirement could materially reduce your retirement income. Gradually shifting towards bonds and cash can protect your accumulated wealth.',
                    'Review your asset allocation and consider a phased shift towards lower-risk investments as retirement approaches.',
                    'Reduced sequence-of-returns risk approaching retirement'
                );
            }
        }

        return $strategies;
    }

    /**
     * Build a standardised strategy array.
     */
    private function buildStrategy(
        string $type,
        array $modules,
        string $priority,
        string $title,
        string $description,
        string $action,
        string $estimatedImpact
    ): array {
        return [
            'type' => $type,
            'modules' => $modules,
            'priority' => $priority,
            'title' => $title,
            'description' => $description,
            'action' => $action,
            'estimated_impact' => $estimatedImpact,
        ];
    }

    /**
     * Check if user has GIA holdings from investment analysis.
     */
    private function userHasGIAHoldings(array $investment): bool
    {
        $fullAnalysis = $investment['full_analysis'] ?? [];
        $accounts = $fullAnalysis['accounts'] ?? $fullAnalysis['portfolio_summary']['accounts'] ?? [];

        if (is_array($accounts)) {
            foreach ($accounts as $account) {
                $type = strtolower($account['account_type'] ?? $account['type'] ?? '');
                if (in_array($type, ['gia', 'general_investment_account', 'trading'])) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get ISA contributions used this tax year.
     */
    private function getISAUsed(array $taxOptimisation, array $moduleAnalysis): float
    {
        // Try from tax optimisation allowance usage
        $allowanceUsage = $taxOptimisation['allowance_usage'] ?? [];
        if (isset($allowanceUsage['isa']['used'])) {
            return (float) $allowanceUsage['isa']['used'];
        }

        return 0.0;
    }

    /**
     * Get estimated unrealised gains from investment data.
     */
    private function getEstimatedGains(array $investment): float
    {
        $fullAnalysis = $investment['full_analysis'] ?? [];

        return (float) ($fullAnalysis['unrealised_gains'] ?? $fullAnalysis['total_unrealised_gains'] ?? 0);
    }

    /**
     * Check if GIA has dividend-paying holdings.
     */
    private function hasDividendExposureInGIA(array $investment): bool
    {
        $fullAnalysis = $investment['full_analysis'] ?? [];
        $accounts = $fullAnalysis['accounts'] ?? [];

        foreach ($accounts as $account) {
            $type = strtolower($account['account_type'] ?? $account['type'] ?? '');
            if (in_array($type, ['gia', 'general_investment_account', 'trading'])) {
                // Check for income-producing holdings
                $holdings = $account['holdings'] ?? [];
                foreach ($holdings as $holding) {
                    if (($holding['distribution_type'] ?? '') === 'income' || ($holding['yield'] ?? 0) > 0) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Get user's annual income from module analysis.
     */
    private function getUserAnnualIncome(array $moduleAnalysis): float
    {
        $estate = $moduleAnalysis['estate'] ?? [];
        $monthlyIncome = $estate['monthly_income'] ?? 0;

        if ($monthlyIncome > 0) {
            return $monthlyIncome * 12;
        }

        return 0.0;
    }

    /**
     * Get total pension contributions from retirement analysis.
     */
    private function getPensionContributions(array $retirement): float
    {
        $fullAnalysis = $retirement['full_analysis'] ?? [];

        return (float) ($fullAnalysis['total_contributions'] ?? $fullAnalysis['summary']['total_contributions'] ?? 0);
    }

    /**
     * Get carry forward amount available.
     */
    private function getCarryForwardAvailable(array $retirement, array $taxOptimisation): float
    {
        // Try tax optimisation strategies
        $strategies = $taxOptimisation['strategies'] ?? [];
        foreach ($strategies as $strategy) {
            if (($strategy['type'] ?? '') === 'pension_carry_forward') {
                return (float) ($strategy['carry_forward_available'] ?? $strategy['estimated_annual_saving'] ?? 0);
            }
        }

        // Try retirement data
        $fullAnalysis = $retirement['full_analysis'] ?? [];

        return (float) ($fullAnalysis['carry_forward_available'] ?? $fullAnalysis['summary']['carry_forward_available'] ?? 0);
    }

    /**
     * Check if user has income protection policies.
     */
    private function userHasIncomeProtection(array $protection): bool
    {
        $fullAnalysis = $protection['full_analysis'] ?? [];
        $policies = $fullAnalysis['policies'] ?? $fullAnalysis['existing_cover'] ?? [];

        foreach ($policies as $policy) {
            $type = strtolower($policy['type'] ?? $policy['policy_type'] ?? '');
            if (str_contains($type, 'income') || $type === 'income_protection') {
                return true;
            }
        }

        return false;
    }

    /**
     * Get years until a goal's target date.
     */
    private function getYearsToGoalTarget(array $goal): ?float
    {
        $targetDate = $goal['target_date'] ?? $goal['deadline'] ?? null;

        if ($targetDate) {
            try {
                $target = new \DateTime($targetDate);
                $now = new \DateTime;
                $diff = $now->diff($target);

                return $diff->y + ($diff->m / 12);
            } catch (\Exception) {
                return null;
            }
        }

        $timeframeYears = $goal['timeframe_years'] ?? $goal['years_remaining'] ?? null;

        return $timeframeYears !== null ? (float) $timeframeYears : null;
    }

    /**
     * Determine how a goal is funded (equity, cash, or unknown).
     */
    private function getGoalFundingType(array $goal): string
    {
        $linkedAccountType = strtolower($goal['linked_account_type'] ?? $goal['account_type'] ?? '');

        if (in_array($linkedAccountType, ['stocks_shares_isa', 'gia', 'general_investment_account', 'sipp'])) {
            return 'equity';
        }

        if (in_array($linkedAccountType, ['cash_isa', 'easy_access', 'fixed_term', 'notice', 'regular_saver'])) {
            return 'cash';
        }

        return 'unknown';
    }

    /**
     * Get equity allocation percentage from investment data.
     */
    private function getEquityAllocation(array $investment): float
    {
        $fullAnalysis = $investment['full_analysis'] ?? [];

        // Try asset allocation data
        $assetAllocation = $fullAnalysis['asset_allocation'] ?? [];
        if (isset($assetAllocation['equity'])) {
            return (float) $assetAllocation['equity'] / 100;
        }

        // Try portfolio summary
        $portfolioSummary = $fullAnalysis['portfolio_summary'] ?? [];
        if (isset($portfolioSummary['equity_percentage'])) {
            return (float) $portfolioSummary['equity_percentage'] / 100;
        }

        return 0.0;
    }

    /**
     * Format currency value for display in strategy text.
     */
    private function formatCurrency(float $value): string
    {
        if ($value >= 1000) {
            return '£'.number_format($value, 0);
        }

        return '£'.number_format($value, 2);
    }
}
