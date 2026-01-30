<?php

declare(strict_types=1);

namespace App\Services\Retirement;

use App\Models\DBPension;
use App\Models\DCPension;
use App\Models\Investment\InvestmentAccount;
use App\Models\RetirementProfile;
use App\Models\SavingsAccount;
use App\Models\StatePension;
use App\Models\User;
use App\Services\TaxBandTracker;
use App\Services\TaxConfigService;

/**
 * Retirement Income Service
 *
 * Calculates tax-optimized retirement income drawdown strategies,
 * projects fund depletion over time, and provides real-time tax calculations.
 */
class RetirementIncomeService
{
    private const DEFAULT_RETIREMENT_AGE = 68;

    private const DEFAULT_GROWTH_RATE = 0.04;

    private const DEFAULT_INFLATION_RATE = 0.02;

    private const PROJECTION_END_AGE = 100;

    // Sustainable withdrawal rates
    private const ISA_WITHDRAWAL_RATE = 0.047; // 4.7% sustainable withdrawal

    private const BOND_TAX_FREE_RATE = 0.05; // 5% cumulative tax-free allowance

    private const GIA_WITHDRAWAL_RATE = 0.04; // 4% sustainable withdrawal

    public function __construct(
        private TaxConfigService $taxConfig,
        private DecumulationPlanner $decumulationPlanner,
    ) {}

    /**
     * Get retirement income configuration with default tax-optimized allocations.
     */
    public function getRetirementIncomeConfig(int $userId, bool $includeSpouse = false): array
    {
        $user = User::findOrFail($userId);
        $profile = RetirementProfile::where('user_id', $userId)->first();

        $retirementAge = $profile?->target_retirement_age ?? self::DEFAULT_RETIREMENT_AGE;
        $targetIncome = (float) ($profile?->target_retirement_income ?? $this->calculateDefaultTargetIncome($user));

        $availableAccounts = $this->getAvailableAccounts($userId, $includeSpouse);
        $defaultAllocations = $this->calculateDefaultAllocations($availableAccounts, $targetIncome, $retirementAge);
        $taxBreakdown = $this->calculateTaxBreakdown($defaultAllocations);
        $fundProjections = $this->projectFundDepletion($userId, $defaultAllocations, $retirementAge);

        return [
            'target_income' => round($targetIncome, 2),
            'retirement_age' => $retirementAge,
            'current_age' => $user->date_of_birth ? $user->date_of_birth->age : null,
            'include_spouse' => $includeSpouse,
            'available_accounts' => $availableAccounts,
            'allocations' => $defaultAllocations,
            'tax_breakdown' => $taxBreakdown,
            'fund_projections' => $fundProjections['projections'],
            'depletion_ages' => $fundProjections['depletion_ages'],
        ];
    }

    /**
     * Calculate income scenario based on user-specified allocations.
     */
    public function calculateIncomeScenario(int $userId, array $incomeAllocations, ?float $customTargetIncome = null, bool $includeSpouse = false): array
    {
        $user = User::findOrFail($userId);
        $profile = RetirementProfile::where('user_id', $userId)->first();

        $retirementAge = $profile?->target_retirement_age ?? self::DEFAULT_RETIREMENT_AGE;
        $targetIncome = (float) ($customTargetIncome ?? $profile?->target_retirement_income ?? $this->calculateDefaultTargetIncome($user));

        // Include available accounts so they're not lost after recalculation
        $availableAccounts = $this->getAvailableAccounts($userId, $includeSpouse);
        $taxBreakdown = $this->calculateTaxBreakdown($incomeAllocations);
        $fundProjections = $this->projectFundDepletion($userId, $incomeAllocations, $retirementAge);

        return [
            'target_income' => round($targetIncome, 2),
            'retirement_age' => $retirementAge,
            'available_accounts' => $availableAccounts,
            'allocations' => $incomeAllocations,
            'tax_breakdown' => $taxBreakdown,
            'fund_projections' => $fundProjections['projections'],
            'depletion_ages' => $fundProjections['depletion_ages'],
            'meets_target' => $taxBreakdown['net_income'] >= $targetIncome,
            'income_gap' => max(0, $targetIncome - $taxBreakdown['net_income']),
        ];
    }

