<?php

declare(strict_types=1);

namespace App\Services\Tax;

use App\Constants\TaxDefaults;
use App\Models\Investment\InvestmentAccount;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Retirement\AnnualAllowanceChecker;
use App\Services\TaxConfigService;
use App\Traits\ResolvesIncome;

/**
 * Tax Optimisation Service
 *
 * Analyses allowance usage across ISA, pension, and CGT, and generates
 * prioritised tax-saving strategies for a user.
 */
class TaxOptimisationService
{
    use ResolvesIncome;

    public function __construct(
        private readonly TaxConfigService $taxConfig,
        private readonly AnnualAllowanceChecker $allowanceChecker
    ) {}

    /**
     * Analyse all allowance usage for a user.
     *
     * @return array ISA, pension Annual Allowance, CGT, and Personal Savings Allowance usage
     */
    public function analyzeAllowanceUsage(User $user): array
    {
        return [
            'isa' => $this->analyzeISAUsage($user),
            'pension_annual_allowance' => $this->analyzePensionAllowance($user),
            'capital_gains' => $this->analyzeCGTPosition($user),
            'personal_savings_allowance' => $this->analyzePersonalSavingsAllowance($user),
        ];
    }

    /**
     * Generate prioritised tax-saving strategies.
     *
     * @return array Prioritised list of strategies with estimated savings
     */
    public function generateStrategies(User $user): array
    {
        $strategies = [];
        $allowanceUsage = $this->analyzeAllowanceUsage($user);
        $grossIncome = $this->resolveGrossAnnualIncome($user);
        $taxBand = $this->determineTaxBand($grossIncome);

        // ISA strategy
        $isaStrategy = $this->buildISAStrategy($user, $allowanceUsage['isa'], $taxBand);
        if ($isaStrategy !== null) {
            $strategies[] = $isaStrategy;
        }

        // Pension strategy
        $pensionStrategy = $this->buildPensionStrategy($user, $allowanceUsage['pension_annual_allowance'], $grossIncome, $taxBand);
        if ($pensionStrategy !== null) {
            $strategies[] = $pensionStrategy;
        }

        // CGT strategy
        $cgtStrategy = $this->buildCGTStrategy($user, $allowanceUsage['capital_gains'], $taxBand);
        if ($cgtStrategy !== null) {
            $strategies[] = $cgtStrategy;
        }

        // Spousal optimisation (if married)
        $spousalStrategy = $this->buildSpousalStrategy($user, $grossIncome, $taxBand);
        if ($spousalStrategy !== null) {
            $strategies[] = $spousalStrategy;
        }

        // Sort by estimated savings descending
        usort($strategies, fn (array $a, array $b) => $b['estimated_annual_saving'] <=> $a['estimated_annual_saving']);

        // Assign rank
        foreach ($strategies as $index => &$strategy) {
            $strategy['rank'] = $index + 1;
        }
        unset($strategy);

        return [
            'strategies' => $strategies,
            'total_estimated_saving' => round(array_sum(array_column($strategies, 'estimated_annual_saving')), 2),
            'strategy_count' => count($strategies),
        ];
    }

    // =========================================================================
    // ISA Analysis
    // =========================================================================

    private function analyzeISAUsage(User $user): array
    {
        $isaConfig = $this->taxConfig->getISAAllowances();
        $isaAllowance = (float) ($isaConfig['annual_allowance'] ?? TaxDefaults::ISA_ALLOWANCE);

        // Investment ISAs
        $investmentISAs = InvestmentAccount::where('user_id', $user->id)
            ->whereIn('account_type', ['isa', 'stocks_shares_isa'])
            ->get();
        $investmentISASubscribed = (float) $investmentISAs->sum('isa_subscription_current_year');

        // Cash ISAs from savings
        $cashISAs = SavingsAccount::where('user_id', $user->id)
            ->where('account_type', 'isa')
            ->get();
        $cashISASubscribed = (float) $cashISAs->sum('isa_subscription_amount');

        $totalUsed = $investmentISASubscribed + $cashISASubscribed;
        $remaining = max(0, $isaAllowance - $totalUsed);

        // Check if user has non-ISA accounts that could benefit
        $giaValue = InvestmentAccount::where('user_id', $user->id)
            ->where('account_type', 'gia')
            ->sum('current_value');

        $nonISASavings = SavingsAccount::where('user_id', $user->id)
            ->where('account_type', '!=', 'isa')
            ->sum('current_balance');

        return [
            'allowance' => $isaAllowance,
            'used' => round($totalUsed, 2),
            'remaining' => round($remaining, 2),
            'utilisation_percent' => $isaAllowance > 0 ? round(($totalUsed / $isaAllowance) * 100, 1) : 0,
            'has_gia_holdings' => $giaValue > 0,
            'gia_value' => round((float) $giaValue, 2),
            'non_isa_savings' => round((float) $nonISASavings, 2),
        ];
    }

