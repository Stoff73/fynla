<?php

declare(strict_types=1);

namespace App\Services\Investment\AssetLocation;

use App\Models\Investment\Holding;
use App\Models\Investment\InvestmentAccount;
use App\Services\Risk\RiskPreferenceService;
use App\Services\TaxConfigService;
use App\Services\UKTaxCalculator;

/**
 * Tax Drag Calculator
 * Calculates the annual tax impact of holding assets in different account types
 *
 * Tax drag is the reduction in returns due to taxation.
 * Different account types have different tax treatments:
 * - ISA: No tax on income, dividends, or capital gains (0% tax drag)
 * - GIA: Income tax on interest/dividends, CGT on gains (high tax drag)
 * - Pension: Tax-deferred growth, but taxed on withdrawal (medium tax drag)
 */
class TaxDragCalculator
{
    public function __construct(
        private readonly UKTaxCalculator $taxCalculator,
        private readonly TaxConfigService $taxConfig,
        private readonly RiskPreferenceService $riskPreferenceService
    ) {}

    /**
     * Get default expected return from risk preference service
     */
    private function getDefaultExpectedReturn(): float
    {
        return $this->riskPreferenceService->getReturnParameters('medium')['expected_return_typical'] / 100;
    }

    /**
     * Calculate tax drag for a holding in its current account type
     *
     * @param  Holding  $holding  Holding to analyze
     * @param  array  $userTaxProfile  User's tax information (income, tax_rate, etc.)
     * @return array Tax drag analysis
     */
    public function calculateCurrentTaxDrag(Holding $holding, array $userTaxProfile): array
    {
        // Use polymorphic relationship to get the account
        $account = $holding->holdable;

        // Get account type - handle both InvestmentAccount and DCPension
        $accountType = $account->account_type ?? 'sipp'; // DCPension defaults to SIPP

        return $this->calculateTaxDragByAccountType(
            $holding,
            $accountType,
            $userTaxProfile
        );
    }

    /**
     * Calculate tax drag if holding was in a different account type
     *
     * @param  Holding  $holding  Holding to analyze
     * @param  string  $accountType  Account type (isa, gia, sipp, personal_pension)
     * @param  array  $userTaxProfile  User's tax information
     * @return array Tax drag analysis
     */
    public function calculateTaxDragByAccountType(
        Holding $holding,
        string $accountType,
        array $userTaxProfile
    ): array {
        $value = $holding->current_value;
        $expectedReturn = $userTaxProfile['expected_return'] ?? $this->getDefaultExpectedReturn();
        $dividendYield = $this->estimateDividendYield($holding);
        $interestRate = $this->estimateInterestRate($holding);

        // Calculate annual returns
        $annualCapitalGain = $value * ($expectedReturn - $dividendYield - $interestRate);
        $annualDividend = $value * $dividendYield;
        $annualInterest = $value * $interestRate;

        // Calculate tax based on account type
        $taxAmount = match ($accountType) {
            'isa', 'stocks_shares_isa', 'cash_isa', 'lifetime_isa' => 0, // Tax-free
            'sipp', 'personal_pension' => $this->calculatePensionTaxDrag(
                $annualCapitalGain,
                $annualDividend,
                $annualInterest,
                $userTaxProfile
            ),
            'gia', 'general_investment_account' => $this->calculateGIATaxDrag(
                $annualCapitalGain,
                $annualDividend,
                $annualInterest,
                $userTaxProfile
            ),
            default => $this->calculateGIATaxDrag(
                $annualCapitalGain,
                $annualDividend,
                $annualInterest,
                $userTaxProfile
            ),
        };

        $annualReturn = $annualCapitalGain + $annualDividend + $annualInterest;
        $taxDragPercent = $annualReturn > 0 ? ($taxAmount / $annualReturn) * 100 : 0;

        return [
            'account_type' => $accountType,
            'holding_value' => $value,
            'annual_return' => $annualReturn,
            'breakdown' => [
                'capital_gain' => $annualCapitalGain,
                'dividend_income' => $annualDividend,
                'interest_income' => $annualInterest,
            ],
            'tax_amount' => $taxAmount,
            'tax_drag_percent' => $taxDragPercent,
            'after_tax_return' => $annualReturn - $taxAmount,
            'after_tax_return_percent' => (($annualReturn - $taxAmount) / $value) * 100,
        ];
    }