    /**
     * Get all accounts eligible for retirement income.
     */
    public function getAvailableAccounts(int $userId, bool $includeSpouse = false): array
    {
        $accounts = [];

        // Get user IDs to query
        $userIds = [$userId];
        if ($includeSpouse) {
            $spouse = User::find($userId)?->spouse;
            if ($spouse) {
                $userIds[] = $spouse->id;
            }
        }

        // DC Pensions
        $dcPensions = DCPension::whereIn('user_id', $userIds)->get();
        foreach ($dcPensions as $pension) {
            $value = (float) ($pension->current_fund_value ?? 0);
            $pclsAvailable = $value * 0.25;

            $accounts[] = [
                'id' => $pension->id,
                'type' => 'dc_pension',
                'owner_id' => $pension->user_id,
                'name' => $pension->scheme_name ?? 'DC Pension',
                'provider' => $pension->provider,
                'value' => round($value, 2),
                'pcls_available' => round($pclsAvailable, 2),
                'annual_contribution' => (float) ($pension->monthly_contribution_amount ?? 0) * 12,
                'tax_treatment' => 'taxable',
                'sub_accounts' => [
                    [
                        'source_type' => 'dc_pension_pcls',
                        'source_id' => $pension->id,
                        'name' => ($pension->scheme_name ?? 'DC Pension').' - Tax-Free Cash (PCLS)',
                        'max_amount' => round($pclsAvailable, 2),
                        'tax_rate' => 0,
                        'tax_treatment' => 'tax_free',
                    ],
                    [
                        'source_type' => 'dc_pension_drawdown',
                        'source_id' => $pension->id,
                        'name' => ($pension->scheme_name ?? 'DC Pension').' - Drawdown',
                        'max_amount' => round($value - $pclsAvailable, 2),
                        'tax_rate' => null, // Depends on total income
                        'tax_treatment' => 'taxable',
                    ],
                ],
            ];
        }

        // DB Pensions
        $dbPensions = DBPension::whereIn('user_id', $userIds)->get();
        foreach ($dbPensions as $pension) {
            $annualIncome = (float) ($pension->accrued_annual_pension ?? 0);
            $accounts[] = [
                'id' => $pension->id,
                'type' => 'db_pension',
                'owner_id' => $pension->user_id,
                'name' => $pension->scheme_name ?? 'DB Pension',
                'provider' => $pension->employer,
                'value' => null, // DB pensions don't have a pot value
                'annual_income' => round($annualIncome, 2),
                'payment_start_age' => $pension->normal_retirement_age,
                'lump_sum_entitlement' => (float) ($pension->lump_sum_entitlement ?? 0),
                'tax_treatment' => 'taxable',
                'source_type' => 'db_pension',
                'source_id' => $pension->id,
            ];
        }

        // State Pension
        $statePensions = StatePension::whereIn('user_id', $userIds)->get();
        foreach ($statePensions as $pension) {
            $annualIncome = (float) ($pension->state_pension_forecast_annual ?? 0);
            $accounts[] = [
                'id' => $pension->id,
                'type' => 'state_pension',
                'owner_id' => $pension->user_id,
                'name' => 'State Pension',
                'value' => null,
                'annual_income' => round($annualIncome, 2),
                'payment_start_age' => $pension->state_pension_age ?? 67,
                'already_receiving' => (bool) $pension->already_receiving,
                'tax_treatment' => 'taxable',
                'source_type' => 'state_pension',
                'source_id' => $pension->id,
            ];
        }

        // ISAs (Savings - Cash ISA)
        $isaAccounts = SavingsAccount::whereIn('user_id', $userIds)
            ->where('is_isa', true)
            ->get();
        foreach ($isaAccounts as $account) {
            $value = (float) ($account->current_balance ?? 0);
            $accounts[] = [
                'id' => $account->id,
                'type' => 'isa_cash',
                'owner_id' => $account->user_id,
                'name' => $account->institution ?? 'Cash ISA',
                'value' => round($value, 2),
                'isa_type' => $account->isa_type,
                'tax_rate' => 0,
                'tax_treatment' => 'tax_free',
                'source_type' => 'isa',
                'source_id' => $account->id,
            ];
        }

        // ISAs (Investment - Stocks & Shares ISA)
        // Only include accounts marked for retirement planning
        $investmentIsas = InvestmentAccount::whereIn('user_id', $userIds)
            ->where('include_in_retirement', true)
            ->where(function ($query) {
                $query->where('account_type', 'isa')
                    ->orWhere('account_type', 'stocks_shares_isa')
                    ->orWhere('account_type', 'lifetime_isa');
            })
            ->get();
        foreach ($investmentIsas as $account) {
            $value = (float) ($account->current_value ?? 0);
            $accounts[] = [
                'id' => $account->id,
                'type' => 'isa_investment',
                'owner_id' => $account->user_id,
                'name' => $account->provider ?? 'Stocks & Shares ISA',
                'platform' => $account->platform,
                'value' => round($value, 2),
                'isa_type' => $account->isa_type ?? 'stocks_shares',
                'tax_rate' => 0,
                'tax_treatment' => 'tax_free',
                'source_type' => 'isa',
                'source_id' => $account->id,
            ];
        }

        // Onshore Bonds - 5% cumulative tax-free withdrawal
        // Only include accounts marked for retirement planning
        $onshoreBonds = InvestmentAccount::whereIn('user_id', $userIds)
            ->where('include_in_retirement', true)
            ->where('account_type', 'onshore_bond')
            ->get();
        foreach ($onshoreBonds as $account) {
            $value = (float) ($account->current_value ?? 0);
            // Original investment for 5% calculation (fallback to current value if not set)
            $originalInvestment = (float) ($account->investment_amount ?? $value);
            // 5% annual tax-free allowance of original investment
            $annualTaxFreeAllowance = $originalInvestment * self::BOND_TAX_FREE_RATE;

            $accounts[] = [
                'id' => $account->id,
                'type' => 'onshore_bond',
                'owner_id' => $account->user_id,
                'name' => $account->provider ?? 'Onshore Bond',
                'provider' => $account->provider,
                'value' => round($value, 2),
                'original_investment' => round($originalInvestment, 2),
                'annual_tax_free_allowance' => round($annualTaxFreeAllowance, 2),
                'tax_rate' => 0, // Within 5% allowance
                'tax_treatment' => 'tax_deferred',
                'source_type' => 'onshore_bond',
                'source_id' => $account->id,
            ];
        }

        // Offshore Bonds - 5% cumulative tax-free withdrawal with gross roll-up
        // Only include accounts marked for retirement planning
        $offshoreBonds = InvestmentAccount::whereIn('user_id', $userIds)
            ->where('include_in_retirement', true)
            ->where('account_type', 'offshore_bond')
            ->get();
        foreach ($offshoreBonds as $account) {
            $value = (float) ($account->current_value ?? 0);
            // Original investment for 5% calculation (fallback to current value if not set)
            $originalInvestment = (float) ($account->investment_amount ?? $value);
            // 5% annual tax-free allowance of original investment
            $annualTaxFreeAllowance = $originalInvestment * self::BOND_TAX_FREE_RATE;

            $accounts[] = [
                'id' => $account->id,
                'type' => 'offshore_bond',
                'owner_id' => $account->user_id,
                'name' => $account->provider ?? 'Offshore Bond',
                'provider' => $account->provider,
                'value' => round($value, 2),
                'original_investment' => round($originalInvestment, 2),
                'annual_tax_free_allowance' => round($annualTaxFreeAllowance, 2),
                'tax_rate' => 0, // Within 5% allowance
                'tax_treatment' => 'tax_deferred',
                'source_type' => 'offshore_bond',
                'source_id' => $account->id,
            ];
        }

        // GIAs (General Investment Accounts)
        // Only include accounts marked for retirement planning
        $giaAccounts = InvestmentAccount::whereIn('user_id', $userIds)
            ->where('include_in_retirement', true)
            ->where(function ($query) {
                $query->where('account_type', 'gia')
                    ->orWhere('account_type', 'general');
            })
            ->get();
        foreach ($giaAccounts as $account) {
            $value = (float) ($account->current_value ?? 0);
            $accounts[] = [
                'id' => $account->id,
                'type' => 'gia',
                'owner_id' => $account->user_id,
                'name' => $account->provider ?? 'General Investment Account',
                'platform' => $account->platform,
                'value' => round($value, 2),
                'tax_rate' => null, // Depends on total income
                'tax_treatment' => 'taxable',
                'source_type' => 'gia',
                'source_id' => $account->id,
            ];
        }

        // Non-ISA Savings
        $savingsAccounts = SavingsAccount::whereIn('user_id', $userIds)
            ->where(function ($query) {
                $query->where('is_isa', false)
                    ->orWhereNull('is_isa');
            })
            ->get();
        foreach ($savingsAccounts as $account) {
            $value = (float) ($account->current_balance ?? 0);
            $accounts[] = [
                'id' => $account->id,
                'type' => 'savings',
                'owner_id' => $account->user_id,
                'name' => $account->institution ?? 'Savings Account',
                'value' => round($value, 2),
                'interest_rate' => (float) ($account->interest_rate ?? 0),
                'tax_rate' => null, // PSA may apply
                'tax_treatment' => 'taxable',
                'source_type' => 'savings',
                'source_id' => $account->id,
            ];
        }

        return $accounts;
    }

