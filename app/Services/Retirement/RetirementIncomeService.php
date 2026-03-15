<?php

declare(strict_types=1);

namespace App\Services\Retirement;

use App\Models\DBPension;
use App\Models\Investment\InvestmentAccount;
use App\Models\RetirementProfile;
use App\Models\SavingsAccount;
use App\Models\StatePension;
use App\Models\User;
use App\Services\Investment\InvestmentProjectionService;
use App\Services\TaxBandTracker;
use App\Services\TaxConfigService;

/**
 * Retirement Income Service
 *
 * Calculates tax-optimized retirement income drawdown strategies,
 * projects fund depletion over time, and provides real-time tax calculations.
 *
 * Key features:
 * - Uses combined Pension Pot (not individual pensions)
 * - Uses 80% Monte Carlo projected value at retirement
 * - Checks if target income depletes funds before age 100
 * - Adjusts income to sustainable level if funds would deplete early
 * - Handles state pension timing (before/after state pension age)
 * - Shows message with gov.uk link if no state pension data entered
 */
class RetirementIncomeService
{
    private const DEFAULT_RETIREMENT_AGE = 67;

    private const PROJECTION_END_AGE = 100;

    private const STATE_PENSION_GOV_UK = 'https://www.gov.uk/check-state-pension';

    private const BOND_TAX_FREE_RATE = 0.05; // 5% cumulative tax-free allowance

    public function __construct(
        private readonly TaxConfigService $taxConfig,
        private readonly DecumulationPlanner $decumulationPlanner,
        private readonly RequiredCapitalCalculator $requiredCapitalCalculator,
        private readonly RetirementProjectionService $projectionService,
        private readonly InvestmentProjectionService $investmentProjectionService,
    ) {}

    /**
     * Get retirement income configuration with default tax-optimized allocations.
     *
     * Process:
     * 1. Get projected pension pot value (80% Monte Carlo confidence)
     * 2. Get target income from RequiredCapitalCalculator
     * 3. Check state pension status (show message if not entered)
     * 4. Check if target income depletes funds before age 100
     * 5. If depletes early, calculate sustainable income to last to age 100
     * 6. Optimise allocations for that income (adjusted or original)
     * 7. Account for state pension timing in calculations
     */
    public function getRetirementIncomeConfig(int $userId, bool $includeSpouse = false): array
    {
        $user = User::findOrFail($userId);
        $profile = RetirementProfile::where('user_id', $userId)->first();
        $currentAge = $user->date_of_birth ? $user->date_of_birth->age : null;

        $retirementAge = $profile?->target_retirement_age ?? self::DEFAULT_RETIREMENT_AGE;

        // Get projected pension pot value (80% Monte Carlo confidence)
        $potProjection = $this->projectionService->projectPensionPot($user);
        $projectedPensionPot = (float) ($potProjection['percentile_20_at_retirement'] ?? 0);

        // Get target income from centralised RequiredCapitalCalculator
        $requiredCapitalData = $this->requiredCapitalCalculator->calculate($userId);
        $targetIncome = (float) $requiredCapitalData['required_income'];

        // Calculate years to retirement for projecting asset values
        $yearsToRetirement = max(0, $retirementAge - ($currentAge ?? 45));

        // Get available accounts (non-pension sources: ISAs, bonds, GIA, savings)
        // Plus combined pension pot using projected value
        // All assets are projected to retirement age
        $availableAccounts = $this->getAvailableAccounts($userId, $includeSpouse, $projectedPensionPot, $yearsToRetirement);

        // Total funds = projected pension pot + other drawable assets
        $totalFunds = $this->calculateTotalFunds($availableAccounts);

        // Get state pension status
        $statePensionStatus = $this->getStatePensionStatus($userId, $includeSpouse, $retirementAge);

        // Calculate years in retirement (used for depletion check reference)
        $yearsInRetirement = self::PROJECTION_END_AGE - $retirementAge;

        // NOTE: We no longer pre-adjust income here. The projectFundDepletion function
        // correctly simulates using actual allocations (which exclude guaranteed income
        // like state pension and DB pensions). Pre-checking with checkFundDepletion
        // was incorrectly including guaranteed income in the withdrawal calculation,
        // causing premature income reduction.

        // Use target income directly - projectFundDepletion will adjust if truly needed
        $optimisedIncome = $targetIncome;

        // Calculate allocations for the target income
        $defaultAllocations = $this->calculateDefaultAllocations(
            $availableAccounts,
            $optimisedIncome,
            $retirementAge,
            $statePensionStatus
        );

        // Calculate tax breakdown accounting for state pension timing
        $taxBreakdown = $this->calculateTaxBreakdown($defaultAllocations, $statePensionStatus);

        // Project fund depletion (with all assets projected to retirement age)
        // Pass availableAccounts to ensure consistent values between PMT calculation and projection
        $fundProjections = $this->projectFundDepletion($userId, $defaultAllocations, $retirementAge, $statePensionStatus, $projectedPensionPot, $yearsToRetirement, $availableAccounts);

        // Build depletion check from projections
        $depletionAges = $fundProjections['depletion_ages'] ?? [];
        $totalDepletionAge = $depletionAges['total'] ?? 100;
        $depletionCheck = [
            'is_sustainable' => $totalDepletionAge >= 100,
            'depletion_age' => $totalDepletionAge < 100 ? $totalDepletionAge : null,
            'funds_at_100' => $fundProjections['projections'][count($fundProjections['projections']) - 1]['total_funds'] ?? 0,
        ];

        return [
            'target_income' => round($targetIncome, 2),
            'optimised_income' => round($fundProjections['actual_withdrawal'] ?? $optimisedIncome, 2),
            'income_was_adjusted' => $fundProjections['income_was_adjusted'] ?? false,
            'sustainable_withdrawal' => $fundProjections['sustainable_withdrawal'] ?? 0,
            'retirement_age' => $retirementAge,
            'current_age' => $currentAge,
            'include_spouse' => $includeSpouse,
            'projected_pension_pot' => round($projectedPensionPot, 2),
            'total_funds' => round($fundProjections['total_starting_funds'] ?? $totalFunds, 2),
            'available_accounts' => $availableAccounts,
            'allocations' => $defaultAllocations,
            'tax_breakdown' => $taxBreakdown,
            'fund_projections' => $fundProjections['projections'],
            'depletion_ages' => $fundProjections['depletion_ages'],
            'depletion_check' => $depletionCheck,
            'state_pension_status' => $statePensionStatus,
        ];
    }

    /**
     * Calculate income scenario based on user-specified allocations.
     */
    public function calculateIncomeScenario(int $userId, array $incomeAllocations, ?float $customTargetIncome = null, bool $includeSpouse = false): array
    {
        $user = User::findOrFail($userId);
        $profile = RetirementProfile::where('user_id', $userId)->first();
        $currentAge = $user->date_of_birth ? $user->date_of_birth->age : null;

        $retirementAge = $profile?->target_retirement_age ?? self::DEFAULT_RETIREMENT_AGE;

        // Get target income from centralised RequiredCapitalCalculator, or use custom if provided
        if ($customTargetIncome !== null) {
            $targetIncome = $customTargetIncome;
        } else {
            $requiredCapitalData = $this->requiredCapitalCalculator->calculate($userId);
            $targetIncome = (float) $requiredCapitalData['required_income'];
        }

        // Get state pension status
        $statePensionStatus = $this->getStatePensionStatus($userId, $includeSpouse, $retirementAge);

        // Get projected pension pot value (80% Monte Carlo confidence)
        $potProjection = $this->projectionService->projectPensionPot($user);
        $projectedPensionPot = (float) ($potProjection['percentile_20_at_retirement'] ?? 0);

        // Calculate years to retirement for projecting asset values
        $yearsToRetirement = max(0, $retirementAge - ($currentAge ?? 45));

        // Include available accounts so they're not lost after recalculation
        // All assets are projected to retirement age
        $availableAccounts = $this->getAvailableAccounts($userId, $includeSpouse, $projectedPensionPot, $yearsToRetirement);
        $totalFunds = $this->calculateTotalFunds($availableAccounts);

        // Calculate gross income from allocations
        $grossAllocationIncome = array_reduce($incomeAllocations, function ($sum, $alloc) {
            return $sum + (float) ($alloc['annual_amount'] ?? 0);
        }, 0.0);

        $taxBreakdown = $this->calculateTaxBreakdown($incomeAllocations, $statePensionStatus);
        // Pass availableAccounts to ensure consistent values between PMT calculation and projection
        $fundProjections = $this->projectFundDepletion($userId, $incomeAllocations, $retirementAge, $statePensionStatus, $projectedPensionPot, $yearsToRetirement, $availableAccounts);

        // Build depletion check from projections (more accurate than checkFundDepletion)
        $depletionAges = $fundProjections['depletion_ages'] ?? [];
        $totalDepletionAge = $depletionAges['total'] ?? 100;
        $depletionCheck = [
            'is_sustainable' => $totalDepletionAge >= 100,
            'depletion_age' => $totalDepletionAge < 100 ? $totalDepletionAge : null,
            'funds_at_100' => $fundProjections['projections'][count($fundProjections['projections']) - 1]['total_funds'] ?? 0,
        ];

        return [
            'target_income' => round($targetIncome, 2),
            'optimised_income' => round($fundProjections['actual_withdrawal'] ?? $grossAllocationIncome, 2),
            'income_was_adjusted' => $fundProjections['income_was_adjusted'] ?? false,
            'sustainable_withdrawal' => $fundProjections['sustainable_withdrawal'] ?? 0,
            'retirement_age' => $retirementAge,
            'projected_pension_pot' => round($projectedPensionPot, 2),
            'total_funds' => round($fundProjections['total_starting_funds'] ?? $totalFunds, 2),
            'available_accounts' => $availableAccounts,
            'allocations' => $incomeAllocations,
            'tax_breakdown' => $taxBreakdown,
            'fund_projections' => $fundProjections['projections'],
            'depletion_ages' => $fundProjections['depletion_ages'],
            'meets_target' => $taxBreakdown['net_income'] >= $targetIncome,
            'income_gap' => max(0, $targetIncome - $taxBreakdown['net_income']),
            'depletion_check' => $depletionCheck,
            'state_pension_status' => $statePensionStatus,
        ];
    }