    /**
     * Calculate tax drag for GIA (General Investment Account)
     *
     * @param  float  $capitalGain  Annual capital gain
     * @param  float  $dividend  Annual dividend income
     * @param  float  $interest  Annual interest income
     * @param  array  $userTaxProfile  User tax information
     * @return float Total tax amount
     */
    private function calculateGIATaxDrag(
        float $capitalGain,
        float $dividend,
        float $interest,
        array $userTaxProfile
    ): float {
        $incomeTaxRate = $userTaxProfile['income_tax_rate'] ?? 0.20;
        $cgtRate = $userTaxProfile['cgt_rate'] ?? 0.20;

        // Get CGT allowance from tax configuration
        $cgtConfig = $this->taxConfig->getCapitalGainsTax();
        $cgtAllowance = $cgtConfig['annual_exempt_amount'] ?? 3000;
        $cgtAllowanceUsed = $userTaxProfile['cgt_allowance_used'] ?? 0;
        $remainingCGTAllowance = max(0, $cgtAllowance - $cgtAllowanceUsed);

        // Get Dividend allowance from tax configuration
        $dividendConfig = $this->taxConfig->getDividendTax();
        $dividendAllowance = $dividendConfig['allowance'] ?? 500;
        $dividendAllowanceUsed = $userTaxProfile['dividend_allowance_used'] ?? 0;
        $remainingDividendAllowance = max(0, $dividendAllowance - $dividendAllowanceUsed);

        // Personal Savings Allowance sourced from TaxConfigService
        $psaBand = match (true) {
            $incomeTaxRate <= 0.20 => 'basic',
            $incomeTaxRate <= 0.40 => 'higher',
            default => 'additional',
        };
        $personalSavingsAllowance = $this->taxConfig->getPersonalSavingsAllowance($psaBand);
        $psaUsed = $userTaxProfile['psa_used'] ?? 0;
        $remainingPSA = max(0, $personalSavingsAllowance - $psaUsed);

        // Calculate taxable amounts
        $taxableCapitalGain = max(0, $capitalGain - $remainingCGTAllowance);
        $taxableDividend = max(0, $dividend - $remainingDividendAllowance);
        $taxableInterest = max(0, $interest - $remainingPSA);

        // Calculate tax
        $cgtTax = $taxableCapitalGain * $cgtRate;

        // Dividend tax rates
        $dividendTaxRate = match (true) {
            $incomeTaxRate <= 0.20 => 0.0875, // Basic rate: 8.75%
            $incomeTaxRate <= 0.40 => 0.3375, // Higher rate: 33.75%
            default => 0.3935, // Additional rate: 39.35%
        };
        $dividendTax = $taxableDividend * $dividendTaxRate;

        $interestTax = $taxableInterest * $incomeTaxRate;

        return $cgtTax + $dividendTax + $interestTax;
    }

    /**
     * Calculate tax drag for pension accounts
     * Pensions are tax-deferred, but withdrawals are taxed
     * We calculate the present value of future tax liability
     *
     * @param  float  $capitalGain  Annual capital gain
     * @param  float  $dividend  Annual dividend income
     * @param  float  $interest  Annual interest income
     * @param  array  $userTaxProfile  User tax information
     * @return float Present value of future tax
     */
    private function calculatePensionTaxDrag(
        float $capitalGain,
        float $dividend,
        float $interest,
        array $userTaxProfile
    ): float {
        // No tax during accumulation
        // But we need to account for future tax on withdrawal

        $yearsToRetirement = $userTaxProfile['years_to_retirement'] ?? 20;
        $expectedWithdrawalTaxRate = $userTaxProfile['expected_withdrawal_tax_rate'] ?? 0.20;

        // 25% tax-free lump sum
        $taxablePortionOnWithdrawal = 0.75;

        // Discount future tax to present value (using expected return as discount rate)
        $discountRate = $userTaxProfile['expected_return'] ?? $this->getDefaultExpectedReturn();
        $discountFactor = 1 / pow(1 + $discountRate, $yearsToRetirement);

        $totalReturn = $capitalGain + $dividend + $interest;
        $futureTax = $totalReturn * $taxablePortionOnWithdrawal * $expectedWithdrawalTaxRate;
        $presentValueOfTax = $futureTax * $discountFactor;

        return $presentValueOfTax;
    }