    /**
     * Calculate tax breakdown based on income allocations.
     * Applies income in tax-efficient order: PCLS -> Personal Allowance -> ISA -> Taxable
     */
    public function calculateTaxBreakdown(array $incomeAllocations): array
    {
        $incomeTaxConfig = $this->taxConfig->getIncomeTax();
        $tracker = new TaxBandTracker($incomeTaxConfig);

        $breakdown = [
            'sources' => [],
            'pcls_total' => 0,
            'tax_free_total' => 0,
            'taxable_total' => 0,
            'personal_allowance_used' => 0,
            'basic_rate_used' => 0,
            'higher_rate_used' => 0,
            'additional_rate_used' => 0,
            'basic_rate_total' => 0,
            'higher_rate_total' => 0,
            'additional_rate_total' => 0,
            'total_tax' => 0,
            'gross_income' => 0,
            'net_income' => 0,
            'effective_rate' => 0,
            'band_usage' => [],
        ];

        // Sort allocations by tax efficiency (tax-free first)
        $sortedAllocations = $this->sortByTaxEfficiency($incomeAllocations);

        foreach ($sortedAllocations as $allocation) {
            $amount = (float) ($allocation['annual_amount'] ?? 0);
            $taxTreatment = $allocation['tax_treatment'] ?? 'taxable';
            $sourceType = $allocation['source_type'] ?? 'unknown';

            $breakdown['gross_income'] += $amount;

            $sourceBreakdown = [
                'source_type' => $sourceType,
                'source_id' => $allocation['source_id'] ?? null,
                'name' => $allocation['name'] ?? $sourceType,
                'amount' => round($amount, 2),
                'tax_treatment' => $taxTreatment,
                'tax' => 0,
                'effective_rate' => 0,
            ];

            if ($taxTreatment === 'tax_free' || $taxTreatment === 'pcls') {
                // PCLS and ISA are tax-free
                $sourceBreakdown['tax'] = 0;
                $sourceBreakdown['effective_rate'] = 0;
                $breakdown['tax_free_total'] += $amount;

                if ($taxTreatment === 'pcls') {
                    $breakdown['pcls_total'] += $amount;
                }
            } else {
                // Taxable income - use tracker to allocate to bands
                $breakdown['taxable_total'] += $amount;
                $taxAllocation = $tracker->allocateIncome($amount);

                $sourceBreakdown['tax'] = $taxAllocation['total_income_tax'];
                $sourceBreakdown['effective_rate'] = $amount > 0 ? $taxAllocation['total_income_tax'] / $amount : 0;
                $sourceBreakdown['band_breakdown'] = [
                    'personal_allowance' => $taxAllocation['personal_allowance_used'],
                    'basic_rate' => $taxAllocation['basic_rate']['taxable'],
                    'higher_rate' => $taxAllocation['higher_rate']['taxable'],
                    'additional_rate' => $taxAllocation['additional_rate']['taxable'],
                ];

                $breakdown['personal_allowance_used'] += $taxAllocation['personal_allowance_used'];
                $breakdown['basic_rate_used'] += $taxAllocation['basic_rate']['taxable'];
                $breakdown['higher_rate_used'] += $taxAllocation['higher_rate']['taxable'];
                $breakdown['additional_rate_used'] += $taxAllocation['additional_rate']['taxable'];
                $breakdown['basic_rate_total'] += $taxAllocation['basic_rate']['tax'];
                $breakdown['higher_rate_total'] += $taxAllocation['higher_rate']['tax'];
                $breakdown['additional_rate_total'] += $taxAllocation['additional_rate']['tax'];
                $breakdown['total_tax'] += $taxAllocation['total_income_tax'];
            }

            $breakdown['sources'][] = $sourceBreakdown;
        }

        $breakdown['net_income'] = $breakdown['gross_income'] - $breakdown['total_tax'];
        $breakdown['effective_rate'] = $breakdown['gross_income'] > 0
            ? round($breakdown['total_tax'] / $breakdown['gross_income'], 4)
            : 0;

        // Add band usage summary
        $taxConfig = $tracker->getConfig();
        $basicRateLimit = $taxConfig['basic_rate_limit'] - $taxConfig['personal_allowance'];
        $higherRateLimit = $taxConfig['higher_rate_limit'] - $taxConfig['basic_rate_limit'];

        $breakdown['band_usage'] = [
            'personal_allowance' => [
                'limit' => $taxConfig['personal_allowance'],
                'used' => round($breakdown['personal_allowance_used'], 2),
                'remaining' => round(max(0, $taxConfig['personal_allowance'] - $breakdown['personal_allowance_used']), 2),
            ],
            'basic_rate' => [
                'limit' => $basicRateLimit,
                'rate' => $taxConfig['basic_rate'],
                'used' => round($breakdown['basic_rate_used'], 2),
                'remaining' => round(max(0, $basicRateLimit - $breakdown['basic_rate_used']), 2),
            ],
            'higher_rate' => [
                'limit' => $higherRateLimit,
                'rate' => $taxConfig['higher_rate'],
                'used' => round($breakdown['higher_rate_used'], 2),
                'remaining' => round(max(0, $higherRateLimit - $breakdown['higher_rate_used']), 2),
            ],
            'additional_rate' => [
                'rate' => $taxConfig['additional_rate'],
                'used' => round($breakdown['additional_rate_used'], 2),
                'remaining' => null, // No upper limit
            ],
        ];

        // Round all monetary values
        $breakdown['pcls_total'] = round($breakdown['pcls_total'], 2);
        $breakdown['tax_free_total'] = round($breakdown['tax_free_total'], 2);
        $breakdown['taxable_total'] = round($breakdown['taxable_total'], 2);
        $breakdown['personal_allowance_used'] = round($breakdown['personal_allowance_used'], 2);
        $breakdown['basic_rate_total'] = round($breakdown['basic_rate_total'], 2);
        $breakdown['higher_rate_total'] = round($breakdown['higher_rate_total'], 2);
        $breakdown['additional_rate_total'] = round($breakdown['additional_rate_total'], 2);
        $breakdown['total_tax'] = round($breakdown['total_tax'], 2);
        $breakdown['gross_income'] = round($breakdown['gross_income'], 2);
        $breakdown['net_income'] = round($breakdown['net_income'], 2);

        // Add aliases for frontend card compatibility
        $breakdown['tax_free_income'] = $breakdown['tax_free_total'];
        $breakdown['taxable_income'] = $breakdown['taxable_total'];

        return $breakdown;
    }