    // =========================================================================
    // Pension Annual Allowance Analysis
    // =========================================================================

    private function analyzePensionAllowance(User $user): array
    {
        $taxYear = $this->taxConfig->getTaxYear();

        $allowanceResult = $this->allowanceChecker->checkAnnualAllowance($user->id, $taxYear);

        return [
            'tax_year' => $taxYear,
            'standard_allowance' => $allowanceResult['standard_allowance'],
            'available_allowance' => $allowanceResult['available_allowance'],
            'total_contributions' => $allowanceResult['total_contributions'],
            'remaining_allowance' => $allowanceResult['remaining_allowance'],
            'carry_forward_available' => $allowanceResult['carry_forward_available'],
            'is_tapered' => $allowanceResult['is_tapered'],
            'has_excess' => $allowanceResult['has_excess'],
        ];
    }

    // =========================================================================
    // CGT Analysis
    // =========================================================================

    private function analyzeCGTPosition(User $user): array
    {
        $cgtConfig = $this->taxConfig->getCapitalGainsTax();
        $annualExempt = (float) ($cgtConfig['annual_exempt_amount'] ?? TaxDefaults::CGT_ANNUAL_EXEMPT);

        // Only GIA holdings have CGT implications
        $giaAccounts = InvestmentAccount::where('user_id', $user->id)
            ->where('account_type', 'gia')
            ->with('holdings')
            ->get();

        $unrealisedGains = 0.0;
        $unrealisedLosses = 0.0;

        foreach ($giaAccounts as $account) {
            foreach ($account->holdings as $holding) {
                if ($holding->cost_basis && $holding->current_value) {
                    $gainLoss = (float) $holding->current_value - (float) $holding->cost_basis;
                    if ($gainLoss > 0) {
                        $unrealisedGains += $gainLoss;
                    } else {
                        $unrealisedLosses += abs($gainLoss);
                    }
                }
            }
        }

        $netGains = $unrealisedGains - $unrealisedLosses;
        $excessGains = max(0, $netGains - $annualExempt);

        return [
            'annual_exempt_amount' => $annualExempt,
            'unrealised_gains' => round($unrealisedGains, 2),
            'unrealised_losses' => round($unrealisedLosses, 2),
            'net_gains' => round($netGains, 2),
            'excess_gains' => round($excessGains, 2),
            'has_harvesting_opportunity' => $unrealisedLosses > 0 && $unrealisedGains > $annualExempt,
        ];
    }

    // =========================================================================
    // Personal Savings Allowance Analysis
    // =========================================================================

    private function analyzePersonalSavingsAllowance(User $user): array
    {
        $grossIncome = $this->resolveGrossAnnualIncome($user);
        $taxBand = $this->determineTaxBand($grossIncome);

        $psaAmount = (float) $this->taxConfig->getPersonalSavingsAllowance($taxBand);

        return [
            'allowance' => $psaAmount,
            'tax_band' => $taxBand,
        ];
    }

    // =========================================================================
    // Strategy Builders
    // =========================================================================

    private function buildISAStrategy(User $user, array $isaUsage, string $taxBand): ?array
    {
        if ($isaUsage['remaining'] <= 0) {
            return null;
        }

        // Calculate estimated saving based on sheltering returns from tax
        $amountToShelter = $isaUsage['remaining'];
        $assumedReturn = 0.06;
        $assumedDividendYield = 0.02;

        $cgtConfig = $this->taxConfig->getCapitalGainsTax();
        $cgtRate = $taxBand === 'basic' ? (float) ($cgtConfig['basic_rate'] ?? 0.10) : (float) ($cgtConfig['higher_rate'] ?? 0.20);
        $dividendConfig = $this->taxConfig->getDividendTax();
        $dividendRate = $taxBand === 'basic' ? (float) ($dividendConfig['basic_rate'] ?? 0.0875) : (float) ($dividendConfig['higher_rate'] ?? 0.3375);

        $growthSaving = $amountToShelter * $assumedReturn * $cgtRate;
        $dividendSaving = $amountToShelter * $assumedDividendYield * $dividendRate;
        $estimatedSaving = round($growthSaving + $dividendSaving, 2);

        $description = sprintf(
            'You have %s of unused ISA allowance this tax year.',
            '£'.number_format($isaUsage['remaining'], 0)
        );

        $action = 'Contribute to a Stocks and Shares ISA or Cash ISA before the end of the tax year';
        if ($isaUsage['has_gia_holdings']) {
            $action = 'Consider a Bed and ISA transfer from your General Investment Account, or make a fresh ISA contribution';
        }

        return [
            'type' => 'isa_allowance',
            'priority' => 'high',
            'title' => 'Use Your ISA Allowance',
            'description' => $description,
            'action' => $action,
            'estimated_annual_saving' => $estimatedSaving,
            'details' => [
                'remaining_allowance' => $isaUsage['remaining'],
                'gia_value' => $isaUsage['gia_value'],
            ],
        ];
    }