    /**
     * Compare tax drag across all account types for a holding
     *
     * @param  Holding  $holding  Holding to analyze
     * @param  array  $userTaxProfile  User tax information
     * @return array Comparison across account types
     */
    public function compareAccountTypes(Holding $holding, array $userTaxProfile): array
    {
        $accountTypes = ['isa', 'gia', 'sipp'];
        $comparison = [];

        foreach ($accountTypes as $accountType) {
            $comparison[$accountType] = $this->calculateTaxDragByAccountType(
                $holding,
                $accountType,
                $userTaxProfile
            );
        }

        // Calculate potential savings
        $account = $holding->holdable;
        $currentAccountType = $account->account_type ?? 'sipp';
        $currentTax = $comparison[$currentAccountType]['tax_amount'] ?? $comparison['gia']['tax_amount'];

        // Find best account type (lowest tax)
        $bestAccountType = 'isa'; // ISA is always best (0% tax)
        $bestTax = $comparison['isa']['tax_amount'];

        $potentialSaving = $currentTax - $bestTax;

        return [
            'current_account_type' => $currentAccountType,
            'current_tax_drag' => $currentTax,
            'comparison' => $comparison,
            'best_account_type' => $bestAccountType,
            'best_tax_drag' => $bestTax,
            'potential_annual_saving' => $potentialSaving,
            'potential_10_year_saving' => $potentialSaving * 10 * 1.15, // With compounding
        ];
    }

    /**
     * Estimate dividend yield for a holding based on asset type
     *
     * @param  Holding  $holding  Holding
     * @return float Estimated dividend yield (0-1)
     */
    private function estimateDividendYield(Holding $holding): float
    {
        // Use holding's dividend yield if available
        if (isset($holding->dividend_yield) && $holding->dividend_yield > 0) {
            return $holding->dividend_yield;
        }

        // Estimate based on asset type using TaxConfigService yields
        $yields = $this->taxConfig->get('investment.asset_class_yields', []);

        return match ($holding->asset_type) {
            'equity', 'stock' => $yields['global_equity']['income_yield'] ?? 0.02,
            'uk_equity' => $yields['uk_equity']['income_yield'] ?? 0.035,
            'bond', 'fixed_income' => $yields['bonds']['income_yield'] ?? 0.04,
            'reit', 'property' => $yields['property']['income_yield'] ?? 0.03,
            'preferred_stock' => 0.05,
            'cash', 'money_market' => $yields['cash']['income_yield'] ?? 0.04,
            default => $yields['global_equity']['income_yield'] ?? 0.02,
        };
    }

    /**
     * Estimate interest rate for a holding based on asset type
     *
     * @param  Holding  $holding  Holding
     * @return float Estimated interest rate (0-1)
     */
    private function estimateInterestRate(Holding $holding): float
    {
        return match ($holding->asset_type) {
            'bond', 'fixed_income' => 0.04, // 4% for bonds
            'cash', 'money_market' => 0.045, // 4.5% for cash (2024/25 rates)
            default => 0.0, // No interest for equities
        };
    }

    /**
     * Calculate portfolio-wide tax drag
     *
     * @param  int  $userId  User ID
     * @param  array  $userTaxProfile  User tax information
     * @return array Portfolio tax drag analysis
     */
    public function calculatePortfolioTaxDrag(int $userId, array $userTaxProfile): array
    {
        $accounts = InvestmentAccount::where('user_id', $userId)
            ->with('holdings')
            ->get();

        $totalValue = 0;
        $totalTaxDrag = 0;
        $accountBreakdown = [];

        foreach ($accounts as $account) {
            $accountTaxDrag = 0;
            $accountValue = 0;

            foreach ($account->holdings as $holding) {
                if (! $holding->current_value) {
                    continue;
                }

                $taxDrag = $this->calculateCurrentTaxDrag($holding, $userTaxProfile);
                $accountTaxDrag += $taxDrag['tax_amount'];
                $accountValue += $holding->current_value;
            }

            $totalValue += $accountValue;
            $totalTaxDrag += $accountTaxDrag;

            $accountBreakdown[] = [
                'account_id' => $account->id,
                'account_type' => $account->account_type,
                'account_value' => $accountValue,
                'tax_drag' => $accountTaxDrag,
                'tax_drag_percent' => $accountValue > 0 ? ($accountTaxDrag / $accountValue) * 100 : 0,
            ];
        }

        return [
            'total_portfolio_value' => $totalValue,
            'total_annual_tax_drag' => $totalTaxDrag,
            'average_tax_drag_percent' => $totalValue > 0 ? ($totalTaxDrag / $totalValue) * 100 : 0,
            'accounts' => $accountBreakdown,
        ];
    }
}