    /**
     * Project fund depletion from retirement age to 100.
     */
    public function projectFundDepletion(int $userId, array $incomeAllocations, int $retirementAge): array
    {
        $projections = [];
        $depletionAges = [];

        // Group allocations by source to track fund balances
        $fundBalances = $this->initializeFundBalances($userId, $incomeAllocations);
        $annualWithdrawals = $this->calculateAnnualWithdrawals($incomeAllocations);

        // Initialize aggregated balances from starting fund balances
        $aggregatedBalances = ['dc_pension' => 0, 'isa' => 0, 'bond' => 0, 'gia' => 0, 'savings' => 0];
        foreach ($fundBalances as $fundKey => $balance) {
            $type = $this->getFundTypeFromKey($fundKey);
            if ($type) {
                $aggregatedBalances[$type] += $balance;
            }
        }
        $aggregatedDepleted = [];

        for ($age = $retirementAge; $age <= self::PROJECTION_END_AGE; $age++) {
            $yearData = ['age' => $age, 'total_income' => 0, 'funds' => []];

            // Reset aggregated totals for this year
            $yearAggregated = ['dc_pension' => 0, 'isa' => 0, 'bond' => 0, 'gia' => 0, 'savings' => 0];

            foreach ($fundBalances as $fundKey => $balance) {
                $withdrawal = $annualWithdrawals[$fundKey] ?? 0;

                // Apply withdrawal
                $newBalance = $balance - $withdrawal;

                // Check for depletion
                if ($newBalance <= 0 && ! isset($depletionAges[$fundKey])) {
                    $depletionAges[$fundKey] = $age;
                    $newBalance = 0;
                    $withdrawal = $balance; // Only withdraw what's left
                }

                // Apply growth to remaining balance (not for cash)
                if ($newBalance > 0 && ! str_contains($fundKey, 'savings') && ! str_contains($fundKey, 'cash')) {
                    $growthRate = $this->getGrowthRateForFund($fundKey);
                    $newBalance *= (1 + $growthRate);
                }

                $fundBalances[$fundKey] = max(0, $newBalance);
                $yearData['funds'][$fundKey] = round($fundBalances[$fundKey], 2);
                $yearData['total_income'] += $withdrawal;

                // Aggregate by fund type for chart
                $aggregatedType = $this->getFundTypeFromKey($fundKey);
                if ($aggregatedType) {
                    $yearAggregated[$aggregatedType] += $fundBalances[$fundKey];
                }
            }

            // Add aggregated values directly on yearData for chart compatibility
            $yearData['dc_pension'] = round($yearAggregated['dc_pension'], 2);
            $yearData['isa'] = round($yearAggregated['isa'], 2);
            $yearData['bond'] = round($yearAggregated['bond'], 2);
            $yearData['gia'] = round($yearAggregated['gia'], 2);
            $yearData['savings'] = round($yearAggregated['savings'], 2);
            $yearData['total_funds'] = round(
                $yearAggregated['dc_pension'] + $yearAggregated['isa'] + $yearAggregated['bond'] + $yearAggregated['gia'] + $yearAggregated['savings'],
                2
            );

            // Track aggregated depletion ages
            foreach (['dc_pension', 'isa', 'bond', 'gia', 'savings'] as $type) {
                if ($yearAggregated[$type] <= 0 && ! isset($aggregatedDepleted[$type]) && $aggregatedBalances[$type] > 0) {
                    $aggregatedDepleted[$type] = $age;
                }
                $aggregatedBalances[$type] = $yearAggregated[$type];
            }

            $yearData['total_income'] = round($yearData['total_income'], 2);
            $projections[] = $yearData;
        }

        return [
            'projections' => $projections,
            'depletion_ages' => $aggregatedDepleted,
        ];
    }