    private function buildPensionStrategy(User $user, array $pensionAA, float $grossIncome, string $taxBand): ?array
    {
        $remainingAA = $pensionAA['remaining_allowance'] + $pensionAA['carry_forward_available'];

        if ($remainingAA <= 0 || $taxBand === 'non_taxpayer') {
            return null;
        }

        // Tax relief rate depends on band — use TaxConfigService with TaxDefaults fallback
        $incomeTax = $this->taxConfig->getIncomeTax();
        $basicRate = (float) ($incomeTax['bands'][0]['rate'] ?? 0.20);
        $higherRate = (float) ($incomeTax['bands'][1]['rate'] ?? 0.40);
        $additionalRate = (float) ($incomeTax['bands'][2]['rate'] ?? 0.45);
        $reliefRate = match ($taxBand) {
            'basic' => $basicRate,
            'higher' => $higherRate,
            'additional' => $additionalRate,
            default => $basicRate,
        };

        // Estimate saving if user contributed more
        $suggestedAdditional = min($remainingAA, $grossIncome * 0.10);
        $estimatedSaving = round($suggestedAdditional * $reliefRate, 2);

        $description = sprintf(
            'You have %s of unused pension Annual Allowance (including %s carry forward from prior years).',
            '£'.number_format($remainingAA, 0),
            '£'.number_format($pensionAA['carry_forward_available'], 0)
        );

        $action = $taxBand === 'higher' || $taxBand === 'additional'
            ? 'As a '.$taxBand.'-rate taxpayer, additional pension contributions receive '.((int) ($reliefRate * 100)).'% tax relief'
            : 'Consider increasing pension contributions to benefit from tax relief';

        return [
            'type' => 'pension_annual_allowance',
            'priority' => $taxBand === 'higher' || $taxBand === 'additional' ? 'high' : 'medium',
            'title' => 'Maximise Pension Contributions',
            'description' => $description,
            'action' => $action,
            'estimated_annual_saving' => $estimatedSaving,
            'details' => [
                'remaining_allowance' => $pensionAA['remaining_allowance'],
                'carry_forward' => $pensionAA['carry_forward_available'],
                'tax_relief_rate' => $reliefRate,
                'is_tapered' => $pensionAA['is_tapered'],
            ],
        ];
    }

    private function buildCGTStrategy(User $user, array $cgtPosition, string $taxBand): ?array
    {
        // Only relevant if there are unrealised gains exceeding the exempt amount
        // or if there are losses that could be harvested
        if ($cgtPosition['unrealised_gains'] <= 0 && $cgtPosition['unrealised_losses'] <= 0) {
            return null;
        }

        $cgtConfig = $this->taxConfig->getCapitalGainsTax();
        $cgtRate = $taxBand === 'basic' ? (float) ($cgtConfig['basic_rate'] ?? 0.10) : (float) ($cgtConfig['higher_rate'] ?? 0.20);

        if ($cgtPosition['has_harvesting_opportunity']) {
            $harvestable = min($cgtPosition['unrealised_losses'], $cgtPosition['excess_gains']);
            $estimatedSaving = round($harvestable * $cgtRate, 2);

            return [
                'type' => 'cgt_loss_harvesting',
                'priority' => 'medium',
                'title' => 'Tax-Loss Harvesting Opportunity',
                'description' => sprintf(
                    'You have %s in unrealised losses that could offset %s in gains above your annual exempt amount.',
                    '£'.number_format($cgtPosition['unrealised_losses'], 0),
                    '£'.number_format($cgtPosition['excess_gains'], 0)
                ),
                'action' => 'Consider realising losses to offset gains and reduce your Capital Gains Tax liability',
                'estimated_annual_saving' => $estimatedSaving,
                'details' => [
                    'unrealised_losses' => $cgtPosition['unrealised_losses'],
                    'excess_gains' => $cgtPosition['excess_gains'],
                    'cgt_rate' => $cgtRate,
                ],
            ];
        }

        if ($cgtPosition['excess_gains'] > 0) {
            $potentialTax = round($cgtPosition['excess_gains'] * $cgtRate, 2);

            return [
                'type' => 'cgt_planning',
                'priority' => 'medium',
                'title' => 'Capital Gains Tax Planning',
                'description' => sprintf(
                    'You have %s in unrealised gains above your annual exempt amount, which could attract %s in Capital Gains Tax if realised.',
                    '£'.number_format($cgtPosition['excess_gains'], 0),
                    '£'.number_format($potentialTax, 0)
                ),
                'action' => 'Consider staged realisation across tax years or transferring assets to an ISA wrapper',
                'estimated_annual_saving' => 0,
                'details' => [
                    'excess_gains' => $cgtPosition['excess_gains'],
                    'potential_tax' => $potentialTax,
                    'cgt_rate' => $cgtRate,
                ],
            ];
        }

        return null;
    }