    /**
     * Get all accounts eligible for retirement income.
     *
     * Uses the COMBINED Pension Pot with the projected value (80% Monte Carlo confidence),
     * NOT individual DC pensions. The pension pot is split into PCLS (25%) and drawdown (75%).
     *
     * All assets (ISAs, bonds, GIAs) are PROJECTED to retirement age using compound growth,
     * not current values. This provides accurate values for retirement planning.
     *
     * @param  float  $projectedPensionPot  The 80% confidence projected value from Monte Carlo
     * @param  int|null  $yearsToRetirement  Years until retirement for projecting asset values
     */
    public function getAvailableAccounts(int $userId, bool $includeSpouse = false, float $projectedPensionPot = 0, ?int $yearsToRetirement = null): array
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

        // Calculate years to retirement if not provided
        if ($yearsToRetirement === null) {
            $user = User::find($userId);
            $profile = RetirementProfile::where('user_id', $userId)->first();
            $currentAge = $user?->date_of_birth?->age;
            $retirementAge = $profile?->target_retirement_age ?? self::DEFAULT_RETIREMENT_AGE;
            $yearsToRetirement = max(0, $retirementAge - ($currentAge ?? 45));
        }

        // Combined Pension Pot (using projected 80% Monte Carlo value, not individual pensions)
        // This is the combined value of ALL DC pensions projected to retirement age
        if ($projectedPensionPot > 0) {
            $pclsAvailable = $projectedPensionPot * 0.25; // 25% tax-free
            $drawdownAvailable = $projectedPensionPot * 0.75; // 75% taxable

            $accounts[] = [
                'id' => 'pension_pot',
                'type' => 'pension_pot',
                'owner_id' => $userId,
                'name' => 'Pension Pot',
                'value' => round($projectedPensionPot, 2),
                'pcls_available' => round($pclsAvailable, 2),
                'tax_treatment' => 'taxable',
                'is_projected' => true,
                'sub_accounts' => [
                    [
                        'source_type' => 'pension_pot_pcls',
                        'source_id' => 'pension_pot',
                        'name' => 'Pension Pot - Tax-Free Cash (PCLS)',
                        'max_amount' => round($pclsAvailable, 2),
                        'tax_rate' => 0,
                        'tax_treatment' => 'tax_free',
                    ],
                    [
                        'source_type' => 'pension_pot_drawdown',
                        'source_id' => 'pension_pot',
                        'name' => 'Pension Pot - Drawdown',
                        'max_amount' => round($drawdownAvailable, 2),
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
        // Projected to retirement age using compound growth
        // Only include accounts explicitly marked for retirement planning
        $isaAccounts = SavingsAccount::whereIn('user_id', $userIds)
            ->where('include_in_retirement', true)
            ->where('is_isa', true)
            ->get();
        foreach ($isaAccounts as $account) {
            $currentValue = (float) ($account->current_balance ?? 0);
            // Cash ISAs grow at lower rate (savings rate)
            $cashGrowthRate = 0.02; // 2% for cash
            $projectedValue = $currentValue * pow(1 + $cashGrowthRate, $yearsToRetirement);
            $accounts[] = [
                'id' => $account->id,
                'type' => 'isa_cash',
                'owner_id' => $account->user_id,
                'name' => $account->institution ?? 'Cash ISA',
                'current_value' => round($currentValue, 2),
                'value' => round($projectedValue, 2),
                'is_projected' => true,
                'years_projected' => $yearsToRetirement,
                'growth_rate' => $cashGrowthRate,
                'isa_type' => $account->isa_type,
                'tax_rate' => 0,
                'tax_treatment' => 'tax_free',
                'source_type' => 'isa',
                'source_id' => $account->id,
            ];
        }

        // ISAs (Investment - Stocks & Shares ISA)
        // Only include accounts marked for retirement planning
        // Projected to retirement age using Monte Carlo 80% confidence
        $investmentIsas = InvestmentAccount::whereIn('user_id', $userIds)
            ->where('include_in_retirement', true)
            ->where(function ($query) {
                $query->where('account_type', 'isa')
                    ->orWhere('account_type', 'stocks_shares_isa')
                    ->orWhere('account_type', 'lifetime_isa');
            })
            ->get();
        foreach ($investmentIsas as $account) {
            $currentValue = (float) ($account->current_value ?? 0);
            $accountUser = User::find($account->user_id);
            // Use Monte Carlo 80% projected value (same as Investment module)
            $projectedValue = $yearsToRetirement > 0 && $accountUser
                ? $this->investmentProjectionService->getAccountProjectedValue80($account, $accountUser, $yearsToRetirement)
                : $currentValue;
            $accounts[] = [
                'id' => $account->id,
                'type' => 'isa_investment',
                'owner_id' => $account->user_id,
                'name' => $account->provider ?? 'Stocks & Shares ISA',
                'platform' => $account->platform,
                'current_value' => round($currentValue, 2),
                'value' => round($projectedValue, 2),
                'is_projected' => true,
                'years_projected' => $yearsToRetirement,
                'projection_type' => 'monte_carlo_80',
                'isa_type' => $account->isa_type ?? 'stocks_shares',
                'tax_rate' => 0,
                'tax_treatment' => 'tax_free',
                'source_type' => 'isa',
                'source_id' => $account->id,
            ];
        }

        // Onshore Bonds - 5% cumulative tax-free withdrawal
        // Only include accounts marked for retirement planning
        // Projected to retirement age using Monte Carlo 80% confidence
        $onshoreBonds = InvestmentAccount::whereIn('user_id', $userIds)
            ->where('include_in_retirement', true)
            ->where('account_type', 'onshore_bond')
            ->get();
        foreach ($onshoreBonds as $account) {
            $currentValue = (float) ($account->current_value ?? 0);
            $accountUser = User::find($account->user_id);
            // Use Monte Carlo 80% projected value
            $projectedValue = $yearsToRetirement > 0 && $accountUser
                ? $this->investmentProjectionService->getAccountProjectedValue80($account, $accountUser, $yearsToRetirement)
                : $currentValue;
            // Original investment for 5% calculation (fallback to current value if not set)
            $originalInvestment = (float) ($account->investment_amount ?? $currentValue);
            // 5% annual tax-free allowance of original investment
            $annualTaxFreeAllowance = $originalInvestment * self::BOND_TAX_FREE_RATE;

            $accounts[] = [
                'id' => $account->id,
                'type' => 'onshore_bond',
                'owner_id' => $account->user_id,
                'name' => $account->provider ?? 'Onshore Bond',
                'provider' => $account->provider,
                'current_value' => round($currentValue, 2),
                'value' => round($projectedValue, 2),
                'is_projected' => true,
                'years_projected' => $yearsToRetirement,
                'projection_type' => 'monte_carlo_80',
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
        // Projected to retirement age using Monte Carlo 80% confidence
        $offshoreBonds = InvestmentAccount::whereIn('user_id', $userIds)
            ->where('include_in_retirement', true)
            ->where('account_type', 'offshore_bond')
            ->get();
        foreach ($offshoreBonds as $account) {
            $currentValue = (float) ($account->current_value ?? 0);
            $accountUser = User::find($account->user_id);
            // Use Monte Carlo 80% projected value
            $projectedValue = $yearsToRetirement > 0 && $accountUser
                ? $this->investmentProjectionService->getAccountProjectedValue80($account, $accountUser, $yearsToRetirement)
                : $currentValue;
            // Original investment for 5% calculation (fallback to current value if not set)
            $originalInvestment = (float) ($account->investment_amount ?? $currentValue);
            // 5% annual tax-free allowance of original investment
            $annualTaxFreeAllowance = $originalInvestment * self::BOND_TAX_FREE_RATE;

            $accounts[] = [
                'id' => $account->id,
                'type' => 'offshore_bond',
                'owner_id' => $account->user_id,
                'name' => $account->provider ?? 'Offshore Bond',
                'provider' => $account->provider,
                'current_value' => round($currentValue, 2),
                'value' => round($projectedValue, 2),
                'is_projected' => true,
                'years_projected' => $yearsToRetirement,
                'projection_type' => 'monte_carlo_80',
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
        // Projected to retirement age using Monte Carlo 80% confidence
        $giaAccounts = InvestmentAccount::whereIn('user_id', $userIds)
            ->where('include_in_retirement', true)
            ->where(function ($query) {
                $query->where('account_type', 'gia')
                    ->orWhere('account_type', 'general');
            })
            ->get();
        foreach ($giaAccounts as $account) {
            $currentValue = (float) ($account->current_value ?? 0);
            $accountUser = User::find($account->user_id);
            // Use Monte Carlo 80% projected value
            $projectedValue = $yearsToRetirement > 0 && $accountUser
                ? $this->investmentProjectionService->getAccountProjectedValue80($account, $accountUser, $yearsToRetirement)
                : $currentValue;
            $accounts[] = [
                'id' => $account->id,
                'type' => 'gia',
                'owner_id' => $account->user_id,
                'name' => $account->provider ?? 'General Investment Account',
                'platform' => $account->platform,
                'current_value' => round($currentValue, 2),
                'value' => round($projectedValue, 2),
                'is_projected' => true,
                'years_projected' => $yearsToRetirement,
                'projection_type' => 'monte_carlo_80',
                'tax_rate' => null, // Depends on total income
                'tax_treatment' => 'taxable',
                'source_type' => 'gia',
                'source_id' => $account->id,
            ];
        }

        // Non-ISA Savings
        // Projected to retirement age using lower cash growth rate
        // Only include accounts explicitly marked for retirement planning
        $savingsAccounts = SavingsAccount::whereIn('user_id', $userIds)
            ->where('include_in_retirement', true)
            ->where(function ($query) {
                $query->where('is_isa', false)
                    ->orWhereNull('is_isa');
            })
            ->get();
        foreach ($savingsAccounts as $account) {
            $currentValue = (float) ($account->current_balance ?? 0);
            // Cash savings grow at lower rate
            $cashGrowthRate = 0.02; // 2% for cash
            $projectedValue = $currentValue * pow(1 + $cashGrowthRate, $yearsToRetirement);
            $accounts[] = [
                'id' => $account->id,
                'type' => 'savings',
                'owner_id' => $account->user_id,
                'name' => $account->institution ?? 'Savings Account',
                'current_value' => round($currentValue, 2),
                'value' => round($projectedValue, 2),
                'is_projected' => true,
                'years_projected' => $yearsToRetirement,
                'growth_rate' => $cashGrowthRate,
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
     * Get state pension status for the user.
     *
     * Returns whether state pension data exists, the amount, age, and timing.
     * If no data entered, returns a message with link to gov.uk.
     */
    public function getStatePensionStatus(int $userId, bool $includeSpouse, int $retirementAge): array
    {
        $userIds = [$userId];
        if ($includeSpouse) {
            $spouse = User::find($userId)?->spouse;
            if ($spouse) {
                $userIds[] = $spouse->id;
            }
        }

        $statePensions = StatePension::whereIn('user_id', $userIds)->get();

        $defaultSPA = (int) $this->taxConfig->get('pension.state_pension.current_spa', 67);

        // No state pension data entered
        if ($statePensions->isEmpty()) {
            return [
                'has_data' => false,
                'annual_amount' => 0,
                'state_pension_age' => $defaultSPA,
                'already_receiving' => false,
                'starts_at_retirement' => false,
                'years_until_state_pension' => max(0, $defaultSPA - $retirementAge),
                'message' => 'No State Pension forecast entered. Your projections do not include State Pension income.',
                'link' => self::STATE_PENSION_GOV_UK,
                'link_text' => 'Check your State Pension forecast on GOV.UK',
            ];
        }

        // Calculate totals from state pension records
        $totalAnnualAmount = 0;
        $statePensionAge = $defaultSPA;
        $alreadyReceiving = false;

        foreach ($statePensions as $pension) {
            $totalAnnualAmount += (float) ($pension->state_pension_forecast_annual ?? 0);
            if ($pension->state_pension_age) {
                $statePensionAge = max($statePensionAge, $pension->state_pension_age);
            }
            if ($pension->already_receiving) {
                $alreadyReceiving = true;
            }
        }

        $startsAtRetirement = $alreadyReceiving || ($statePensionAge <= $retirementAge);
        $yearsUntilStatePension = $alreadyReceiving ? 0 : max(0, $statePensionAge - $retirementAge);

        return [
            'has_data' => true,
            'annual_amount' => round($totalAnnualAmount, 2),
            'state_pension_age' => $statePensionAge,
            'already_receiving' => $alreadyReceiving,
            'starts_at_retirement' => $startsAtRetirement,
            'years_until_state_pension' => $yearsUntilStatePension,
            'message' => null,
            'link' => null,
            'link_text' => null,
        ];
    }

    /**
     * Calculate total drawable funds from available accounts.
     *
     * Includes pension pot (projected value), ISAs, Bonds, GIAs, Savings.
     * Excludes DB pensions and State Pension (income streams, not capital).
     */
    public function calculateTotalFunds(array $availableAccounts): float
    {
        $total = 0.0;

        foreach ($availableAccounts as $account) {
            $type = $account['type'] ?? '';

            // Skip income-based pensions (not capital)
            if (in_array($type, ['db_pension', 'state_pension'])) {
                continue;
            }

            if (isset($account['value']) && $account['value'] > 0) {
                $total += (float) $account['value'];
            }
        }

        return $total;
    }

    /**
     * @deprecated Use calculateTotalFunds() instead
     */
    public function calculateTotalPensionPot(array $availableAccounts): float
    {
        return $this->calculateTotalFunds($availableAccounts);
    }

    /**
     * Check if target income will deplete funds before age 100.
     *
     * Returns whether the income is sustainable and when funds would deplete.
     */
    public function checkFundDepletion(
        float $totalFunds,
        float $annualIncome,
        int $yearsInRetirement,
        array $statePensionStatus
    ): array {
        if ($totalFunds <= 0 || $annualIncome <= 0) {
            return [
                'is_sustainable' => $annualIncome <= 0,
                'depletion_year' => $annualIncome > 0 ? 0 : null,
                'depletion_age' => null,
                'funds_at_100' => 0,
            ];
        }

        $balance = $totalFunds;
        $statePensionAmount = $statePensionStatus['annual_amount'] ?? 0;
        $yearsUntilStatePension = $statePensionStatus['years_until_state_pension'] ?? 0;
        $growthRate = $this->getDefaultGrowthRate();

        for ($year = 0; $year < $yearsInRetirement; $year++) {
            // Calculate withdrawal needed from funds
            // After state pension starts, need less from funds
            $withdrawal = $annualIncome;
            if ($year >= $yearsUntilStatePension && $statePensionAmount > 0) {
                $withdrawal = max(0, $annualIncome - $statePensionAmount);
            }

            $balance -= $withdrawal;

            if ($balance <= 0) {
                return [
                    'is_sustainable' => false,
                    'depletion_year' => $year,
                    'depletion_age' => self::PROJECTION_END_AGE - $yearsInRetirement + $year,
                    'funds_at_100' => 0,
                ];
            }

            // Apply growth
            $balance *= (1 + $growthRate);
        }

        return [
            'is_sustainable' => true,
            'depletion_year' => null,
            'depletion_age' => null,
            'funds_at_100' => round($balance, 2),
        ];
    }

    /**
     * Calculate sustainable income that ensures funds last to age 100.
     *
     * Uses binary search to find the maximum annual income that won't
     * deplete funds before age 100.
     */
    public function calculateSustainableIncome(
        float $totalFunds,
        int $yearsInRetirement,
        array $statePensionStatus
    ): float {
        if ($totalFunds <= 0 || $yearsInRetirement <= 0) {
            return $statePensionStatus['annual_amount'] ?? 0;
        }

        $low = 0.0;
        $high = $totalFunds * 0.15; // Max 15% as upper bound
        $tolerance = 100.0; // £100 precision

        while (($high - $low) > $tolerance) {
            $mid = ($low + $high) / 2;

            $check = $this->checkFundDepletion($totalFunds, $mid, $yearsInRetirement, $statePensionStatus);

            if ($check['is_sustainable']) {
                $low = $mid;
            } else {
                $high = $mid;
            }
        }

        return floor($low);
    }

    /**
     * Calculate tax breakdown based on income allocations.
     * Applies income in tax-efficient order: PCLS -> Personal Allowance -> ISA -> Taxable
     *
     * State pension status is used for context but doesn't change tax calculation
     * (state pension is included in allocations if applicable).
     */
    public function calculateTaxBreakdown(array $incomeAllocations, ?array $statePensionStatus = null): array
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

            if ($taxTreatment === 'tax_free' || $taxTreatment === 'pcls' || $taxTreatment === 'tax_deferred') {
                // PCLS, ISA are tax-free; Bonds are tax-deferred (no tax until encashment)
                // All count as tax-free for annual income calculation purposes
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
     *
     * CRITICAL: Uses the SAME allocations as the tax breakdown.
     * The income allocations passed in define EXACTLY how much to withdraw from each source.
     * This ensures the year-by-year table matches the tax breakdown.
     *
     * Process:
     * 1. Use income allocations to determine annual withdrawal per source
     * 2. SIMULATE with actual allocations first
     * 3. ONLY reduce if funds deplete before age 95 (5-year tolerance)
     * 4. Year-by-year: Withdraw per allocation, then apply growth
     */
    public function projectFundDepletion(int $userId, array $incomeAllocations, int $retirementAge, ?array $statePensionStatus = null, float $projectedPensionPot = 0, int $yearsToRetirement = 0, ?array $availableAccounts = null): array
    {
        // Initialize fund balances - PCLS and Drawdown as SEPARATE buckets
        // Pass availableAccounts to ensure values match what was used for PMT calculation
        $fundBalances = $this->initializeFundBalancesWithPclsSplit($userId, $incomeAllocations, $projectedPensionPot, $yearsToRetirement, $availableAccounts);

        // Calculate total starting funds
        $totalStartingFunds = array_sum($fundBalances);

        // Build withdrawal map from allocations - this is what the tax breakdown uses
        $allocationWithdrawals = $this->buildWithdrawalMapFromAllocations($incomeAllocations);

        // Get target income from allocations
        $targetAnnualIncome = array_sum($allocationWithdrawals);

        // Calculate years in retirement
        $yearsInRetirement = self::PROJECTION_END_AGE - $retirementAge;

        // FIRST: Simulate with actual allocations (no scaling) to check if funds deplete before age 100
        $testResult = $this->simulateDepletion($fundBalances, $allocationWithdrawals, $retirementAge, 1.0);

        $scaleFactor = 1.0;
        $incomeWasAdjusted = false;
        $actualAnnualWithdrawal = $targetAnnualIncome;

        // Calculate sustainable withdrawal rate (for reference only)
        $avgGrowthRate = $this->calculateWeightedGrowthRate($fundBalances);
        $sustainableWithdrawal = $this->calculateSustainableWithdrawalRate($totalStartingFunds, $yearsInRetirement, $avgGrowthRate);

        // ONLY adjust if funds actually deplete BEFORE age 100
        // If final balance > 0, NO adjustment needed regardless of sustainable withdrawal formula
        if ($testResult['depletes_early'] && $testResult['final_balance'] <= 0) {
            // Funds will run out before age 100 - calculate reduced income
            if ($targetAnnualIncome > 0 && $sustainableWithdrawal < $targetAnnualIncome) {
                $scaleFactor = $sustainableWithdrawal / $targetAnnualIncome;
                $actualAnnualWithdrawal = $sustainableWithdrawal;
                $incomeWasAdjusted = true;
            }
        }

        // Reset fund balances for actual projection
        $fundBalances = $this->initializeFundBalancesWithPclsSplit($userId, $incomeAllocations, $projectedPensionPot, $yearsToRetirement, $availableAccounts);

        // Track initial balances for depletion detection (pcls and drawdown separate)
        $initialBalances = [
            'pcls' => $fundBalances['pension_pot_pcls'] ?? 0,
            'drawdown' => $fundBalances['pension_pot_drawdown'] ?? 0,
            'isa' => 0,
            'bond' => 0,
            'gia' => 0,
            'savings' => 0,
        ];
        foreach ($fundBalances as $fundKey => $balance) {
            $type = $this->getFundTypeFromKey($fundKey);
            if ($type && ! in_array($type, ['pcls', 'drawdown'])) {
                $initialBalances[$type] += $balance;
            }
        }

        // =============================================================================
        // TAX CALCULATION SETUP
        // Extract guaranteed taxable income (State Pension, DB Pension) that uses PA first
        // =============================================================================
        $incomeTax = $this->taxConfig->getIncomeTax();
        $personalAllowance = $incomeTax['personal_allowance'];
        $basicRate = $incomeTax['basic_rate'] ?? 0.20;

        // Get State Pension and DB Pension amounts from allocations
        $statePensionAmount = 0;
        $dbPensionAmount = 0;
        $statePensionStartAge = 67;  // Default

        foreach ($incomeAllocations as $allocation) {
            $sourceType = $allocation['source_type'] ?? '';
            $amount = (float) ($allocation['annual_amount'] ?? 0);

            if ($sourceType === 'state_pension') {
                $statePensionAmount = $amount;
                $statePensionStartAge = $allocation['starts_at_age'] ?? 67;
            } elseif ($sourceType === 'db_pension') {
                $dbPensionAmount += $amount;
            }
        }

        $projections = [];
        $aggregatedDepleted = [];

        for ($age = $retirementAge; $age <= self::PROJECTION_END_AGE; $age++) {
            $yearData = [
                'age' => $age,
                'total_income' => 0,
                'withdrawals' => ['pcls' => 0, 'drawdown' => 0, 'isa' => 0, 'bond' => 0, 'gia' => 0, 'savings' => 0],
                'growth' => ['pcls' => 0, 'drawdown' => 0, 'isa' => 0, 'bond' => 0, 'gia' => 0, 'savings' => 0],
                // Tax calculation fields
                'state_pension' => 0,
                'db_pension' => 0,
                'guaranteed_income' => 0,
                'remaining_pa' => $personalAllowance,
                'pa_drawdown' => 0,      // Drawdown covered by PA (tax-free)
                'taxable_drawdown' => 0, // Drawdown over PA (taxed)
                'tax_paid' => 0,
            ];

            // =============================================================================
            // ALLOCATION-BASED WITHDRAWAL: Follow calculated PMT amounts
            // Withdraw the allocated annual amount from each fund (matching simulateDepletion)
            // Bond 5% is still mandatory but calculated in allocations
            // =============================================================================

            // Helper function to withdraw from a specific fund key
            $withdrawFromFundKey = function ($fundKey, $maxAmount) use (&$fundBalances, &$yearData, &$aggregatedDepleted, $age) {
                if (! isset($fundBalances[$fundKey]) || $fundBalances[$fundKey] <= 0 || $maxAmount <= 0) {
                    return 0;
                }

                $withdrawal = min($fundBalances[$fundKey], $maxAmount);
                $fundBalances[$fundKey] -= $withdrawal;
                $yearData['total_income'] += $withdrawal;

                $fundType = $this->getFundTypeFromKey($fundKey);
                if ($fundType) {
                    $yearData['withdrawals'][$fundType] += $withdrawal;
                }

                if ($fundBalances[$fundKey] <= 0 && ! isset($aggregatedDepleted[$fundKey])) {
                    $aggregatedDepleted[$fundKey] = $age;
                }

                return $withdrawal;
            };

            // Helper function to withdraw from a specific fund type (for Bond 5% mandatory)
            $withdrawFromFundType = function ($fundType, $maxAmount) use (&$fundBalances, &$yearData, &$aggregatedDepleted, $age) {
                $withdrawn = 0;
                foreach ($fundBalances as $fundKey => $balance) {
                    if ($balance <= 0 || $maxAmount <= 0) {
                        continue;
                    }

                    $keyType = $this->getFundTypeFromKey($fundKey);
                    if ($keyType !== $fundType) {
                        continue;
                    }

                    $withdrawal = min($balance, $maxAmount - $withdrawn);
                    $fundBalances[$fundKey] -= $withdrawal;
                    $withdrawn += $withdrawal;
                    $yearData['total_income'] += $withdrawal;
                    $yearData['withdrawals'][$fundType] += $withdrawal;

                    if ($fundBalances[$fundKey] <= 0 && ! isset($aggregatedDepleted[$fundKey])) {
                        $aggregatedDepleted[$fundKey] = $age;
                    }

                    if ($withdrawn >= $maxAmount) {
                        break;
                    }
                }

                return $withdrawn;
            };

            // Get available balance for each fund type
            $getAvailableBalance = function ($fundType) use ($fundBalances) {
                $total = 0;
                foreach ($fundBalances as $fundKey => $balance) {
                    if ($this->getFundTypeFromKey($fundKey) === $fundType && $balance > 0) {
                        $total += $balance;
                    }
                }

                return $total;
            };

            // =============================================================================
            // PRIORITY-BASED WITHDRAWAL: Tax-efficient order per documentation
            // Order: Bond 5% (mandatory) → PCLS → ISA → Drawdown → GIA → Savings
            // GOAL: Use ALL tax-free sources FIRST before ANY taxable sources
            // Result: ZERO TAX while tax-free money exists
            //
            // Reference: retireIncomePriority.md lines 711-736, 1026-1031
            // =============================================================================

            // Calculate target income for this year
            // Note: State Pension added separately in tax calculation section
            $yearTargetIncome = $actualAnnualWithdrawal;
            $remainingTarget = $yearTargetIncome;

            // 1. BOND 5% (MANDATORY) - Tax-deferred, always withdraw if bonds exist
            // UK investment bonds allow 5% tax-deferred withdrawal annually
            $bondBalance = $getAvailableBalance('bond');
            if ($bondBalance > 0) {
                $bondPmt = $bondBalance * 0.05;  // 5% of current balance
                $bondWithdrawn = $withdrawFromFundType('bond', $bondPmt);
                $remainingTarget -= $bondWithdrawn;
            }

            // 2. PCLS (TAX-FREE) - Fill the gap first
            // PCLS is 25% of pension pot, completely tax-free
            if ($remainingTarget > 0) {
                $pclsBalance = $getAvailableBalance('pcls');
                if ($pclsBalance > 0) {
                    $pclsWithdrawn = $withdrawFromFundType('pcls', $remainingTarget);
                    $remainingTarget -= $pclsWithdrawn;
                }
            }

            // 3. ISA (TAX-FREE) - Fill remaining gap
            // ISA withdrawals are 100% tax-free
            if ($remainingTarget > 0) {
                $isaBalance = $getAvailableBalance('isa');
                if ($isaBalance > 0) {
                    $isaWithdrawn = $withdrawFromFundType('isa', $remainingTarget);
                    $remainingTarget -= $isaWithdrawn;
                }
            }

            // 4. PENSION DRAWDOWN (TAXABLE) - ONLY if tax-free sources insufficient
            // This is the LAST RESORT for meeting target income
            // Tax calculated later based on PA usage
            if ($remainingTarget > 0) {
                $drawdownBalance = $getAvailableBalance('drawdown');
                if ($drawdownBalance > 0) {
                    $drawdownWithdrawn = $withdrawFromFundType('drawdown', $remainingTarget);
                    $remainingTarget -= $drawdownWithdrawn;
                }
            }

            // 5. GIA (TAXABLE) - If pension insufficient
            if ($remainingTarget > 0) {
                $giaBalance = $getAvailableBalance('gia');
                if ($giaBalance > 0) {
                    $giaWithdrawn = $withdrawFromFundType('gia', $remainingTarget);
                    $remainingTarget -= $giaWithdrawn;
                }
            }

            // 6. SAVINGS (TAXABLE) - Absolute last resort
            if ($remainingTarget > 0) {
                $savingsBalance = $getAvailableBalance('savings');
                if ($savingsBalance > 0) {
                    $savingsWithdrawn = $withdrawFromFundType('savings', $remainingTarget);
                    $remainingTarget -= $savingsWithdrawn;
                }
            }

            // Note: If remainingTarget > 0 after all sources, income is less than target
            // This is correct - we can only withdraw what exists

            // Apply growth to remaining balances AFTER withdrawal
            foreach ($fundBalances as $fundKey => $balance) {
                if ($balance > 0) {
                    $growthRate = $this->getGrowthRateForFund($fundKey);
                    $growthAmount = $balance * $growthRate;
                    $fundBalances[$fundKey] = $balance + $growthAmount;

                    $displayType = $this->getFundTypeFromKey($fundKey);
                    if ($displayType) {
                        $yearData['growth'][$displayType] += $growthAmount;
                    }
                }
            }

            // Aggregate balances by display type for chart (pcls and drawdown separate)
            $yearAggregated = ['pcls' => 0, 'drawdown' => 0, 'isa' => 0, 'bond' => 0, 'gia' => 0, 'savings' => 0];
            foreach ($fundBalances as $fundKey => $balance) {
                $displayType = $this->getFundTypeFromKey($fundKey);
                if ($displayType) {
                    $yearAggregated[$displayType] += max(0, $balance);
                }
            }

            // Record balances for this year
            $yearData['pcls'] = round($yearAggregated['pcls'], 2);
            $yearData['drawdown'] = round($yearAggregated['drawdown'], 2);
            $yearData['isa'] = round($yearAggregated['isa'], 2);
            $yearData['bond'] = round($yearAggregated['bond'], 2);
            $yearData['gia'] = round($yearAggregated['gia'], 2);
            $yearData['savings'] = round($yearAggregated['savings'], 2);
            $yearData['total_funds'] = round(array_sum($yearAggregated), 2);

            // Round withdrawal and growth values
            foreach (['pcls', 'drawdown', 'isa', 'bond', 'gia', 'savings'] as $type) {
                $yearData['withdrawals'][$type] = round($yearData['withdrawals'][$type], 2);
                $yearData['growth'][$type] = round($yearData['growth'][$type], 2);
            }

            // Track when each fund type is fully depleted
            foreach (['pcls', 'drawdown', 'isa', 'bond', 'gia', 'savings'] as $type) {
                if ($yearAggregated[$type] <= 0 && ! isset($aggregatedDepleted[$type]) && $initialBalances[$type] > 0) {
                    $aggregatedDepleted[$type] = $age;
                }
            }

            $yearData['total_income'] = round($yearData['total_income'], 2);

            // =============================================================================
            // TAX CALCULATION FOR THIS YEAR
            // State Pension and DB Pension use Personal Allowance FIRST
            // Pension drawdown is only taxed on amount exceeding remaining PA
            // =============================================================================

            // Add State Pension if age >= state pension age
            $yearStatePension = ($age >= $statePensionStartAge) ? $statePensionAmount : 0;
            $yearDbPension = $dbPensionAmount;  // DB pension available from retirement age

            $yearData['state_pension'] = round($yearStatePension, 2);
            $yearData['db_pension'] = round($yearDbPension, 2);
            $yearData['guaranteed_income'] = round($yearStatePension + $yearDbPension, 2);

            // Calculate remaining PA after guaranteed income
            $guaranteedTaxable = $yearStatePension + $yearDbPension;
            $remainingPa = max(0, $personalAllowance - $guaranteedTaxable);
            $yearData['remaining_pa'] = round($remainingPa, 2);

            // Calculate how much of drawdown is covered by PA vs taxable
            $drawdownWithdrawal = $yearData['withdrawals']['drawdown'];
            $paDrawdown = min($drawdownWithdrawal, $remainingPa);  // Covered by remaining PA
            $taxableDrawdown = max(0, $drawdownWithdrawal - $remainingPa);  // Over PA = taxable

            $yearData['pa_drawdown'] = round($paDrawdown, 2);
            $yearData['taxable_drawdown'] = round($taxableDrawdown, 2);

            // Calculate tax paid (basic rate on taxable drawdown + guaranteed income over PA)
            $totalTaxableIncome = $guaranteedTaxable + $drawdownWithdrawal;
            $taxableAmount = max(0, $totalTaxableIncome - $personalAllowance);
            $taxPaid = $taxableAmount * $basicRate;
            $yearData['tax_paid'] = round($taxPaid, 2);

            $projections[] = $yearData;
        }

        // Calculate total depletion age (when ALL funds hit zero)
        $finalProjection = end($projections);
        $totalDepletionAge = null;
        if ($finalProjection && $finalProjection['total_funds'] <= 0) {
            foreach ($projections as $proj) {
                if ($proj['total_funds'] <= 0) {
                    $totalDepletionAge = $proj['age'];
                    break;
                }
            }
        }

        return [
            'projections' => $projections,
            'depletion_ages' => ['total' => $totalDepletionAge ?? 100] + $aggregatedDepleted,
            'sustainable_withdrawal' => round($sustainableWithdrawal, 2),
            'actual_withdrawal' => round($actualAnnualWithdrawal, 2),
            'income_was_adjusted' => $incomeWasAdjusted,
            'total_starting_funds' => round($totalStartingFunds, 2),
        ];
    }

    /**
     * Simulate depletion to check if funds run out early.
     * Returns whether funds deplete before age 100 and at what age.
     */
    private function simulateDepletion(array $fundBalances, array $allocationWithdrawals, int $retirementAge, float $scaleFactor): array
    {
        $balances = $fundBalances; // Copy
        $depletionAge = null;

        for ($age = $retirementAge; $age <= self::PROJECTION_END_AGE; $age++) {
            // Withdraw according to allocations
            foreach ($allocationWithdrawals as $fundKey => $annualAmount) {
                $scaledAmount = $annualAmount * $scaleFactor;

                // Find actual fund key (handle ISA variations)
                $actualFundKey = $fundKey;
                if (! isset($balances[$fundKey])) {
                    if (str_starts_with($fundKey, 'isa_investment_')) {
                        $altKey = str_replace('isa_investment_', 'isa_savings_', $fundKey);
                        if (isset($balances[$altKey])) {
                            $actualFundKey = $altKey;
                        }
                    } elseif (str_starts_with($fundKey, 'isa_savings_')) {
                        $altKey = str_replace('isa_savings_', 'isa_investment_', $fundKey);
                        if (isset($balances[$altKey])) {
                            $actualFundKey = $altKey;
                        }
                    }
                }

                if (isset($balances[$actualFundKey]) && $balances[$actualFundKey] > 0) {
                    $withdrawal = min($balances[$actualFundKey], $scaledAmount);
                    $balances[$actualFundKey] -= $withdrawal;
                }
            }

            // Apply growth
            foreach ($balances as $fundKey => $balance) {
                if ($balance > 0) {
                    $growthRate = $this->getGrowthRateForFund($fundKey);
                    $balances[$fundKey] = $balance * (1 + $growthRate);
                }
            }

            // Check total
            $totalFunds = array_sum($balances);
            if ($totalFunds <= 0 && $depletionAge === null) {
                $depletionAge = $age;
                break;
            }
        }

        return [
            'depletes_early' => $depletionAge !== null && $depletionAge < self::PROJECTION_END_AGE,
            'depletion_age' => $depletionAge,
            'final_balance' => array_sum($balances),
        ];
    }

    /**
     * Build withdrawal map from income allocations.
     * Maps each allocation to a fund key for the depletion projection.
     */
    private function buildWithdrawalMapFromAllocations(array $incomeAllocations): array
    {
        $withdrawals = [];

        foreach ($incomeAllocations as $allocation) {
            $sourceType = $allocation['source_type'] ?? '';
            $sourceId = $allocation['source_id'] ?? '';
            $amount = (float) ($allocation['annual_amount'] ?? 0);

            if ($amount <= 0) {
                continue;
            }

            // Map source type to fund key
            $fundKey = null;

            if ($sourceType === 'pension_pot_pcls') {
                $fundKey = 'pension_pot_pcls';
            } elseif ($sourceType === 'pension_pot_drawdown') {
                $fundKey = 'pension_pot_drawdown';
            } elseif ($sourceType === 'onshore_bond' && $sourceId) {
                $fundKey = 'onshore_bond_'.$sourceId;
            } elseif ($sourceType === 'offshore_bond' && $sourceId) {
                $fundKey = 'offshore_bond_'.$sourceId;
            } elseif ($sourceType === 'isa' && $sourceId) {
                // Could be savings or investment ISA - check both
                $fundKey = 'isa_investment_'.$sourceId;
                // Fallback handled in projection loop
            } elseif ($sourceType === 'gia' && $sourceId) {
                $fundKey = 'gia_'.$sourceId;
            } elseif ($sourceType === 'savings' && $sourceId) {
                $fundKey = 'savings_'.$sourceId;
            }

            if ($fundKey) {
                $withdrawals[$fundKey] = ($withdrawals[$fundKey] ?? 0) + $amount;
            }
        }

        return $withdrawals;
    }

    /**
     * Calculate weighted average growth rate based on fund balances.
     */
    private function calculateWeightedGrowthRate(array $fundBalances): float
    {
        $totalBalance = array_sum($fundBalances);
        if ($totalBalance <= 0) {
            return $this->getDefaultGrowthRate();
        }

        $weightedSum = 0;
        foreach ($fundBalances as $fundKey => $balance) {
            $growthRate = $this->getGrowthRateForFund($fundKey);
            $weightedSum += $balance * $growthRate;
        }

        return $weightedSum / $totalBalance;
    }

    /**
     * Calculate sustainable annual withdrawal that depletes funds to £0 at end of period.
     *
     * Uses the PMT formula: PMT = PV * (r * (1 + r)^n) / ((1 + r)^n - 1)
     * Where: PV = present value, r = growth rate, n = years
     */
    private function calculateSustainableWithdrawalRate(float $totalFunds, int $years, float $growthRate): float
    {
        if ($totalFunds <= 0 || $years <= 0) {
            return 0;
        }

        // If no growth, simple division
        if ($growthRate <= 0) {
            return $totalFunds / $years;
        }

        // PMT formula for annuity
        $r = $growthRate;
        $n = $years;
        $factor = pow(1 + $r, $n);

        return $totalFunds * ($r * $factor) / ($factor - 1);
    }

    /**
     * Map fund key to aggregated type for chart display.
     *
     * pension_pot_pcls and pension_pot_drawdown both map to 'pension_pot'
     * for display purposes, but are tracked separately for withdrawal order.
     */
    private function getFundTypeFromKey(string $fundKey): ?string
    {
        // PCLS and Drawdown are SEPARATE display types
        if ($fundKey === 'pension_pot_pcls') {
            return 'pcls';
        }
        if ($fundKey === 'pension_pot_drawdown' || $fundKey === 'pension_pot') {
            return 'drawdown';
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
    private function calculateDefaultAllocations(array $availableAccounts, float $targetIncome, int $retirementAge, ?array $statePensionStatus = null): array
    {
        $allocations = [];
        $incomeTax = $this->taxConfig->getIncomeTax();
        $personalAllowance = $incomeTax['personal_allowance'];

        // Calculate years in retirement for depletion calculations
        $yearsInRetirement = max(1, self::PROJECTION_END_AGE - $retirementAge);

        // =============================================================================
        // STEP 1: Add Guaranteed Income (unavoidable - State Pension, DB Pension)
        // These are TAXABLE and use the Personal Allowance FIRST
        // Any pension drawdown will only be taxed on amounts exceeding remaining PA
        // =============================================================================
        $guaranteedIncome = 0;
        $guaranteedTaxableIncome = 0;  // Track taxable portion for PA calculation

        // State Pension - only if retirement age >= state pension age
        // State Pension is TAXABLE and uses Personal Allowance FIRST
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
                    $guaranteedTaxableIncome += $account['annual_income'];  // Uses PA first
                }
            }
        }

        // DB Pensions - only if retirement age >= normal retirement age
        // DB Pension is TAXABLE and uses Personal Allowance (after State Pension)
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
                    $guaranteedTaxableIncome += $account['annual_income'];  // Uses PA after State Pension
                }
            }
        }

        // Calculate remaining Personal Allowance after guaranteed taxable income
        // This determines how much pension drawdown (if any) can be tax-free
        $remainingPersonalAllowance = max(0, $personalAllowance - $guaranteedTaxableIncome);

        // Calculate how much more income we need beyond guaranteed
        $remainingTarget = max(0, $targetIncome - $guaranteedIncome);

        // =============================================================================
        // STEP 2: Calculate PMT withdrawals to DEPLETE tax-free accounts at age 100
        // =============================================================================
        // This ensures tax-free accounts reach £0 at age 100, maximising tax efficiency

        // 2a: PCLS Annual = PCLS Total ÷ Years in Retirement (no growth on PCLS cash)
        $pclsAnnualPmt = 0;
        $pclsSubAccount = null;
        $pclsBalance = 0;
        foreach ($availableAccounts as $account) {
            if ($account['type'] === 'pension_pot' && isset($account['sub_accounts'])) {
                foreach ($account['sub_accounts'] as $subAccount) {
                    if ($subAccount['source_type'] === 'pension_pot_pcls') {
                        $pclsBalance = $subAccount['max_amount'] ?? 0;
                        $pclsAnnualPmt = $pclsBalance / $yearsInRetirement;
                        $pclsSubAccount = $subAccount;
                        break 2;
                    }
                }
            }
        }

        // 2b: Bond 5% = UK investment bonds allow 5% tax-deferred withdrawal per year
        // This is the 5% allowance rule - you can withdraw up to 5% of original capital annually
        // without triggering an immediate tax charge (tax-deferred, not tax-free)
        $bondAccounts = [];
        $totalBondPmt = 0;
        foreach ($availableAccounts as $account) {
            if ($account['type'] === 'onshore_bond' || $account['type'] === 'offshore_bond') {
                $bondBalance = $account['value'] ?? 0;
                // 5% of original capital (the balance at retirement) - UK tax-deferred withdrawal rule
                $bondPmt = $bondBalance * 0.05;
                $totalBondPmt += $bondPmt;
                $bondAccounts[] = [
                    'account' => $account,
                    'pmt' => $bondPmt,
                    'balance' => $bondBalance,
                ];
            }
        }

        // 2c: ISA PMT = Calculate withdrawal to deplete at age 100 (4% growth for S&S ISA, 0% for cash)
        $isaAccounts = [];
        $totalIsaPmt = 0;
        foreach ($availableAccounts as $account) {
            if ($account['type'] === 'isa_cash' || $account['type'] === 'isa_investment') {
                $isaBalance = $account['value'] ?? 0;
                // Cash ISA has no growth, investment ISA has 4% growth
                $growthRate = ($account['type'] === 'isa_cash') ? 0 : $this->getDefaultGrowthRate();
                $isaPmt = $this->calculateSustainableWithdrawalRate($isaBalance, $yearsInRetirement, $growthRate);
                $totalIsaPmt += $isaPmt;
                $isaAccounts[] = [
                    'account' => $account,
                    'pmt' => $isaPmt,
                    'balance' => $isaBalance,
                ];
            }
        }

        // Total tax-free PMT available (depletes all tax-free at age 100)
        $totalTaxFreePmt = $pclsAnnualPmt + $totalBondPmt + $totalIsaPmt;

        // =============================================================================
        // STEP 3: Allocate TAX-FREE sources to FILL THE GAP - NO TAX!
        // Priority: Bond 5% (mandatory) → PCLS → ISA → Pension Drawdown (LAST RESORT)
        // Goal: Use tax-free sources to cover the ENTIRE remaining target
        // Only use pension drawdown when tax-free sources are INSUFFICIENT
        // =============================================================================

        // 3a: Bond PMT withdrawals FIRST (MANDATORY - tax-deferred, using PMT to deplete at 100)
        // Bond 5% must ALWAYS be used if bonds exist
        foreach ($bondAccounts as $bondData) {
            $account = $bondData['account'];
            $bondPmt = $bondData['pmt'];
            if ($bondPmt > 0) {
                $allocations[] = [
                    'source_type' => $account['type'],
                    'source_id' => $account['id'],
                    'name' => $account['name'],
                    'annual_amount' => round($bondPmt, 2),  // FULL PMT - not capped
                    'starting_balance' => $bondData['balance'],
                    'tax_rate' => 0,
                    'tax_treatment' => 'tax_deferred',
                ];
                $remainingTarget -= $bondPmt;
            }
        }

        // 3b: PCLS - FILL THE GAP (tax-free, draw what's needed to avoid pension drawdown)
        // If we need more income, draw from PCLS first (will deplete sooner, but NO TAX)
        if ($pclsSubAccount && $pclsBalance > 0 && $remainingTarget > 0) {
            // Calculate max sustainable withdrawal from PCLS (no growth on cash)
            $maxPclsWithdrawal = $pclsBalance / $yearsInRetirement;
            // Draw what we need OR the max sustainable, whichever is less
            $pclsAllocation = min($remainingTarget, $maxPclsWithdrawal);
            // But if ISA can't cover the rest, we may need to draw more from PCLS
            // For now, use max sustainable to spread depletion
            $pclsAllocation = min($remainingTarget, $pclsBalance / max(1, $yearsInRetirement));

            $allocations[] = [
                'source_type' => 'pension_pot_pcls',
                'source_id' => $pclsSubAccount['source_id'],
                'name' => $pclsSubAccount['name'],
                'annual_amount' => round($pclsAllocation, 2),
                'tax_rate' => 0,
                'tax_treatment' => 'tax_free',
            ];
            $remainingTarget -= $pclsAllocation;

            // Also add the Pension Drawdown card (even if £0 withdrawal for now)
            // This shows the taxable pension pot available for future use
            foreach ($availableAccounts as $account) {
                if ($account['type'] === 'pension_pot' && isset($account['sub_accounts'])) {
                    foreach ($account['sub_accounts'] as $subAccount) {
                        if ($subAccount['source_type'] === 'pension_pot_drawdown') {
                            $drawdownBalance = $subAccount['max_amount'] ?? 0;
                            $allocations[] = [
                                'source_type' => 'pension_pot_drawdown',
                                'source_id' => $subAccount['source_id'],
                                'name' => $subAccount['name'],
                                'annual_amount' => 0, // Not drawing yet - tax-free sources covering target
                                'starting_balance' => $drawdownBalance,
                                'max_amount' => $drawdownBalance,
                                'tax_rate' => null,
                                'tax_treatment' => 'taxable',
                            ];
                            break 2;
                        }
                    }
                }
            }
        }

        // 3c: ISA - FILL THE REMAINING GAP (tax-free)
        // After Bond and PCLS, use ISA to cover whatever is left
        if ($remainingTarget > 0) {
            $totalIsaBalance = array_sum(array_column($isaAccounts, 'balance'));

            foreach ($isaAccounts as $isaData) {
                if ($remainingTarget <= 0) {
                    break;
                }

                $account = $isaData['account'];
                $isaBalance = $isaData['balance'];

                if ($isaBalance <= 0) {
                    continue;
                }

                // Calculate PMT to deplete ISA at age 100
                $growthRate = ($account['type'] === 'isa_cash') ? 0 : $this->getDefaultGrowthRate();
                $isaPmt = $this->calculateSustainableWithdrawalRate($isaBalance, $yearsInRetirement, $growthRate);

                // ISA should use PMT (to deplete at 100), NOT fill the gap
                // Per documentation: "ISA is MANDATORY - ALWAYS allocate full PMT to deplete at age 100"
                // If PMT exceeds remaining target, reduce to avoid over-allocation (zero tax scenario)
                // The remaining gap after ISA PMT goes to pension drawdown
                $isaAllocation = min($isaPmt, $remainingTarget);

                if ($isaAllocation > 0) {
                    $allocations[] = [
                        'source_type' => 'isa',
                        'source_id' => $account['id'],
                        'name' => $account['name'],
                        'annual_amount' => round($isaAllocation, 2),
                        'starting_balance' => $isaBalance,
                        'account_type' => $account['type'],
                        'tax_rate' => 0,
                        'tax_treatment' => 'tax_free',
                    ];
                    $remainingTarget -= $isaAllocation;
                }
            }
        }

        // =============================================================================
        // STEP 4: ONLY if tax-free sources CANNOT cover target, use TAXABLE sources
        // This should rarely happen if user has sufficient tax-free assets
        // Pension drawdown is the LAST RESORT - only used when tax-free is exhausted
        //
        // TAX CALCULATION:
        // - State Pension and DB Pension use Personal Allowance FIRST
        // - Remaining PA available for pension drawdown: £{$remainingPersonalAllowance}
        // - Only pension drawdown EXCEEDING remaining PA is taxed at Basic Rate
        // =============================================================================

        if ($remainingTarget > 0) {
            // Tax-free sources couldn't cover the target - need taxable income
            // Note: First £{$remainingPersonalAllowance} of pension drawdown is tax-free (remaining PA)
            // Gather all taxable sources with their PMT rates
            $taxableSources = [];

            // Pension Pot Drawdown
            foreach ($availableAccounts as $account) {
                if ($account['type'] === 'pension_pot' && isset($account['sub_accounts'])) {
                    foreach ($account['sub_accounts'] as $subAccount) {
                        if ($subAccount['source_type'] === 'pension_pot_drawdown') {
                            $drawdownBalance = $subAccount['max_amount'] ?? 0;
                            $drawdownPmt = $this->calculateSustainableWithdrawalRate($drawdownBalance, $yearsInRetirement, $this->getDefaultGrowthRate());
                            $taxableSources[] = [
                                'source_type' => 'pension_pot_drawdown',
                                'source_id' => $subAccount['source_id'],
                                'name' => $subAccount['name'],
                                'balance' => $drawdownBalance,
                                'pmt' => $drawdownPmt,
                                'tax_treatment' => 'taxable',
                            ];
                        }
                    }
                }
            }

            // GIA - include from the start to spread taxable load
            foreach ($availableAccounts as $account) {
                if ($account['type'] === 'gia') {
                    $giaBalance = $account['value'] ?? 0;
                    $giaPmt = $this->calculateSustainableWithdrawalRate($giaBalance, $yearsInRetirement, $this->getDefaultGrowthRate());
                    $taxableSources[] = [
                        'source_type' => 'gia',
                        'source_id' => $account['id'],
                        'name' => $account['name'],
                        'balance' => $giaBalance,
                        'pmt' => $giaPmt,
                        'tax_treatment' => 'taxable',
                    ];
                }
            }

            // Savings
            foreach ($availableAccounts as $account) {
                if ($account['type'] === 'savings') {
                    $savingsBalance = $account['value'] ?? 0;
                    // Savings typically have lower/no growth
                    $savingsPmt = $this->calculateSustainableWithdrawalRate($savingsBalance, $yearsInRetirement, 0.02);
                    $taxableSources[] = [
                        'source_type' => 'savings',
                        'source_id' => $account['id'],
                        'name' => $account['name'],
                        'balance' => $savingsBalance,
                        'pmt' => $savingsPmt,
                        'tax_treatment' => 'taxable',
                    ];
                }
            }

            // Calculate total taxable PMT and balance
            $totalTaxablePmt = array_sum(array_column($taxableSources, 'pmt'));
            $totalTaxableBalance = array_sum(array_column($taxableSources, 'balance'));

            // Check if taxable sources can cover the remaining target sustainably
            if ($totalTaxablePmt >= $remainingTarget) {
                // Sustainable: split remaining target proportionally by balance
                foreach ($taxableSources as $source) {
                    if ($remainingTarget <= 0 || $totalTaxableBalance <= 0) {
                        break;
                    }

                    // Calculate proportional share based on balance
                    $proportion = $source['balance'] / $totalTaxableBalance;
                    $taxableAmount = min($source['pmt'], $remainingTarget * $proportion, $remainingTarget);

                    if ($taxableAmount > 0) {
                        $allocations[] = [
                            'source_type' => $source['source_type'],
                            'source_id' => $source['source_id'],
                            'name' => $source['name'],
                            'annual_amount' => round($taxableAmount, 2),
                            'starting_balance' => $source['balance'],  // Include projected balance
                            'tax_rate' => null,
                            'tax_treatment' => $source['tax_treatment'],
                        ];
                        $remainingTarget -= $taxableAmount;
                    }
                }
            } else {
                // Not sustainable: draw what's needed to meet target, even if funds deplete faster
                // User wants target income - we'll show them the depletion impact in the projection
                foreach ($taxableSources as $source) {
                    if ($remainingTarget <= 0) {
                        break;
                    }

                    // Calculate what we need from this source
                    // If there's only one taxable source, it should cover the full remaining target
                    $proportion = ($totalTaxableBalance > 0) ? ($source['balance'] / $totalTaxableBalance) : 1;
                    $taxableAmount = $remainingTarget * $proportion;

                    // Cap at what the fund can realistically provide per year (balance / years remaining)
                    // But allow higher withdrawals if needed to meet target - user accepts faster depletion
                    $maxAnnual = $source['balance'] / max(1, $yearsInRetirement);
                    // Allow up to 3x sustainable to meet target, accepting earlier depletion
                    $taxableAmount = min($taxableAmount, $maxAnnual * 3, $remainingTarget);

                    if ($taxableAmount > 0) {
                        $allocations[] = [
                            'source_type' => $source['source_type'],
                            'source_id' => $source['source_id'],
                            'name' => $source['name'],
                            'annual_amount' => round($taxableAmount, 2),
                            'starting_balance' => $source['balance'],  // Include projected balance
                            'tax_rate' => null,
                            'tax_treatment' => $source['tax_treatment'],
                        ];
                        $remainingTarget -= $taxableAmount;
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
     * Initialize fund balances with PCLS and Drawdown as SEPARATE buckets.
     *
     * PCLS = 25% of projected pension pot (tax-free)
     * Drawdown = 75% of projected pension pot (taxable)
     *
     * This allows correct withdrawal order: PCLS first, then drawdown later.
     *
     * @param  array|null  $availableAccounts  If provided, use these values directly for consistent projection
     */
    private function initializeFundBalancesWithPclsSplit(int $userId, array $incomeAllocations, float $projectedPensionPot = 0, int $yearsToRetirement = 0, ?array $availableAccounts = null): array
    {
        $balances = [];

        // =============================================================================
        // CRITICAL: Build a lookup map from availableAccounts for CONSISTENT values
        // This ensures the projection uses the SAME values that were used to calculate
        // PMT rates in calculateDefaultAllocations()
        // =============================================================================
        $accountValueMap = [];
        if ($availableAccounts !== null) {
            foreach ($availableAccounts as $account) {
                $type = $account['type'] ?? '';
                $id = $account['id'] ?? null;
                $value = $account['value'] ?? null;

                if ($id === null || $value === null) {
                    continue;
                }

                // Map by type and ID
                if ($type === 'isa_investment' || $type === 'isa_cash') {
                    $prefix = ($type === 'isa_cash') ? 'isa_savings_' : 'isa_investment_';
                    $accountValueMap[$prefix.$id] = $value;
                } elseif ($type === 'onshore_bond') {
                    $accountValueMap['onshore_bond_'.$id] = $value;
                } elseif ($type === 'offshore_bond') {
                    $accountValueMap['offshore_bond_'.$id] = $value;
                } elseif ($type === 'gia') {
                    $accountValueMap['gia_'.$id] = $value;
                } elseif ($type === 'savings') {
                    $accountValueMap['savings_'.$id] = $value;
                }
            }
        }

        // =============================================================================
        // ALSO use starting_balance from allocations as fallback
        // This ensures the projection uses the SAME values that were used to calculate
        // PMT rates, avoiding mismatches from re-querying Monte Carlo projections
        // =============================================================================

        // Build maps: account IDs AND their starting balances from allocations
        $allocationBalances = [];
        $allocatedAccounts = [];
        $hasPensionPotAllocation = false;

        foreach ($incomeAllocations as $allocation) {
            $sourceType = $allocation['source_type'] ?? '';
            $sourceId = $allocation['source_id'] ?? 0;
            $startingBalance = $allocation['starting_balance'] ?? null;

            if (in_array($sourceType, ['pension_pot_pcls', 'pension_pot_drawdown'])) {
                $hasPensionPotAllocation = true;
            } elseif ($sourceType === 'isa' && $sourceId) {
                // Store the starting balance keyed by source_id
                $accountType = $allocation['account_type'] ?? 'isa_investment';
                $isCashIsa = str_contains($accountType, 'cash');
                // Track cash vs investment ISAs separately to avoid ID collisions across tables
                if ($isCashIsa) {
                    $allocatedAccounts['isa_cash'][] = $sourceId;
                    $allocationBalances['isa_savings_'.$sourceId] = $startingBalance;
                } else {
                    $allocatedAccounts['isa_investment'][] = $sourceId;
                    $allocationBalances['isa_investment_'.$sourceId] = $startingBalance;
                }
            } elseif ($sourceType === 'onshore_bond' && $sourceId) {
                $allocatedAccounts['onshore_bond'][] = $sourceId;
                $allocationBalances['onshore_bond_'.$sourceId] = $startingBalance;
            } elseif ($sourceType === 'offshore_bond' && $sourceId) {
                $allocatedAccounts['offshore_bond'][] = $sourceId;
                $allocationBalances['offshore_bond_'.$sourceId] = $startingBalance;
            } elseif ($sourceType === 'gia' && $sourceId) {
                $allocatedAccounts['gia'][] = $sourceId;
                $allocationBalances['gia_'.$sourceId] = $startingBalance;
            } elseif ($sourceType === 'savings' && $sourceId) {
                $allocatedAccounts['savings'][] = $sourceId;
                $allocationBalances['savings_'.$sourceId] = $startingBalance;
            } elseif ($sourceType === 'pension_pot_drawdown') {
                // Drawdown balance comes from projected pension pot
                $allocationBalances['pension_pot_drawdown'] = $startingBalance;
            }
        }

        // Pension Pot - split into PCLS (25%) and Drawdown (75%) as SEPARATE buckets
        if ($hasPensionPotAllocation && $projectedPensionPot > 0) {
            $balances['pension_pot_pcls'] = $projectedPensionPot * 0.25;      // 25% tax-free
            $balances['pension_pot_drawdown'] = $projectedPensionPot * 0.75; // 75% taxable
        }

        // Cash growth rate
        $cashGrowthRate = 0.02;

        // Load only accounts that are in allocations
        // Cash ISAs and Investment ISAs use separate ID arrays to avoid collisions
        $cashIsaIds = $allocatedAccounts['isa_cash'] ?? [];
        $investmentIsaIds = $allocatedAccounts['isa_investment'] ?? [];
        $onshoreBondIds = $allocatedAccounts['onshore_bond'] ?? [];
        $offshoreBondIds = $allocatedAccounts['offshore_bond'] ?? [];
        $giaIds = $allocatedAccounts['gia'] ?? [];
        $savingsIds = $allocatedAccounts['savings'] ?? [];

        // ISAs (Cash/Savings) - only those explicitly allocated as cash ISAs
        if (! empty($cashIsaIds)) {
            $isaAccounts = SavingsAccount::whereIn('id', $cashIsaIds)->where('is_isa', true)->get();
            foreach ($isaAccounts as $account) {
                $fundKey = 'isa_savings_'.$account->id;
                // Priority: 1) accountValueMap (from availableAccounts), 2) allocationBalances, 3) DB query
                if (isset($accountValueMap[$fundKey])) {
                    $balances[$fundKey] = $accountValueMap[$fundKey];
                } elseif (isset($allocationBalances[$fundKey]) && $allocationBalances[$fundKey] !== null) {
                    $balances[$fundKey] = $allocationBalances[$fundKey];
                } else {
                    $currentValue = (float) ($account->current_balance ?? 0);
                    $balances[$fundKey] = $currentValue * pow(1 + $cashGrowthRate, $yearsToRetirement);
                }
            }
        }

        // ISAs (Investment) - only those explicitly allocated as investment ISAs
        if (! empty($investmentIsaIds)) {
            $investmentIsas = InvestmentAccount::whereIn('id', $investmentIsaIds)
                ->whereIn('account_type', ['isa', 'stocks_shares_isa', 'lifetime_isa'])
                ->get();
            foreach ($investmentIsas as $account) {
                $fundKey = 'isa_investment_'.$account->id;
                // Priority: 1) accountValueMap (from availableAccounts), 2) allocationBalances, 3) DB query
                if (isset($accountValueMap[$fundKey])) {
                    $balances[$fundKey] = $accountValueMap[$fundKey];
                } elseif (isset($allocationBalances[$fundKey]) && $allocationBalances[$fundKey] !== null) {
                    $balances[$fundKey] = $allocationBalances[$fundKey];
                } else {
                    $currentValue = (float) ($account->current_value ?? 0);
                    $accountUser = User::find($account->user_id);
                    $balances[$fundKey] = $yearsToRetirement > 0 && $accountUser
                        ? $this->investmentProjectionService->getAccountProjectedValue80($account, $accountUser, $yearsToRetirement)
                        : $currentValue;
                }
            }
        }

        // Onshore Bonds - only those in allocations
        if (! empty($onshoreBondIds)) {
            $onshoreBonds = InvestmentAccount::whereIn('id', $onshoreBondIds)
                ->where('account_type', 'onshore_bond')
                ->get();
            foreach ($onshoreBonds as $account) {
                $fundKey = 'onshore_bond_'.$account->id;
                // Priority: 1) accountValueMap (from availableAccounts), 2) allocationBalances, 3) DB query
                if (isset($accountValueMap[$fundKey])) {
                    $balances[$fundKey] = $accountValueMap[$fundKey];
                } elseif (isset($allocationBalances[$fundKey]) && $allocationBalances[$fundKey] !== null) {
                    $balances[$fundKey] = $allocationBalances[$fundKey];
                } else {
                    $currentValue = (float) ($account->current_value ?? 0);
                    $accountUser = User::find($account->user_id);
                    $balances[$fundKey] = $yearsToRetirement > 0 && $accountUser
                        ? $this->investmentProjectionService->getAccountProjectedValue80($account, $accountUser, $yearsToRetirement)
                        : $currentValue;
                }
            }
        }

        // Offshore Bonds - only those in allocations
        if (! empty($offshoreBondIds)) {
            $offshoreBonds = InvestmentAccount::whereIn('id', $offshoreBondIds)
                ->where('account_type', 'offshore_bond')
                ->get();
            foreach ($offshoreBonds as $account) {
                $fundKey = 'offshore_bond_'.$account->id;
                // Priority: 1) accountValueMap (from availableAccounts), 2) allocationBalances, 3) DB query
                if (isset($accountValueMap[$fundKey])) {
                    $balances[$fundKey] = $accountValueMap[$fundKey];
                } elseif (isset($allocationBalances[$fundKey]) && $allocationBalances[$fundKey] !== null) {
                    $balances[$fundKey] = $allocationBalances[$fundKey];
                } else {
                    $currentValue = (float) ($account->current_value ?? 0);
                    $accountUser = User::find($account->user_id);
                    $balances[$fundKey] = $yearsToRetirement > 0 && $accountUser
                        ? $this->investmentProjectionService->getAccountProjectedValue80($account, $accountUser, $yearsToRetirement)
                        : $currentValue;
                }
            }
        }

        // GIAs - only those in allocations
        if (! empty($giaIds)) {
            $giaAccounts = InvestmentAccount::whereIn('id', $giaIds)
                ->whereIn('account_type', ['gia', 'general'])
                ->get();
            foreach ($giaAccounts as $account) {
                $fundKey = 'gia_'.$account->id;
                // Priority: 1) accountValueMap (from availableAccounts), 2) allocationBalances, 3) DB query
                if (isset($accountValueMap[$fundKey])) {
                    $balances[$fundKey] = $accountValueMap[$fundKey];
                } elseif (isset($allocationBalances[$fundKey]) && $allocationBalances[$fundKey] !== null) {
                    $balances[$fundKey] = $allocationBalances[$fundKey];
                } else {
                    $currentValue = (float) ($account->current_value ?? 0);
                    $accountUser = User::find($account->user_id);
                    $balances[$fundKey] = $yearsToRetirement > 0 && $accountUser
                        ? $this->investmentProjectionService->getAccountProjectedValue80($account, $accountUser, $yearsToRetirement)
                        : $currentValue;
                }
            }
        }

        // Non-ISA Savings - only those in allocations
        if (! empty($savingsIds)) {
            $savingsAccounts = SavingsAccount::whereIn('id', $savingsIds)
                ->where(function ($query) {
                    $query->where('is_isa', false)->orWhereNull('is_isa');
                })
                ->get();
            foreach ($savingsAccounts as $account) {
                $fundKey = 'savings_'.$account->id;
                // Priority: 1) accountValueMap (from availableAccounts), 2) allocationBalances, 3) DB query
                if (isset($accountValueMap[$fundKey])) {
                    $balances[$fundKey] = $accountValueMap[$fundKey];
                } elseif (isset($allocationBalances[$fundKey]) && $allocationBalances[$fundKey] !== null) {
                    $balances[$fundKey] = $allocationBalances[$fundKey];
                } else {
                    $currentValue = (float) ($account->current_balance ?? 0);
                    $balances[$fundKey] = $currentValue * pow(1 + $cashGrowthRate, $yearsToRetirement);
                }
            }
        }

        return $balances;
    }

    /**
     * Initialize fund balances for projection.
     *
     * Uses projected pension pot value (80% Monte Carlo) passed directly.
     * All other assets are projected to retirement age using compound growth.
     *
     * @deprecated Use initializeFundBalancesWithPclsSplit() instead
     */
    private function initializeFundBalances(int $userId, array $incomeAllocations, float $projectedPensionPot = 0, int $yearsToRetirement = 0): array
    {
        $balances = [];

        // Build a set of account IDs that are actually in the income allocations
        $allocatedAccounts = [];
        $hasPensionPotAllocation = false;

        foreach ($incomeAllocations as $allocation) {
            $sourceType = $allocation['source_type'] ?? '';
            $sourceId = $allocation['source_id'] ?? 0;

            if (in_array($sourceType, ['pension_pot_pcls', 'pension_pot_drawdown'])) {
                $hasPensionPotAllocation = true;
            } elseif ($sourceType === 'isa' && $sourceId) {
                $allocatedAccounts['isa'][] = $sourceId;
            } elseif ($sourceType === 'onshore_bond' && $sourceId) {
                $allocatedAccounts['onshore_bond'][] = $sourceId;
            } elseif ($sourceType === 'offshore_bond' && $sourceId) {
                $allocatedAccounts['offshore_bond'][] = $sourceId;
            } elseif ($sourceType === 'gia' && $sourceId) {
                $allocatedAccounts['gia'][] = $sourceId;
            } elseif ($sourceType === 'savings' && $sourceId) {
                $allocatedAccounts['savings'][] = $sourceId;
            }
        }

        // Cash growth rate (lower for savings)
        $cashGrowthRate = 0.02;

        // Pension Pot - only include if there's an allocation for it
        if ($hasPensionPotAllocation && $projectedPensionPot > 0) {
            $balances['pension_pot'] = $projectedPensionPot;
        }

        // Only load accounts that are in the allocations
        $isaIds = $allocatedAccounts['isa'] ?? [];
        $onshoreBondIds = $allocatedAccounts['onshore_bond'] ?? [];
        $offshoreBondIds = $allocatedAccounts['offshore_bond'] ?? [];
        $giaIds = $allocatedAccounts['gia'] ?? [];
        $savingsIds = $allocatedAccounts['savings'] ?? [];

        // ISAs (Savings) - only those in allocations
        if (! empty($isaIds)) {
            $isaAccounts = SavingsAccount::whereIn('id', $isaIds)->where('is_isa', true)->get();
            foreach ($isaAccounts as $account) {
                $currentValue = (float) ($account->current_balance ?? 0);
                $projectedValue = $currentValue * pow(1 + $cashGrowthRate, $yearsToRetirement);
                $balances['isa_savings_'.$account->id] = $projectedValue;
            }

            // ISAs (Investment) - only those in allocations
            $investmentIsas = InvestmentAccount::whereIn('id', $isaIds)
                ->whereIn('account_type', ['isa', 'stocks_shares_isa', 'lifetime_isa'])
                ->get();
            foreach ($investmentIsas as $account) {
                $currentValue = (float) ($account->current_value ?? 0);
                $accountUser = User::find($account->user_id);
                $projectedValue = $yearsToRetirement > 0 && $accountUser
                    ? $this->investmentProjectionService->getAccountProjectedValue80($account, $accountUser, $yearsToRetirement)
                    : $currentValue;
                $balances['isa_investment_'.$account->id] = $projectedValue;
            }
        }

        // Onshore Bonds - only those in allocations
        if (! empty($onshoreBondIds)) {
            $onshoreBonds = InvestmentAccount::whereIn('id', $onshoreBondIds)
                ->where('account_type', 'onshore_bond')
                ->get();
            foreach ($onshoreBonds as $account) {
                $currentValue = (float) ($account->current_value ?? 0);
                $accountUser = User::find($account->user_id);
                $projectedValue = $yearsToRetirement > 0 && $accountUser
                    ? $this->investmentProjectionService->getAccountProjectedValue80($account, $accountUser, $yearsToRetirement)
                    : $currentValue;
                $balances['onshore_bond_'.$account->id] = $projectedValue;
            }
        }

        // Offshore Bonds - only those in allocations
        if (! empty($offshoreBondIds)) {
            $offshoreBonds = InvestmentAccount::whereIn('id', $offshoreBondIds)
                ->where('account_type', 'offshore_bond')
                ->get();
            foreach ($offshoreBonds as $account) {
                $currentValue = (float) ($account->current_value ?? 0);
                $accountUser = User::find($account->user_id);
                $projectedValue = $yearsToRetirement > 0 && $accountUser
                    ? $this->investmentProjectionService->getAccountProjectedValue80($account, $accountUser, $yearsToRetirement)
                    : $currentValue;
                $balances['offshore_bond_'.$account->id] = $projectedValue;
            }
        }

        // GIAs - only those in allocations
        if (! empty($giaIds)) {
            $giaAccounts = InvestmentAccount::whereIn('id', $giaIds)
                ->whereIn('account_type', ['gia', 'general'])
                ->get();
            foreach ($giaAccounts as $account) {
                $currentValue = (float) ($account->current_value ?? 0);
                $accountUser = User::find($account->user_id);
                $projectedValue = $yearsToRetirement > 0 && $accountUser
                    ? $this->investmentProjectionService->getAccountProjectedValue80($account, $accountUser, $yearsToRetirement)
                    : $currentValue;
                $balances['gia_'.$account->id] = $projectedValue;
            }
        }

        // Non-ISA Savings - only those in allocations
        if (! empty($savingsIds)) {
            $savingsAccounts = SavingsAccount::whereIn('id', $savingsIds)
                ->where(function ($query) {
                    $query->where('is_isa', false)->orWhereNull('is_isa');
                })
                ->get();
            foreach ($savingsAccounts as $account) {
                $currentValue = (float) ($account->current_balance ?? 0);
                $projectedValue = $currentValue * pow(1 + $cashGrowthRate, $yearsToRetirement);
                $balances['savings_'.$account->id] = $projectedValue;
            }
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
            $fundKey = null;

            if (in_array($sourceType, ['pension_pot_pcls', 'pension_pot_drawdown'])) {
                $fundKey = 'pension_pot';
            } elseif ($sourceType === 'isa' && $sourceId) {
                // Check if it's a savings ISA or investment ISA
                $isSavingsIsa = SavingsAccount::where('id', $sourceId)->where('is_isa', true)->exists();
                $fundKey = $isSavingsIsa ? 'isa_savings_'.$sourceId : 'isa_investment_'.$sourceId;
            } elseif ($sourceType === 'onshore_bond' && $sourceId) {
                $fundKey = 'onshore_bond_'.$sourceId;
            } elseif ($sourceType === 'offshore_bond' && $sourceId) {
                $fundKey = 'offshore_bond_'.$sourceId;
            } elseif ($sourceType === 'gia' && $sourceId) {
                $fundKey = 'gia_'.$sourceId;
            } elseif ($sourceType === 'savings' && $sourceId) {
                $fundKey = 'savings_'.$sourceId;
            }

            if ($fundKey) {
                $withdrawals[$fundKey] = ($withdrawals[$fundKey] ?? 0) + $amount;
            }
        }

        return $withdrawals;
    }

    /**
     * Get default growth rate from TaxConfigService (safe withdrawal rate).
     */
    private function getDefaultGrowthRate(): float
    {
        return (float) $this->taxConfig->get('retirement.withdrawal_rates.safe', 0.04);
    }

    /**
     * Get growth rate for a fund type.
     */
    private function getGrowthRateForFund(string $fundKey): float
    {
        // PCLS is TAX-FREE CASH - NO GROWTH (it's withdrawn and held as cash)
        if ($fundKey === 'pension_pot_pcls' || str_contains($fundKey, '_pcls')) {
            return 0.0;
        }

        // Cash ISA has no growth
        if (str_contains($fundKey, 'isa_cash') || str_contains($fundKey, 'isa_savings')) {
            return 0.0;
        }

        // Investment assets grow at 4%
        if ($fundKey === 'pension_pot' ||
            $fundKey === 'pension_pot_drawdown' ||
            str_contains($fundKey, 'isa_investment') ||
            str_contains($fundKey, 'onshore_bond') ||
            str_contains($fundKey, 'offshore_bond') ||
            str_contains($fundKey, 'gia')) {
            return $this->getDefaultGrowthRate();
        }

        // Savings grow at 2%
        return 0.02;
    }
}