    /**
     * Map fund key to aggregated type for chart.
     */
    private function getFundTypeFromKey(string $fundKey): ?string
    {
        if (str_starts_with($fundKey, 'dc_pension_')) {
            return 'dc_pension';
        }
        if (str_starts_with($fundKey, 'isa_')) {
            return 'isa';
        }
        if (str_starts_with($fundKey, 'onshore_bond_') || str_starts_with($fundKey, 'offshore_bond_')) {
            return 'bond';
        }
        if (str_starts_with($fundKey, 'gia_')) {
            return 'gia';
        }
        if (str_starts_with($fundKey, 'savings_')) {
            return 'savings';
        }

        return null;
    }

    /**
     * Calculate default target income (75% of current net income).
     */
    private function calculateDefaultTargetIncome(User $user): float
    {
        $grossIncome = (float) ($user->annual_employment_income ?? 0);
        if ($grossIncome <= 0) {
            return 25000; // Default fallback
        }

        // Estimate net income (simple approximation)
        $incomeTax = $this->taxConfig->getIncomeTax();
        $personalAllowance = $incomeTax['personal_allowance'] ?? 12570;

        $taxableIncome = max(0, $grossIncome - $personalAllowance);
        $basicBand = ($incomeTax['bands'][0]['max'] ?? 37700);
        $basicTax = min($taxableIncome, $basicBand) * 0.20;
        $higherTax = max(0, $taxableIncome - $basicBand) * 0.40;

        $totalTax = $basicTax + $higherTax;
        $netIncome = $grossIncome - $totalTax;

        return $netIncome * 0.75; // 75% replacement ratio
    }