    private function buildSpousalStrategy(User $user, float $grossIncome, string $taxBand): ?array
    {
        if ($user->marital_status !== 'married' || ! $user->spouse_id) {
            return null;
        }

        $spouse = User::find($user->spouse_id);
        if (! $spouse) {
            return null;
        }

        $spouseIncome = $this->resolveGrossAnnualIncome($spouse);
        $spouseTaxBand = $this->determineTaxBand($spouseIncome);

        // Only suggest if there is a tax band difference
        if ($taxBand === $spouseTaxBand) {
            return null;
        }

        // Higher earner should be the one receiving the recommendation
        $higherEarner = $grossIncome >= $spouseIncome ? $user : $spouse;
        $lowerEarner = $grossIncome >= $spouseIncome ? $spouse : $user;
        $higherBand = $grossIncome >= $spouseIncome ? $taxBand : $spouseTaxBand;
        $lowerBand = $grossIncome >= $spouseIncome ? $spouseTaxBand : $taxBand;

        $estimatedSaving = 0.0;
        $actions = [];

        // Marriage Allowance check (basic rate to non-taxpayer transfer)
        $incomeTaxConfig = $this->taxConfig->getIncomeTax();
        $personalAllowance = (float) ($incomeTaxConfig['personal_allowance'] ?? TaxDefaults::PERSONAL_ALLOWANCE);

        $lowerEarnerIncome = $grossIncome >= $spouseIncome ? $spouseIncome : $grossIncome;
        if ($lowerEarnerIncome < $personalAllowance && $higherBand === 'basic') {
            $marriageAllowanceSaving = round($personalAllowance * 0.10 * 0.20, 2); // 10% of PA at 20%
            $estimatedSaving += $marriageAllowanceSaving;
            $actions[] = 'Apply for Marriage Allowance to transfer unused personal allowance';
        }

        // Check ISA usage for both partners
        $spouseISAUsed = InvestmentAccount::where('user_id', $spouse->id)
            ->whereIn('account_type', ['isa', 'stocks_shares_isa'])
            ->sum('isa_subscription_current_year');
        $spouseISAUsed += SavingsAccount::where('user_id', $spouse->id)
            ->where('account_type', 'isa')
            ->sum('isa_subscription_amount');

        $isaConfig = $this->taxConfig->getISAAllowances();
        $isaAllowance = (float) ($isaConfig['annual_allowance'] ?? TaxDefaults::ISA_ALLOWANCE);
        $spouseISARemaining = max(0, $isaAllowance - (float) $spouseISAUsed);

        if ($spouseISARemaining > 0) {
            $actions[] = sprintf(
                'Your spouse has %s of unused ISA allowance -- consider funding their ISA',
                '£'.number_format($spouseISARemaining, 0)
            );
        }

        // Income shifting suggestion for higher/basic band gap
        if ($higherBand === 'higher' && $lowerBand === 'basic') {
            $actions[] = 'Consider transferring income-producing assets to the lower-rate spouse to reduce overall tax';
            if ($estimatedSaving === 0.0) {
                $estimatedSaving = 200.0; // Conservative estimate
            }
        }

        if (empty($actions)) {
            return null;
        }

        return [
            'type' => 'spousal_optimisation',
            'priority' => 'low',
            'title' => 'Spousal Tax Optimisation',
            'description' => sprintf(
                'You and your spouse are in different tax bands (%s rate vs %s rate), creating opportunities to reduce your household tax bill.',
                $higherBand,
                $lowerBand
            ),
            'action' => implode('. ', $actions),
            'estimated_annual_saving' => round($estimatedSaving, 2),
            'details' => [
                'user_tax_band' => $taxBand,
                'spouse_tax_band' => $spouseTaxBand,
                'spouse_isa_remaining' => $spouseISARemaining,
            ],
        ];
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function determineTaxBand(float $grossIncome): string
    {
        $incomeTax = $this->taxConfig->getIncomeTax();
        $personalAllowance = (float) ($incomeTax['personal_allowance'] ?? 12570);
        $basicRateLimit = $personalAllowance + (float) ($incomeTax['bands'][0]['max'] ?? 37700);
        $additionalThreshold = (float) ($incomeTax['additional_rate_threshold'] ?? 125140);

        if ($grossIncome <= $personalAllowance) {
            return 'non_taxpayer';
        }
        if ($grossIncome <= $basicRateLimit) {
            return 'basic';
        }
        if ($grossIncome <= $additionalThreshold) {
            return 'higher';
        }

        return 'additional';
    }
}