    /**
     * Calculate default tax-optimized allocations.
     *
     * Tax-efficient order:
     * 1. Guaranteed income first (State Pension, DB Pension) - these are unavoidable and use personal allowance
     * 2. Tax-free sources (PCLS, ISA) - no tax impact
     * 3. Taxable flexible income (DC drawdown) - fills remaining target
     *
     * State pension only included if retirement age >= state pension age.
     * DB pension only included if retirement age >= normal retirement age.
     */
    private function calculateDefaultAllocations(array $availableAccounts, float $targetIncome, int $retirementAge): array
    {
        $allocations = [];
        $incomeTax = $this->taxConfig->getIncomeTax();
        $personalAllowance = $incomeTax['personal_allowance'] ?? 12570;

        // Step 1: Calculate guaranteed income that will be received at retirement age
        // These consume personal allowance first since they're unavoidable
        $guaranteedIncome = 0;

        // State Pension - only if retirement age >= state pension age
        foreach ($availableAccounts as $account) {
            if ($account['type'] === 'state_pension' && isset($account['annual_income'])) {
                $statePensionAge = $account['payment_start_age'] ?? 67;

                if ($retirementAge >= $statePensionAge) {
                    $allocations[] = [
                        'source_type' => 'state_pension',
                        'source_id' => $account['id'],
                        'name' => $account['name'],
                        'annual_amount' => $account['annual_income'],
                        'tax_rate' => null,
                        'tax_treatment' => 'taxable',
                        'is_guaranteed' => true,
                        'starts_at_age' => $statePensionAge,
                    ];
                    $guaranteedIncome += $account['annual_income'];
                }
            }
        }

        // DB Pensions - only if retirement age >= normal retirement age
        foreach ($availableAccounts as $account) {
            if ($account['type'] === 'db_pension' && isset($account['annual_income'])) {
                $dbStartAge = $account['payment_start_age'] ?? 65;

                if ($retirementAge >= $dbStartAge) {
                    $allocations[] = [
                        'source_type' => 'db_pension',
                        'source_id' => $account['id'],
                        'name' => $account['name'],
                        'annual_amount' => $account['annual_income'],
                        'tax_rate' => null,
                        'tax_treatment' => 'taxable',
                        'is_guaranteed' => true,
                        'starts_at_age' => $dbStartAge,
                    ];
                    $guaranteedIncome += $account['annual_income'];
                }
            }
        }

        // Calculate remaining personal allowance after guaranteed income
        $personalAllowanceRemaining = max(0, $personalAllowance - $guaranteedIncome);

        // Calculate how much more income we need beyond guaranteed
        $remainingTarget = max(0, $targetIncome - $guaranteedIncome);

        // Step 2: Tax-free sources (PCLS from DC pensions)
        // These are completely tax-free regardless of other income
        foreach ($availableAccounts as $account) {
            if ($account['type'] === 'dc_pension' && isset($account['sub_accounts']) && $remainingTarget > 0) {
                foreach ($account['sub_accounts'] as $subAccount) {
                    if ($subAccount['source_type'] === 'dc_pension_pcls') {
                        // Take PCLS up to 20% of remaining target or max available
                        $pclsAmount = min($subAccount['max_amount'], $remainingTarget * 0.25);
                        if ($pclsAmount > 0) {
                            $allocations[] = [
                                'source_type' => 'dc_pension_pcls',
                                'source_id' => $subAccount['source_id'],
                                'name' => $subAccount['name'],
                                'annual_amount' => round($pclsAmount, 2),
                                'tax_rate' => 0,
                                'tax_treatment' => 'tax_free',
                            ];
                            $remainingTarget -= $pclsAmount;
                        }
                    }
                }
            }
        }

        // Step 3: Bond withdrawals (5% tax-deferred)
        // Use 5% cumulative tax-free allowance from bonds before touching ISA
        foreach ($availableAccounts as $account) {
            if (($account['type'] === 'onshore_bond' || $account['type'] === 'offshore_bond') && $remainingTarget > 0) {
                // 5% of original investment is tax-deferred
                $taxFreeAllowance = $account['annual_tax_free_allowance'] ?? ($account['value'] * self::BOND_TAX_FREE_RATE);
                $bondAmount = min($taxFreeAllowance, $remainingTarget, $account['value']);
                if ($bondAmount > 0) {
                    $allocations[] = [
                        'source_type' => $account['type'],
                        'source_id' => $account['id'],
                        'name' => $account['name'],
                        'annual_amount' => round($bondAmount, 2),
                        'tax_rate' => 0, // Within 5% allowance
                        'tax_treatment' => 'tax_deferred',
                    ];
                    $remainingTarget -= $bondAmount;
                }
            }
        }

        // Step 4: ISA withdrawals (tax-free)
        foreach ($availableAccounts as $account) {
            if (($account['type'] === 'isa_cash' || $account['type'] === 'isa_investment') && $remainingTarget > 0) {
                $isaAmount = min($account['value'] * self::ISA_WITHDRAWAL_RATE, $remainingTarget); // 4.7% sustainable withdrawal
                if ($isaAmount > 0) {
                    $allocations[] = [
                        'source_type' => 'isa',
                        'source_id' => $account['id'],
                        'name' => $account['name'],
                        'annual_amount' => round($isaAmount, 2),
                        'tax_rate' => 0,
                        'tax_treatment' => 'tax_free',
                    ];
                    $remainingTarget -= $isaAmount;
                }
            }
        }

        // Step 5: DC pension drawdown (taxable, but can use remaining personal allowance)
        // First, use any remaining personal allowance
        if ($personalAllowanceRemaining > 0 && $remainingTarget > 0) {
            foreach ($availableAccounts as $account) {
                if ($account['type'] === 'dc_pension' && isset($account['sub_accounts']) && $remainingTarget > 0 && $personalAllowanceRemaining > 0) {
                    foreach ($account['sub_accounts'] as $subAccount) {
                        if ($subAccount['source_type'] === 'dc_pension_drawdown') {
                            $drawdownAmount = min($personalAllowanceRemaining, $remainingTarget, $subAccount['max_amount']);
                            if ($drawdownAmount > 0) {
                                $allocations[] = [
                                    'source_type' => 'dc_pension_drawdown',
                                    'source_id' => $subAccount['source_id'],
                                    'name' => $subAccount['name'],
                                    'annual_amount' => round($drawdownAmount, 2),
                                    'tax_rate' => null,
                                    'tax_treatment' => 'taxable',
                                ];
                                $remainingTarget -= $drawdownAmount;
                                $personalAllowanceRemaining -= $drawdownAmount;
                            }
                        }
                    }
                }
            }
        }

        // Step 6: Additional taxable pension drawdown beyond personal allowance
        if ($remainingTarget > 0) {
            foreach ($availableAccounts as $account) {
                if ($account['type'] === 'dc_pension' && isset($account['sub_accounts']) && $remainingTarget > 0) {
                    foreach ($account['sub_accounts'] as $subAccount) {
                        if ($subAccount['source_type'] === 'dc_pension_drawdown') {
                            // Check if we already allocated to this source
                            $existingAllocation = null;
                            foreach ($allocations as $alloc) {
                                if ($alloc['source_id'] === $subAccount['source_id'] && $alloc['source_type'] === 'dc_pension_drawdown') {
                                    $existingAllocation = $alloc;
                                    break;
                                }
                            }
                            $alreadyAllocated = $existingAllocation['annual_amount'] ?? 0;
                            $availableForDrawdown = $subAccount['max_amount'] - $alreadyAllocated;

                            if ($availableForDrawdown > 0) {
                                $additionalAmount = min($availableForDrawdown, $remainingTarget);
                                // Update existing allocation or add new
                                if ($existingAllocation) {
                                    foreach ($allocations as &$alloc) {
                                        if ($alloc['source_id'] === $subAccount['source_id'] && $alloc['source_type'] === 'dc_pension_drawdown') {
                                            $alloc['annual_amount'] = round($alloc['annual_amount'] + $additionalAmount, 2);
                                            break;
                                        }
                                    }
                                    unset($alloc);
                                } else {
                                    $allocations[] = [
                                        'source_type' => 'dc_pension_drawdown',
                                        'source_id' => $subAccount['source_id'],
                                        'name' => $subAccount['name'],
                                        'annual_amount' => round($additionalAmount, 2),
                                        'tax_rate' => null,
                                        'tax_treatment' => 'taxable',
                                    ];
                                }
                                $remainingTarget -= $additionalAmount;
                            }
                        }
                    }
                }
            }
        }

        // Step 7: GIA and other taxable savings if still needed
        if ($remainingTarget > 0) {
            foreach ($availableAccounts as $account) {
                if ($account['type'] === 'gia' && $remainingTarget > 0) {
                    $giaAmount = min($account['value'] * self::GIA_WITHDRAWAL_RATE, $remainingTarget);
                    if ($giaAmount > 0) {
                        $allocations[] = [
                            'source_type' => 'gia',
                            'source_id' => $account['id'],
                            'name' => $account['name'],
                            'annual_amount' => round($giaAmount, 2),
                            'tax_rate' => null,
                            'tax_treatment' => 'taxable',
                        ];
                        $remainingTarget -= $giaAmount;
                    }
                }
            }
        }

        return $allocations;
    }

    /**
     * Sort allocations by tax efficiency (tax-free first).
     *
     * Priority: PCLS (1) → Tax-deferred bonds (2) → Tax-free ISA (3) → Taxable (4)
     */
    private function sortByTaxEfficiency(array $allocations): array
    {
        $order = [
            'pcls' => 1,
            'tax_deferred' => 2, // Bonds - uses 5% allowance before ISA
            'tax_free' => 3,
            'taxable' => 4,
        ];

        usort($allocations, function ($a, $b) use ($order) {
            $aOrder = $order[$a['tax_treatment'] ?? 'taxable'] ?? 4;
            $bOrder = $order[$b['tax_treatment'] ?? 'taxable'] ?? 4;

            return $aOrder <=> $bOrder;
        });

        return $allocations;
    }

    /**
     * Initialize fund balances for projection.
     */
    private function initializeFundBalances(int $userId, array $incomeAllocations): array
    {
        $balances = [];
        $userIds = [$userId];
        $spouse = User::find($userId)?->spouse;
        if ($spouse) {
            $userIds[] = $spouse->id;
        }

        // DC Pensions
        $dcPensions = DCPension::whereIn('user_id', $userIds)->get();
        foreach ($dcPensions as $pension) {
            $balances['dc_pension_'.$pension->id] = (float) ($pension->current_fund_value ?? 0);
        }

        // ISAs (Savings)
        $isaAccounts = SavingsAccount::whereIn('user_id', $userIds)->where('is_isa', true)->get();
        foreach ($isaAccounts as $account) {
            $balances['isa_savings_'.$account->id] = (float) ($account->current_balance ?? 0);
        }

        // ISAs (Investment)
        $investmentIsas = InvestmentAccount::whereIn('user_id', $userIds)
            ->whereIn('account_type', ['isa', 'stocks_shares_isa', 'lifetime_isa'])
            ->get();
        foreach ($investmentIsas as $account) {
            $balances['isa_investment_'.$account->id] = (float) ($account->current_value ?? 0);
        }

        // Onshore Bonds
        $onshoreBonds = InvestmentAccount::whereIn('user_id', $userIds)
            ->where('account_type', 'onshore_bond')
            ->get();
        foreach ($onshoreBonds as $account) {
            $balances['onshore_bond_'.$account->id] = (float) ($account->current_value ?? 0);
        }

        // Offshore Bonds
        $offshoreBonds = InvestmentAccount::whereIn('user_id', $userIds)
            ->where('account_type', 'offshore_bond')
            ->get();
        foreach ($offshoreBonds as $account) {
            $balances['offshore_bond_'.$account->id] = (float) ($account->current_value ?? 0);
        }

        // GIAs
        $giaAccounts = InvestmentAccount::whereIn('user_id', $userIds)
            ->whereIn('account_type', ['gia', 'general'])
            ->get();
        foreach ($giaAccounts as $account) {
            $balances['gia_'.$account->id] = (float) ($account->current_value ?? 0);
        }

        // Non-ISA Savings
        $savingsAccounts = SavingsAccount::whereIn('user_id', $userIds)
            ->where(function ($query) {
                $query->where('is_isa', false)->orWhereNull('is_isa');
            })
            ->get();
        foreach ($savingsAccounts as $account) {
            $balances['savings_'.$account->id] = (float) ($account->current_balance ?? 0);
        }

        return $balances;
    }

    /**
     * Calculate annual withdrawals from allocations.
     */
    private function calculateAnnualWithdrawals(array $incomeAllocations): array
    {
        $withdrawals = [];

        foreach ($incomeAllocations as $allocation) {
            $sourceType = $allocation['source_type'] ?? '';
            $sourceId = $allocation['source_id'] ?? 0;
            $amount = (float) ($allocation['annual_amount'] ?? 0);

            // Map source type to fund key
            $fundKey = match ($sourceType) {
                'dc_pension_pcls', 'dc_pension_drawdown' => 'dc_pension_'.$sourceId,
                'isa' => 'isa_investment_'.$sourceId, // Could be savings or investment
                'onshore_bond' => 'onshore_bond_'.$sourceId,
                'offshore_bond' => 'offshore_bond_'.$sourceId,
                'gia' => 'gia_'.$sourceId,
                'savings' => 'savings_'.$sourceId,
                default => null,
            };

            if ($fundKey) {
                $withdrawals[$fundKey] = ($withdrawals[$fundKey] ?? 0) + $amount;
            }
        }

        return $withdrawals;
    }

    /**
     * Get growth rate for a fund type.
     */
    private function getGrowthRateForFund(string $fundKey): float
    {
        if (str_contains($fundKey, 'dc_pension') ||
            str_contains($fundKey, 'isa_investment') ||
            str_contains($fundKey, 'onshore_bond') ||
            str_contains($fundKey, 'offshore_bond') ||
            str_contains($fundKey, 'gia')) {
            return self::DEFAULT_GROWTH_RATE;
        }

        return 0.02; // Lower rate for cash/savings
    }
}
