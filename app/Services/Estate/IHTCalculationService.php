<?php

declare(strict_types=1);

namespace App\Services\Estate;

use App\Models\DCPension;
use App\Models\Estate\Gift;
use App\Models\Estate\IHTCalculation;
use App\Models\Estate\IHTProfile;
use App\Models\Investment\InvestmentAccount;
use App\Models\Property;
use App\Models\User;
use App\Services\Goals\LifeEventService;
use App\Services\Investment\InvestmentProjectionService;
use App\Services\Settings\AssumptionsService;
use App\Services\TaxConfigService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * IHT Calculation Service
 *
 * Handles all IHT calculations with caching and clear explanatory messages.
 * Uses sophisticated asset-specific projection methods:
 * - Cash: Income/expense surplus model
 * - Investments: Monte Carlo (80% confidence) or custom rate
 * - Properties: Configurable growth rate (default 3%)
 * - Liabilities: Amortisation to end date (default retirement age)
 */
class IHTCalculationService
{
    private const DEFAULT_RETIREMENT_AGE = 68;

    private const DEFAULT_STATE_PENSION_AGE = 67;

    private const DEFAULT_PROPERTY_GROWTH_RATE = 3.0;

    /** Fallback expenditure ratio when no expenditure profile exists (assume 70% spent, 30% saved) */
    private const EXPENDITURE_FALLBACK_RATIO = 0.70;

    /** Retirement phase expenditure fallback ratio (typically lower than pre-retirement) */
    private const RETIREMENT_EXPENDITURE_FALLBACK_RATIO = 0.50;

    public function __construct(
        private readonly EstateAssetAggregatorService $aggregator,
        private readonly TaxConfigService $taxConfig,
        private readonly AssumptionsService $assumptionsService,
        private readonly InvestmentProjectionService $investmentProjectionService,
        private readonly FutureValueCalculator $futureValueCalculator,
        private readonly LifeEventService $lifeEventService
    ) {}

    /**
     * Calculate IHT liability with caching
     *
     * @param  User  $user  The primary user
     * @param  User|null  $spouse  The spouse (if married and linked)
     * @param  bool  $dataSharingEnabled  Whether spouse data sharing is enabled
     * @return array IHT calculation results with all breakdown values
     */
    public function calculate(User $user, ?User $spouse = null, bool $dataSharingEnabled = false): array
    {
        // Eager load relationships to prevent N+1 queries
        $user->loadMissing(['investmentAccounts', 'mortgages', 'liabilities', 'savingsAccounts', 'properties']);
        if ($spouse) {
            $spouse->loadMissing(['investmentAccounts', 'mortgages', 'liabilities', 'savingsAccounts', 'properties']);
        }

        // 1. Check cache first
        $cached = $this->getCachedCalculation($user, $spouse, $dataSharingEnabled);
        if ($cached) {
            return $cached;
        }

        // 2. Get tax config
        $ihtConfig = $this->taxConfig->getInheritanceTax();
        $isMarried = in_array($user->marital_status, ['married']) && $spouse !== null;
        $isWidowed = $user->marital_status === 'widowed';

        // Get IHT profile for transferred allowances (widows/widowers)
        $ihtProfile = IHTProfile::where('user_id', $user->id)->first();

        // 3. Fetch and sum assets (exclude IHT-exempt assets like pensions)
        $userAssets = $this->aggregator->gatherUserAssets($user);
        $spouseAssets = ($isMarried && $dataSharingEnabled)
            ? $this->aggregator->gatherUserAssets($spouse)
            : collect();

        // Filter out IHT-exempt assets (DC pensions, etc.)
        $userTaxableAssets = $userAssets->reject(fn ($asset) => $asset->is_iht_exempt ?? false);
        $spouseTaxableAssets = $spouseAssets->reject(fn ($asset) => $asset->is_iht_exempt ?? false);

        $userGrossAssets = $userTaxableAssets->sum('current_value');
        $spouseGrossAssets = $spouseTaxableAssets->sum('current_value');
        $totalGrossAssets = $userGrossAssets + $spouseGrossAssets;

        // 4. Fetch and sum liabilities
        $userLiabilities = $this->aggregator->calculateUserLiabilities($user);
        $spouseLiabilities = ($isMarried && $dataSharingEnabled)
            ? $this->aggregator->calculateUserLiabilities($spouse)
            : 0;
        $totalLiabilities = $userLiabilities + $spouseLiabilities;

        // 5. Calculate net estate
        $userNetEstate = $userGrossAssets - $userLiabilities;
        $spouseNetEstate = $spouseGrossAssets - $spouseLiabilities;
        $totalNetEstate = $totalGrossAssets - $totalLiabilities;

        // 6. Calculate NRB with message (includes transferred NRB for widows)
        $nrbSingle = $ihtConfig['nil_rate_band']; // £325,000
        $nrbTransferred = (float) ($ihtProfile?->nrb_transferred_from_spouse ?? 0);

        if ($isMarried) {
            $nrbAvailable = $nrbSingle * 2;
            $nrbMessage = 'Combined Nil Rate Band of £'.number_format($nrbAvailable).' available (£'.number_format($nrbSingle).' each). Transfers between spouses are exempt from IHT on first death.';
        } elseif ($isWidowed && $nrbTransferred > 0) {
            $nrbAvailable = $nrbSingle + $nrbTransferred;
            $nrbMessage = 'Combined Nil Rate Band of £'.number_format($nrbAvailable).' available (own £'.number_format($nrbSingle).' + £'.number_format($nrbTransferred).' transferred from late spouse\'s estate).';
        } else {
            $nrbAvailable = $nrbSingle;
            $nrbMessage = 'Nil Rate Band of £'.number_format($nrbAvailable).' available for single person.';
        }

        // 6b. Deduct primary user's PETs and CLTs from their OWN NRB only
        // Spouse NRB is handled separately by SpouseNRBTrackerService
        $nrbDeduction = $this->calculateNRBDeductionForGifts($user, $nrbSingle);
        $nrbAvailable = max(0, $nrbAvailable - $nrbDeduction['total_nrb_used']);

        if ($nrbDeduction['total_nrb_used'] > 0) {
            $nrbMessage .= ' Reduced by £'.number_format($nrbDeduction['total_nrb_used'])
                .' due to gifts made within the last 7 years'
                .($nrbDeduction['clts_7_to_14_years'] > 0
                    ? ' (including the 14-year rule for historical Chargeable Lifetime Transfers)'
                    : '')
                .'.';
        }

        // 7. Calculate RNRB with message (ALWAYS calculate, even if £0)
        $rnrbData = $this->calculateRNRB($totalNetEstate, $user, $spouse, $ihtConfig, $isMarried, $isWidowed, $ihtProfile);

        // 8. Calculate taxable estate and IHT (CURRENT values)
        $totalAllowances = $nrbAvailable + $rnrbData['rnrb_available'];
        $taxableEstate = max(0, $totalNetEstate - $totalAllowances);

        // Determine IHT rate - check for charitable reduced rate (36% if 10%+ to charity)
        $ihtRateData = $this->determineIHTRate($user, $totalNetEstate, $nrbAvailable, $ihtConfig);
        $ihtRate = $ihtRateData['rate'];
        $ihtLiability = $taxableEstate * $ihtRate;
        $effectiveRate = $totalNetEstate > 0 ? ($ihtLiability / $totalNetEstate * 100) : 0;

        // 9. Calculate PROJECTED values at death using asset-specific methods
        $projectedData = $this->calculateProjectedValues(
            $user,
            $spouse,
            $nrbAvailable,
            $rnrbData,
            $isMarried,
            $ihtRate,
            $dataSharingEnabled
        );

        // 10. Build result array with CURRENT and PROJECTED values
        $result = [
            // Current values
            'user_gross_assets' => round($userGrossAssets, 2),
            'spouse_gross_assets' => round($spouseGrossAssets, 2),
            'total_gross_assets' => round($totalGrossAssets, 2),

            'user_total_liabilities' => round($userLiabilities, 2),
            'spouse_total_liabilities' => round($spouseLiabilities, 2),
            'total_liabilities' => round($totalLiabilities, 2),

            'user_net_estate' => round($userNetEstate, 2),
            'spouse_net_estate' => round($spouseNetEstate, 2),
            'total_net_estate' => round($totalNetEstate, 2),

            'nrb_available' => round($nrbAvailable, 2),
            'nrb_individual' => round($nrbSingle, 2),
            'nrb_transferred' => round($nrbTransferred, 2),
            'nrb_message' => $nrbMessage,

            'rnrb_available' => round($rnrbData['rnrb_available'], 2),
            'rnrb_individual' => round($rnrbData['rnrb_individual'] ?? 0, 2),
            'rnrb_transferred' => round($rnrbData['rnrb_transferred'] ?? 0, 2),
            'rnrb_status' => $rnrbData['rnrb_status'],
            'rnrb_message' => $rnrbData['rnrb_message'],

            'total_allowances' => round($totalAllowances, 2),
            'taxable_estate' => round($taxableEstate, 2),
            'iht_rate' => $ihtRate,
            'iht_rate_percent' => round($ihtRate * 100, 0),
            'iht_rate_type' => $ihtRateData['type'],
            'iht_rate_message' => $ihtRateData['message'],
            'charitable_giving_percent' => $ihtRateData['charitable_percent'],
            'charitable_baseline' => $ihtRateData['baseline'],
            'charitable_threshold' => $ihtRateData['threshold'],
            'iht_liability' => round($ihtLiability, 2),
            'effective_rate' => round($effectiveRate, 2),

            // Projected values at death (asset-specific)
            'projected_cash' => $projectedData['projected_cash'],
            'projected_investments' => $projectedData['projected_investments'],
            'projected_properties' => $projectedData['projected_properties'],
            'projected_gross_assets' => $projectedData['projected_gross_assets'],
            'projected_liabilities' => $projectedData['projected_liabilities'],
            'projected_net_estate' => $projectedData['projected_net_estate'],
            'projected_taxable_estate' => $projectedData['projected_taxable_estate'],
            'projected_iht_liability' => $projectedData['projected_iht_liability'],
            'years_to_death' => $projectedData['years_to_death'],
            'retirement_age' => $projectedData['retirement_age'],
            'estimated_age_at_death' => $projectedData['estimated_age_at_death'],

            'is_married' => $isMarried,
            'is_widowed' => $isWidowed,
            'data_sharing_enabled' => $dataSharingEnabled,

            // NRB gift deduction breakdown
            'nrb_deduction' => $nrbDeduction,
        ];

        // 9b. Calculate 2027 pension Inheritance Tax dual-scenario projection
        $pensionAmendment = $this->calculatePensionAmendmentScenario($user, $spouse, $dataSharingEnabled, $result);
        $result['pension_amendment'] = $pensionAmendment;

        // 10. Save to database
        $this->saveCalculation($user, $result, $userAssets, $spouseAssets, $userLiabilities, $spouseLiabilities);

        return $result;
    }

    /**
     * Calculate projected values at death using asset-specific methods
     *
     * For married couples, projects to SECOND DEATH (whoever lives longer)
     * to accurately calculate combined IHT liability.
     *
     * Asset-specific projection methods:
     * - Cash: Income/expense surplus model (switches at retirement)
     * - Investments: Monte Carlo (80% confidence) or custom rate
     * - Properties: Configurable growth rate (default 3%)
     * - Liabilities: Amortisation to end date (default retirement age)
     */
    private function calculateProjectedValues(
        User $user,
        ?User $spouse,
        float $nrbAvailable,
        array $rnrbData,
        bool $isMarried,
        float $ihtRate,
        bool $dataSharingEnabled = false
    ): array {
        // Get current age and key milestone ages
        $currentAge = $user->date_of_birth ? Carbon::parse($user->date_of_birth)->age : 50;
        $retirementAge = $this->getRetirementAge($user);

        // For married couples, calculate BOTH life expectancies and use the longer one (second death)
        if ($isMarried && $spouse && $spouse->date_of_birth && $spouse->gender) {
            $userYearsUntilDeath = $this->calculateLifeExpectancy($user);
            $spouseYearsUntilDeath = $this->calculateLifeExpectancy($spouse);

            // Use the LONGER life expectancy (second death scenario)
            $yearsUntilDeath = max($userYearsUntilDeath, $spouseYearsUntilDeath);

            // Determine who dies second and use their estimated age
            if ($spouseYearsUntilDeath > $userYearsUntilDeath) {
                $estimatedAgeAtDeath = Carbon::parse($spouse->date_of_birth)->age + $spouseYearsUntilDeath;
            } else {
                $estimatedAgeAtDeath = $currentAge + $userYearsUntilDeath;
            }
        } elseif (! $user->date_of_birth || ! $user->gender) {
            // No DOB/gender - assume 25 years
            $yearsUntilDeath = 25;
            $estimatedAgeAtDeath = $user->date_of_birth
                ? Carbon::parse($user->date_of_birth)->age + $yearsUntilDeath
                : 80;
        } else {
            // Single person - use their own life expectancy
            $yearsUntilDeath = $this->calculateLifeExpectancy($user);
            $estimatedAgeAtDeath = $currentAge + $yearsUntilDeath;
        }

        // Get estate planning assumptions
        $assumptions = $this->assumptionsService->getEstateAssumptions($user);
        $inflationRate = ($assumptions['inflation_rate'] ?? 2.0) / 100;

        // Project investments using Monte Carlo p20 directly at death age
        // No rate extraction or recompounding — use the simulation result as-is
        $projectedInvestments = $this->projectInvestmentsMonteCarlo(
            $user,
            $spouse,
            $yearsUntilDeath,
            $dataSharingEnabled
        );

        // Project cash: year-by-year income - expenses, both inflation-adjusted, with life events
        $projectedCash = $this->projectCashWithInflation(
            $user,
            $spouse,
            $currentAge,
            $retirementAge,
            $estimatedAgeAtDeath,
            $inflationRate,
            $dataSharingEnabled
        );

        $projectedProperties = $this->projectProperties(
            $user,
            $spouse,
            $yearsUntilDeath,
            $assumptions,
            $dataSharingEnabled
        );

        $projectedLiabilities = $this->projectLiabilities(
            $user,
            $spouse,
            $currentAge,
            $retirementAge,
            $estimatedAgeAtDeath,
            $dataSharingEnabled
        );

        // Get current chattel and business values (these don't appreciate - stay at current value)
        $userAssets = $this->aggregator->gatherUserAssets($user);
        $projectedChattels = $userAssets->where('asset_type', 'chattel')
            ->reject(fn ($a) => $a->is_iht_exempt)
            ->sum('current_value');
        $projectedBusiness = $userAssets->where('asset_type', 'business')
            ->reject(fn ($a) => $a->is_iht_exempt)
            ->sum('current_value');

        if ($dataSharingEnabled && $spouse) {
            $spouseAssets = $this->aggregator->gatherUserAssets($spouse);
            $projectedChattels += $spouseAssets->where('asset_type', 'chattel')
                ->reject(fn ($a) => $a->is_iht_exempt)
                ->sum('current_value');
            $projectedBusiness += $spouseAssets->where('asset_type', 'business')
                ->reject(fn ($a) => $a->is_iht_exempt)
                ->sum('current_value');
        }

        // Calculate totals (include chattels and business at current value)
        $projectedGrossAssets = $projectedCash + $projectedInvestments + $projectedProperties + $projectedChattels + $projectedBusiness;
        $projectedNetEstate = $projectedGrossAssets - $projectedLiabilities;

        // Calculate projected IHT using same allowances
        $totalAllowances = $nrbAvailable + $rnrbData['rnrb_available'];
        $projectedTaxableEstate = max(0, $projectedNetEstate - $totalAllowances);
        $projectedIHTLiability = $projectedTaxableEstate * $ihtRate;

        return [
            'projected_cash' => round($projectedCash, 2),
            'projected_investments' => round($projectedInvestments, 2),
            'projected_properties' => round($projectedProperties, 2),
            'projected_gross_assets' => round($projectedGrossAssets, 2),
            'projected_liabilities' => round($projectedLiabilities, 2),
            'projected_net_estate' => round($projectedNetEstate, 2),
            'projected_taxable_estate' => round($projectedTaxableEstate, 2),
            'projected_iht_liability' => round($projectedIHTLiability, 2),
            'years_to_death' => $yearsUntilDeath,
            'retirement_age' => $retirementAge,
            'estimated_age_at_death' => $estimatedAgeAtDeath,
        ];
    }

    /**
     * Get retirement age from user profile or defaults
     */
    private function getRetirementAge(User $user): int
    {
        // Priority order for retirement age
        if ($user->retirementProfile?->target_retirement_age) {
            return $user->retirementProfile->target_retirement_age;
        }

        if ($user->target_retirement_age) {
            return $user->target_retirement_age;
        }

        // Check DC pensions for retirement age
        $pensionRetirementAge = $user->dcPensions()
            ->whereNotNull('retirement_age')
            ->value('retirement_age');

        if ($pensionRetirementAge) {
            return (int) $pensionRetirementAge;
        }

        return self::DEFAULT_RETIREMENT_AGE;
    }

    /**
     * Project cash accounts using income/expense surplus model
     *
     * Pre-retirement: Uses current income and expenses
     * Post-retirement: Uses retirement income (including state pension) and retirement expenses
     */
    private function projectCashAccounts(
        User $user,
        ?User $spouse,
        int $currentAge,
        int $retirementAge,
        int $deathAge,
        bool $dataSharingEnabled
    ): float {
        // Get current cash value from savings accounts
        $currentCash = $this->getCurrentCashValue($user);
        if ($dataSharingEnabled && $spouse) {
            $currentCash += $this->getCurrentCashValue($spouse);
        }

        $projectedCash = $currentCash;

        // Pre-Retirement Phase: currentAge → retirementAge
        if ($currentAge < $retirementAge) {
            $preRetirementYears = min($retirementAge, $deathAge) - $currentAge;
            $annualIncome = $this->getTotalAnnualIncome($user, $spouse, $dataSharingEnabled);
            $annualExpenses = $this->getCurrentAnnualExpenses($user, $spouse, $dataSharingEnabled);
            $annualSurplus = $annualIncome - $annualExpenses;

            $projectedCash += $annualSurplus * $preRetirementYears;
        }

        // Post-Retirement Phase: retirementAge → deathAge
        if ($deathAge > $retirementAge) {
            $startAge = max($currentAge, $retirementAge);
            for ($age = $startAge; $age < $deathAge; $age++) {
                $retirementIncome = $this->getRetirementIncome($user, $spouse, $age, $dataSharingEnabled);
                $retirementExpenses = $this->getRetirementExpenses($user, $spouse, $dataSharingEnabled);
                $surplus = $retirementIncome - $retirementExpenses;
                $projectedCash += $surplus;
            }
        }

        // Cash can go negative (implies drawing from investments) but we return 0 minimum
        // The negative is implicitly handled by reduced investment value
        return max(0, $projectedCash);
    }

    /**
     * Project cash balance year-by-year with inflation-adjusted income/expenses and life events.
     *
     * For each year from current age to death:
     * - Pre-retirement: employment income - current expenses (both inflation-adjusted)
     * - Post-retirement: retirement income - retirement expenses (both inflation-adjusted)
     * - Life events injected at their specific ages
     * - Cash can go negative to give an honest estate picture
     */
    private function projectCashWithInflation(
        User $user,
        ?User $spouse,
        int $currentAge,
        int $retirementAge,
        int $deathAge,
        float $inflationRate,
        bool $dataSharingEnabled
    ): float {
        // Starting cash balance from savings accounts
        $cashBalance = $this->getCurrentCashValue($user);
        if ($dataSharingEnabled && $spouse) {
            $cashBalance += $this->getCurrentCashValue($spouse);
        }

        // Base income and expenses (year 0 values, before inflation)
        $basePreRetirementIncome = $this->getTotalAnnualIncome($user, $spouse, $dataSharingEnabled);
        $basePreRetirementExpenses = $this->getCurrentAnnualExpenses($user, $spouse, $dataSharingEnabled);
        $baseRetirementExpenses = $this->getRetirementExpenses($user, $spouse, $dataSharingEnabled);

        // Life event impacts keyed by age
        $lifeEventImpacts = $this->getLifeEventImpactsByAge($user, $spouse, $dataSharingEnabled);

        // Year-by-year projection
        for ($age = $currentAge; $age < $deathAge; $age++) {
            $yearsFromNow = $age - $currentAge;
            $inflationMultiplier = pow(1 + $inflationRate, $yearsFromNow);

            if ($age < $retirementAge) {
                $income = $basePreRetirementIncome * $inflationMultiplier;
                $expenses = $basePreRetirementExpenses * $inflationMultiplier;
            } else {
                $income = $this->getRetirementIncome($user, $spouse, $age, $dataSharingEnabled) * $inflationMultiplier;
                $expenses = $baseRetirementExpenses * $inflationMultiplier;
            }

            $surplus = $income - $expenses;

            // Inject life events at their specific ages
            $surplus += $lifeEventImpacts[$age] ?? 0;

            $cashBalance += $surplus;
        }

        // Cash can go negative — gives honest estate picture so line items sum to total
        return $cashBalance;
    }

    /**
     * Integrated projection: Cash deficits drawn from investments year-by-year
     *
     * For each year:
     * 1. Calculate cash surplus (income - expenses)
     * 2. If negative, deduct deficit from investments BEFORE applying growth
     * 3. Apply investment growth rate to reduced balance
     * 4. Repeat for each year until death
     *
     * This creates a realistic model where retirement deficits deplete investment
     * accounts over time rather than accumulating impossible negative cash.
     */
    private function projectCashAndInvestmentsIntegrated(
        User $user,
        ?User $spouse,
        int $currentAge,
        int $retirementAge,
        int $deathAge,
        array $assumptions,
        bool $dataSharingEnabled
    ): array {
        // Get initial cash value
        $cashBalance = $this->getCurrentCashValue($user);
        if ($dataSharingEnabled && $spouse) {
            $cashBalance += $this->getCurrentCashValue($spouse);
        }

        // Get investment accounts as array for year-by-year projection
        $investments = $this->getInvestmentAccountsArray($user, $spouse, $dataSharingEnabled);
        $totalInvestments = array_sum(array_column($investments, 'balance'));

        // Get investment growth rate (Monte Carlo annualised rate or custom)
        $method = $assumptions['investment_growth_method'] ?? 'monte_carlo';
        if ($method === 'monte_carlo') {
            // Use annualised Monte Carlo growth rate (derived from p20 percentile)
            $investmentGrowthRate = $this->getMonteCarloAnnualRate($user, $spouse, $dataSharingEnabled);
        } else {
            // Custom rate specified by user
            $investmentGrowthRate = ($assumptions['custom_investment_rate'] ?? 5.0) / 100;
        }

        // Get life event impacts keyed by age (certainty-weighted)
        $lifeEventImpacts = $this->getLifeEventImpactsByAge($user, $spouse, $dataSharingEnabled);

        // Year-by-year projection
        for ($age = $currentAge; $age < $deathAge; $age++) {
            // Step 1: Calculate cash surplus for this year
            if ($age < $retirementAge) {
                // Pre-retirement: employment income - current expenses
                $income = $this->getTotalAnnualIncome($user, $spouse, $dataSharingEnabled);
                $expenses = $this->getCurrentAnnualExpenses($user, $spouse, $dataSharingEnabled);
            } else {
                // Post-retirement: retirement income - retirement expenses
                $income = $this->getRetirementIncome($user, $spouse, $age, $dataSharingEnabled);
                $expenses = $this->getRetirementExpenses($user, $spouse, $dataSharingEnabled);
            }
            $surplus = $income - $expenses;

            // Step 1b: Add life event impacts for this age (income positive, expense negative)
            $surplus += $lifeEventImpacts[$age] ?? 0;

            // Step 2: Update cash balance
            $cashBalance += $surplus;

            // Step 3: If cash goes negative, draw from investments BEFORE growth
            if ($cashBalance < 0) {
                $deficit = abs($cashBalance);
                $cashBalance = 0; // Reset cash to zero

                // Distribute deficit equally across all investment accounts
                $accountCount = count($investments);
                if ($accountCount > 0 && $totalInvestments > 0) {
                    $deficitPerAccount = $deficit / $accountCount;

                    foreach ($investments as &$account) {
                        $account['balance'] = max(0, $account['balance'] - $deficitPerAccount);
                    }
                    unset($account);

                    // Recalculate total after drawdown
                    $totalInvestments = array_sum(array_column($investments, 'balance'));
                }
            }

            // Step 4: Apply investment growth AFTER drawdown
            foreach ($investments as &$account) {
                $account['balance'] *= (1 + $investmentGrowthRate);
            }
            unset($account);
            $totalInvestments = array_sum(array_column($investments, 'balance'));
        }

        return [
            'projected_cash' => round($cashBalance, 2),
            'projected_investments' => round($totalInvestments, 2),
            'investment_accounts' => $investments, // For individual account projections
        ];
    }

    /**
     * Get all investment accounts as array for integrated projection
     *
     * Returns an array of accounts with id, name, owner, and balance
     * for year-by-year drawdown calculations.
     */
    private function getInvestmentAccountsArray(User $user, ?User $spouse, bool $dataSharingEnabled): array
    {
        $accounts = [];

        // User's investment accounts (exclude IHT-exempt accounts like pensions)
        foreach ($user->investmentAccounts as $account) {
            // Skip accounts flagged as IHT exempt (pensions are already excluded by account type)
            $accounts[] = [
                'id' => $account->id,
                'name' => $account->account_name ?? 'Investment Account',
                'owner' => 'user',
                'balance' => (float) ($account->current_value ?? 0),
            ];
        }

        // Spouse's investment accounts (if data sharing enabled)
        if ($dataSharingEnabled && $spouse) {
            foreach ($spouse->investmentAccounts as $account) {
                $accounts[] = [
                    'id' => $account->id,
                    'name' => $account->account_name ?? 'Investment Account',
                    'owner' => 'spouse',
                    'balance' => (float) ($account->current_value ?? 0),
                ];
            }
        }

        return $accounts;
    }

    /**
     * Get annualised Monte Carlo growth rate for investments
     *
     * Calculates the implied annual growth rate from the Monte Carlo p20 projection.
     * This rate is then applied year-by-year in the integrated projection.
     */
    private function getMonteCarloAnnualRate(User $user, ?User $spouse, bool $dataSharingEnabled): float
    {
        $fallbackRate = $this->getFallbackGrowthRate($user);

        // Use a reference projection period to derive annual rate
        $yearsToProject = 10;
        $currentValue = $this->getCurrentInvestmentValue($user, $spouse, $dataSharingEnabled);

        if ($currentValue <= 0) {
            return $fallbackRate;
        }

        // Get the Monte Carlo projected value
        $projectedValue = $this->projectInvestmentsMonteCarlo($user, $spouse, $yearsToProject, $dataSharingEnabled);

        // Calculate implied annual rate: FV = PV * (1 + r)^n → r = (FV/PV)^(1/n) - 1
        if ($projectedValue > 0 && $currentValue > 0) {
            $impliedRate = pow($projectedValue / $currentValue, 1 / $yearsToProject) - 1;

            // Sanity check: rate should be between -10% and +30%
            return max(-0.10, min(0.30, $impliedRate));
        }

        return $fallbackRate;
    }

    /**
     * Get fallback investment growth rate from AssumptionsService.
     * Falls back to 4.7% if no user-specific assumption is configured.
     */
    private function getFallbackGrowthRate(User $user): float
    {
        $assumptions = $this->assumptionsService->getEstateAssumptions($user);

        if (($assumptions['investment_growth_method'] ?? 'monte_carlo') === 'custom'
            && isset($assumptions['custom_investment_rate'])) {
            return (float) $assumptions['custom_investment_rate'] / 100;
        }

        return 0.047;
    }

    /**
     * Get current cash value from all savings/cash accounts
     * Matches EstateAssetAggregatorService which treats ALL savings accounts as 'cash'
     */
    private function getCurrentCashValue(User $user): float
    {
        // All savings accounts are considered cash (matching aggregator logic)
        $cashValue = $user->savingsAccounts()->sum('current_balance');

        return (float) $cashValue;
    }

    /**
     * Get total annual income for user (and spouse if sharing enabled)
     */
    private function getTotalAnnualIncome(User $user, ?User $spouse, bool $dataSharingEnabled): float
    {
        $income = (float) ($user->annual_employment_income ?? 0)
            + (float) ($user->annual_self_employment_income ?? 0)
            + (float) ($user->annual_rental_income ?? 0)
            + (float) ($user->annual_dividend_income ?? 0)
            + (float) ($user->annual_interest_income ?? 0)
            + (float) ($user->annual_other_income ?? 0)
            + (float) ($user->annual_trust_income ?? 0);

        // Include spouse income if data sharing enabled
        if ($dataSharingEnabled && $spouse) {
            $income += (float) ($spouse->annual_employment_income ?? 0)
                + (float) ($spouse->annual_self_employment_income ?? 0)
                + (float) ($spouse->annual_rental_income ?? 0)
                + (float) ($spouse->annual_dividend_income ?? 0)
                + (float) ($spouse->annual_interest_income ?? 0)
                + (float) ($spouse->annual_other_income ?? 0)
                + (float) ($spouse->annual_trust_income ?? 0);
        }

        return $income;
    }

    /**
     * Get current annual expenses from expenditure profile
     * Falls back to 70% of income if no profile exists
     */
    private function getCurrentAnnualExpenses(User $user, ?User $spouse, bool $dataSharingEnabled): float
    {
        $expenses = 0;
        $hasUserProfile = false;
        $hasSpouseProfile = false;

        $profile = $user->expenditureProfile;
        if ($profile && $profile->total_monthly_expenditure) {
            $expenses = (float) $profile->total_monthly_expenditure * 12;
            $hasUserProfile = true;
        }

        // Include spouse expenses if data sharing enabled
        if ($dataSharingEnabled && $spouse) {
            $spouseProfile = $spouse->expenditureProfile;
            if ($spouseProfile && $spouseProfile->total_monthly_expenditure) {
                $expenses += (float) $spouseProfile->total_monthly_expenditure * 12;
                $hasSpouseProfile = true;
            }
        }

        // Fallback: if no expenditure profiles, estimate as 70% of combined income
        // This prevents unrealistic surplus accumulation
        if (! $hasUserProfile && ! $hasSpouseProfile) {
            $totalIncome = $this->getTotalAnnualIncome($user, $spouse, $dataSharingEnabled);
            $expenses = $totalIncome * self::EXPENDITURE_FALLBACK_RATIO; // Assume 70% spent, 30% saved
        } elseif (! $hasUserProfile && $hasSpouseProfile) {
            // User has no profile, estimate their portion
            $userIncome = $this->getUserAnnualIncome($user);
            $expenses += $userIncome * self::EXPENDITURE_FALLBACK_RATIO;
        } elseif ($hasUserProfile && ! $hasSpouseProfile && $dataSharingEnabled && $spouse) {
            // Spouse has no profile, estimate their portion
            $spouseIncome = $this->getUserAnnualIncome($spouse);
            $expenses += $spouseIncome * self::EXPENDITURE_FALLBACK_RATIO;
        }

        return $expenses;
    }

    /**
     * Get annual income for a single user (helper for expense fallback)
     */
    private function getUserAnnualIncome(User $user): float
    {
        return (float) ($user->annual_employment_income ?? 0)
            + (float) ($user->annual_self_employment_income ?? 0)
            + (float) ($user->annual_rental_income ?? 0)
            + (float) ($user->annual_dividend_income ?? 0)
            + (float) ($user->annual_interest_income ?? 0)
            + (float) ($user->annual_other_income ?? 0)
            + (float) ($user->annual_trust_income ?? 0);
    }

    /**
     * Get retirement income at a given age (includes state pension when applicable)
     */
    private function getRetirementIncome(User $user, ?User $spouse, int $age, bool $dataSharingEnabled): float
    {
        $income = 0;

        // Target retirement income from retirement profile
        $income += (float) ($user->retirementProfile?->target_retirement_income ?? 0);

        // Add State Pension if user has reached state pension age
        $statePensionAge = $user->state_pension_age ?? self::DEFAULT_STATE_PENSION_AGE;
        if ($age >= $statePensionAge) {
            $statePension = $user->statePension;
            $income += (float) ($statePension?->estimated_annual_amount ?? 0);
        }

        // Include spouse retirement income and state pension
        if ($dataSharingEnabled && $spouse) {
            $income += (float) ($spouse->retirementProfile?->target_retirement_income ?? 0);

            $spouseStatePensionAge = $spouse->state_pension_age ?? self::DEFAULT_STATE_PENSION_AGE;
            // Calculate spouse's age at the same point in time
            $userCurrentAge = $user->date_of_birth ? Carbon::parse($user->date_of_birth)->age : 50;
            $spouseCurrentAge = $spouse->date_of_birth ? Carbon::parse($spouse->date_of_birth)->age : $userCurrentAge;
            $spouseAgeAtTime = $spouseCurrentAge + ($age - $userCurrentAge);

            if ($spouseAgeAtTime >= $spouseStatePensionAge) {
                $spouseStatePension = $spouse->statePension;
                $income += (float) ($spouseStatePension?->estimated_annual_amount ?? 0);
            }
        }

        return $income;
    }

    /**
     * Get retirement expenses from retirement profile
     * Falls back to target retirement income if no expenses defined
     */
    private function getRetirementExpenses(User $user, ?User $spouse, bool $dataSharingEnabled): float
    {
        $expenses = 0;

        $profile = $user->retirementProfile;
        if ($profile) {
            $profileExpenses = (float) ($profile->essential_expenditure ?? 0)
                + (float) ($profile->lifestyle_expenditure ?? 0);

            if ($profileExpenses > 0) {
                $expenses = $profileExpenses;
            } elseif ($profile->target_retirement_income > 0) {
                // Fallback: assume retirement expenses equal target retirement income
                // (they're targeting to spend what they earn in retirement)
                $expenses = (float) $profile->target_retirement_income;
            }
        }

        // If still no expenses, use 70% of pre-retirement income estimate
        if ($expenses <= 0) {
            $userIncome = $this->getUserAnnualIncome($user);
            $expenses = $userIncome * self::RETIREMENT_EXPENDITURE_FALLBACK_RATIO; // Typically spend less in retirement
        }

        // Include spouse retirement expenses if data sharing enabled
        if ($dataSharingEnabled && $spouse) {
            $spouseProfile = $spouse->retirementProfile;
            $spouseExpenses = 0;

            if ($spouseProfile) {
                $profileExpenses = (float) ($spouseProfile->essential_expenditure ?? 0)
                    + (float) ($spouseProfile->lifestyle_expenditure ?? 0);

                if ($profileExpenses > 0) {
                    $spouseExpenses = $profileExpenses;
                } elseif ($spouseProfile->target_retirement_income > 0) {
                    $spouseExpenses = (float) $spouseProfile->target_retirement_income;
                }
            }

            // Fallback for spouse
            if ($spouseExpenses <= 0) {
                $spouseIncome = $this->getUserAnnualIncome($spouse);
                $spouseExpenses = $spouseIncome * 0.50;
            }

            $expenses += $spouseExpenses;
        }

        return $expenses;
    }

    /**
     * Project investments using Monte Carlo (80% confidence) or custom rate
     */
    private function projectInvestments(
        User $user,
        ?User $spouse,
        int $yearsToProject,
        array $assumptions,
        bool $dataSharingEnabled
    ): float {
        if ($yearsToProject <= 0) {
            return $this->getCurrentInvestmentValue($user, $spouse, $dataSharingEnabled);
        }

        $method = $assumptions['investment_growth_method'] ?? 'monte_carlo';

        if ($method === 'monte_carlo') {
            return $this->projectInvestmentsMonteCarlo($user, $spouse, $yearsToProject, $dataSharingEnabled);
        }

        // Custom rate: simple compound growth
        $customRate = ($assumptions['custom_investment_rate'] ?? 5.0) / 100;
        $currentValue = $this->getCurrentInvestmentValue($user, $spouse, $dataSharingEnabled);

        return $this->futureValueCalculator->calculateFutureValue($currentValue, $customRate, $yearsToProject);
    }

    /**
     * Get current total investment value
     */
    private function getCurrentInvestmentValue(User $user, ?User $spouse, bool $dataSharingEnabled): float
    {
        $value = InvestmentAccount::where('user_id', $user->id)->sum('current_value');

        if ($dataSharingEnabled && $spouse) {
            $value += InvestmentAccount::where('user_id', $spouse->id)->sum('current_value');
        }

        return (float) $value;
    }

    /**
     * Project investments using Monte Carlo simulation (80% confidence / p20)
     */
    private function projectInvestmentsMonteCarlo(
        User $user,
        ?User $spouse,
        int $yearsToProject,
        bool $dataSharingEnabled
    ): float {
        $projectedValue = 0;

        $fallbackRate = $this->getFallbackGrowthRate($user);

        // Get user's investment projections
        try {
            $userProjections = $this->investmentProjectionService->getPortfolioProjections(
                $user,
                [$yearsToProject]
            );

            if (isset($userProjections['portfolio']['projections'][$yearsToProject]['percentiles']['p20'])) {
                $projectedValue += $userProjections['portfolio']['projections'][$yearsToProject]['percentiles']['p20'];
            } else {
                // Fallback: compound at fallback rate instead of zero growth
                $currentValue = (float) InvestmentAccount::where('user_id', $user->id)->sum('current_value');
                $projectedValue += $this->futureValueCalculator->calculateFutureValue($currentValue, $fallbackRate, $yearsToProject);
            }
        } catch (\Exception $e) {
            // Fallback: compound at fallback rate instead of zero growth
            $currentValue = (float) InvestmentAccount::where('user_id', $user->id)->sum('current_value');
            $projectedValue += $this->futureValueCalculator->calculateFutureValue($currentValue, $fallbackRate, $yearsToProject);
        }

        // Include spouse's investments
        if ($dataSharingEnabled && $spouse) {
            try {
                $spouseProjections = $this->investmentProjectionService->getPortfolioProjections(
                    $spouse,
                    [$yearsToProject]
                );

                if (isset($spouseProjections['portfolio']['projections'][$yearsToProject]['percentiles']['p20'])) {
                    $projectedValue += $spouseProjections['portfolio']['projections'][$yearsToProject]['percentiles']['p20'];
                } else {
                    $currentValue = (float) InvestmentAccount::where('user_id', $spouse->id)->sum('current_value');
                    $projectedValue += $this->futureValueCalculator->calculateFutureValue($currentValue, $fallbackRate, $yearsToProject);
                }
            } catch (\Exception $e) {
                $currentValue = (float) InvestmentAccount::where('user_id', $spouse->id)->sum('current_value');
                $projectedValue += $this->futureValueCalculator->calculateFutureValue($currentValue, $fallbackRate, $yearsToProject);
            }
        }

        return $projectedValue;
    }

    /**
     * Project properties using configurable growth rate (default 3%)
     */
    private function projectProperties(
        User $user,
        ?User $spouse,
        int $yearsToProject,
        array $assumptions,
        bool $dataSharingEnabled
    ): float {
        $propertyGrowthRate = ($assumptions['property_growth_rate'] ?? self::DEFAULT_PROPERTY_GROWTH_RATE) / 100;

        $currentPropertyValue = (float) Property::where('user_id', $user->id)->sum('current_value');

        // Include spouse properties if data sharing enabled
        if ($dataSharingEnabled && $spouse) {
            $currentPropertyValue += (float) Property::where('user_id', $spouse->id)->sum('current_value');
        }

        if ($yearsToProject <= 0) {
            return $currentPropertyValue;
        }

        return $this->futureValueCalculator->calculateFutureValue($currentPropertyValue, $propertyGrowthRate, $yearsToProject);
    }

    /**
     * Project liabilities with amortisation to end date
     *
     * If no end date specified, assumes liability cleared at retirement age
     */
    private function projectLiabilities(
        User $user,
        ?User $spouse,
        int $currentAge,
        int $retirementAge,
        int $deathAge,
        bool $dataSharingEnabled
    ): float {
        $projectedLiabilities = 0;
        $currentYear = now()->year;
        $yearsToProject = $deathAge - $currentAge;

        // Project mortgages
        foreach ($user->mortgages as $mortgage) {
            $endDate = $mortgage->end_date;
            $projectedLiabilities += $this->projectSingleLiability(
                (float) ($mortgage->outstanding_balance ?? 0),
                $endDate instanceof \DateTimeInterface ? $endDate->format('Y-m-d') : $endDate,
                $currentAge,
                $retirementAge,
                $yearsToProject,
                $currentYear
            );
        }

        // Project other liabilities
        foreach ($user->liabilities as $liability) {
            $endDate = $liability->maturity_date ?? $this->estimatePayoffDate($liability);
            $projectedLiabilities += $this->projectSingleLiability(
                (float) ($liability->current_balance ?? 0),
                $endDate instanceof \DateTimeInterface ? $endDate->format('Y-m-d') : $endDate,
                $currentAge,
                $retirementAge,
                $yearsToProject,
                $currentYear
            );
        }

        // Include spouse liabilities if data sharing enabled
        if ($dataSharingEnabled && $spouse) {
            foreach ($spouse->mortgages as $mortgage) {
                $endDate = $mortgage->end_date;
                $projectedLiabilities += $this->projectSingleLiability(
                    (float) ($mortgage->outstanding_balance ?? 0),
                    $endDate instanceof \DateTimeInterface ? $endDate->format('Y-m-d') : $endDate,
                    $currentAge,
                    $retirementAge,
                    $yearsToProject,
                    $currentYear
                );
            }

            foreach ($spouse->liabilities as $liability) {
                $endDate = $liability->maturity_date ?? $this->estimatePayoffDate($liability);
                $projectedLiabilities += $this->projectSingleLiability(
                    (float) ($liability->current_balance ?? 0),
                    $endDate instanceof \DateTimeInterface ? $endDate->format('Y-m-d') : $endDate,
                    $currentAge,
                    $retirementAge,
                    $yearsToProject,
                    $currentYear
                );
            }
        }

        return $projectedLiabilities;
    }

    /**
     * Project a single liability using linear amortisation
     */
    private function projectSingleLiability(
        float $currentBalance,
        ?string $endDate,
        int $currentAge,
        int $retirementAge,
        int $yearsToProject,
        int $currentYear
    ): float {
        if ($currentBalance <= 0) {
            return 0;
        }

        // Determine years until liability ends
        if ($endDate) {
            $endYear = Carbon::parse($endDate)->year;
            $yearsUntilEnd = max(0, $endYear - $currentYear);
        } else {
            // Default: assume liability cleared at retirement age
            $yearsUntilEnd = max(0, $retirementAge - $currentAge);
        }

        // If liability ends before death, it contributes £0 at death
        if ($yearsToProject >= $yearsUntilEnd) {
            return 0;
        }

        // Linear amortisation: remaining balance proportional to remaining term
        if ($yearsUntilEnd <= 0) {
            return $currentBalance; // Already past end date but still has balance
        }

        $remainingTerm = $yearsUntilEnd - $yearsToProject;
        $projectedBalance = $currentBalance * ($remainingTerm / $yearsUntilEnd);

        return max(0, $projectedBalance);
    }

    /**
     * Estimate payoff date from balance, monthly payment, and interest rate.
     */
    private function estimatePayoffDate($liability): ?string
    {
        $balance = (float) ($liability->current_balance ?? 0);
        $monthly = (float) ($liability->monthly_payment ?? 0);

        if ($balance <= 0 || $monthly <= 0) {
            return null;
        }

        $annualRate = (float) ($liability->interest_rate ?? 0);
        $monthlyRate = $annualRate / 100 / 12;

        if ($monthlyRate > 0 && $monthly <= $balance * $monthlyRate) {
            return null; // Payment doesn't cover interest
        }

        if ($monthlyRate > 0) {
            $months = (int) ceil(-log(1 - ($balance * $monthlyRate / $monthly)) / log(1 + $monthlyRate));
        } else {
            $months = (int) ceil($balance / $monthly);
        }

        return now()->addMonths($months)->format('Y-m-d');
    }

    /**
     * Calculate RNRB with full explanation message
     *
     * ALWAYS returns a value (even £0) with explanatory message.
     * For widows/widowers, includes transferred RNRB from deceased spouse.
     */
    private function calculateRNRB(
        float $totalNetEstate,
        User $user,
        ?User $spouse,
        array $ihtConfig,
        bool $isMarried,
        bool $isWidowed = false,
        ?IHTProfile $ihtProfile = null
    ): array {
        $rnrbSingle = $ihtConfig['residence_nil_rate_band']; // £175,000
        $taperThreshold = $ihtConfig['rnrb_taper_threshold']; // £2,000,000
        $taperRate = $ihtConfig['rnrb_taper_rate']; // 0.5 (£1 lost per £2 over threshold)

        // Get transferred RNRB for widows
        $rnrbTransferred = (float) ($ihtProfile?->rnrb_transferred_from_spouse ?? 0);

        // Check eligibility: must own main residence
        $hasMainResidence = $this->hasMainResidence($user, $spouse);

        // Calculate potential max RNRB for messaging
        $potentialMax = $isMarried ? ($rnrbSingle * 2) : ($isWidowed && $rnrbTransferred > 0 ? $rnrbSingle + $rnrbTransferred : $rnrbSingle);

        if (! $hasMainResidence) {
            return [
                'rnrb_available' => 0,
                'rnrb_individual' => 0,
                'rnrb_transferred' => 0,
                'rnrb_status' => 'none',
                'rnrb_message' => 'Residence Nil Rate Band not available. You need to own a main residence and leave it to direct descendants (children, grandchildren, step-children) to qualify for Residence Nil Rate Band of up to £'.number_format($potentialMax).'. Nieces, nephews, cousins, siblings, and other relatives are not direct descendants and do not qualify.',
            ];
        }

        // Calculate full RNRB (married gets double, widow with transfer gets own + transferred)
        if ($isMarried) {
            $fullRNRB = $rnrbSingle * 2;
        } elseif ($isWidowed && $rnrbTransferred > 0) {
            $fullRNRB = $rnrbSingle + $rnrbTransferred;
        } else {
            $fullRNRB = $rnrbSingle;
        }

        // Check for taper
        if ($totalNetEstate <= $taperThreshold) {
            // Build message based on status
            if ($isMarried) {
                $rnrbMsg = 'Full Residence Nil Rate Band of £'.number_format($fullRNRB).' available (£'.number_format($rnrbSingle).' each). Your combined estate is below the £'.number_format($taperThreshold).' taper threshold.';
            } elseif ($isWidowed && $rnrbTransferred > 0) {
                $rnrbMsg = 'Full Residence Nil Rate Band of £'.number_format($fullRNRB).' available (own £'.number_format($rnrbSingle).' + £'.number_format($rnrbTransferred).' transferred from late spouse\'s estate). Your estate is below the £'.number_format($taperThreshold).' taper threshold.';
            } else {
                $rnrbMsg = 'Full Residence Nil Rate Band of £'.number_format($fullRNRB).' available. Your estate is below the £'.number_format($taperThreshold).' taper threshold.';
            }

            return [
                'rnrb_available' => $fullRNRB,
                'rnrb_individual' => $rnrbSingle,
                'rnrb_transferred' => $rnrbTransferred,
                'rnrb_status' => 'full',
                'rnrb_message' => $rnrbMsg,
            ];
        }

        // Apply taper
        $excess = $totalNetEstate - $taperThreshold;
        $reduction = $excess * $taperRate;
        $rnrbAvailable = max(0, $fullRNRB - $reduction);

        if ($rnrbAvailable > 0) {
            return [
                'rnrb_available' => $rnrbAvailable,
                'rnrb_individual' => $rnrbSingle,
                'rnrb_transferred' => $rnrbTransferred,
                'rnrb_status' => 'tapered',
                'rnrb_message' => 'Residence Nil Rate Band reduced to £'.number_format($rnrbAvailable).' due to estate taper. Your estate of £'.number_format($totalNetEstate).' exceeds £'.number_format($taperThreshold).' by £'.number_format($excess).', reducing RNRB by £'.number_format($reduction).' (£1 reduction per £2 over threshold).',
            ];
        }

        // Fully tapered away
        return [
            'rnrb_available' => 0,
            'rnrb_individual' => $rnrbSingle,
            'rnrb_transferred' => $rnrbTransferred,
            'rnrb_status' => 'tapered',
            'rnrb_message' => 'Residence Nil Rate Band fully tapered away. Your estate of £'.number_format($totalNetEstate).' exceeds the taper threshold of £'.number_format($taperThreshold).' by £'.number_format($excess).', eliminating all RNRB of £'.number_format($fullRNRB).'.',
        ];
    }

    /**
     * Determine IHT rate based on charitable giving
     *
     * If 10%+ of the "baseline" (net estate minus NRB, excluding RNRB) is left to charity,
     * the reduced rate of 36% applies instead of 40%.
     *
     * @param  User  $user  The primary user
     * @param  float  $netEstate  Total net estate value
     * @param  float  $nrbAvailable  Total NRB available (single or combined)
     * @param  array  $ihtConfig  Tax configuration for IHT
     * @return array Rate determination with type and message
     */
    private function determineIHTRate(User $user, float $netEstate, float $nrbAvailable, array $ihtConfig): array
    {
        $standardRate = $ihtConfig['standard_rate']; // 0.40 (40%)
        $reducedRate = $ihtConfig['reduced_rate_charity'] ?? 0.36; // 0.36 (36%)

        // Get user's IHT profile for charitable giving percentage
        $ihtProfile = IHTProfile::where('user_id', $user->id)->first();
        $charitablePercent = $ihtProfile?->charitable_giving_percent ?? 0;

        // Calculate baseline: Net Estate - NRB (RNRB is excluded from baseline calculation)
        $baseline = max(0, $netEstate - $nrbAvailable);

        // Threshold for reduced rate: 10% of baseline
        $threshold = $baseline * 0.10;

        // Calculate charitable amount as percentage of net estate
        $charitableAmount = $netEstate * ($charitablePercent / 100);

        // Check if charitable giving meets the 10% of baseline threshold
        if ($charitablePercent > 0 && $charitableAmount >= $threshold && $baseline > 0) {
            return [
                'rate' => $reducedRate,
                'type' => 'reduced',
                'message' => 'Reduced IHT rate of 36% applies. Your charitable giving of '.number_format($charitablePercent, 1).'% (£'.number_format($charitableAmount).') meets the 10% threshold of £'.number_format($threshold).' (10% of baseline £'.number_format($baseline).').',
                'charitable_percent' => $charitablePercent,
                'baseline' => round($baseline, 2),
                'threshold' => round($threshold, 2),
            ];
        }

        // Standard rate applies
        if ($charitablePercent > 0 && $baseline > 0) {
            $shortfall = $threshold - $charitableAmount;

            return [
                'rate' => $standardRate,
                'type' => 'standard',
                'message' => 'Standard IHT rate of 40% applies. Your charitable giving of '.number_format($charitablePercent, 1).'% (£'.number_format($charitableAmount).') is below the 10% threshold of £'.number_format($threshold).'. Increase by £'.number_format($shortfall).' to qualify for 36% rate.',
                'charitable_percent' => $charitablePercent,
                'baseline' => round($baseline, 2),
                'threshold' => round($threshold, 2),
            ];
        }

        return [
            'rate' => $standardRate,
            'type' => 'standard',
            'message' => 'Standard IHT rate of 40% applies. Leave 10%+ of your baseline estate (£'.number_format($baseline).') to charity to qualify for the reduced 36% rate.',
            'charitable_percent' => 0,
            'baseline' => round($baseline, 2),
            'threshold' => round($threshold, 2),
        ];
    }

    /**
     * Calculate life expectancy for a user using actuarial tables.
     *
     * Delegates to FutureValueCalculator::getLifeExpectancyYears() which provides
     * interpolated lookups from the ONS actuarial life tables.
     */
    private function calculateLifeExpectancy(User $user): int
    {
        if (! $user->date_of_birth || ! $user->gender) {
            return 25; // Default fallback
        }

        $currentAge = Carbon::parse($user->date_of_birth)->age;
        $gender = strtolower($user->gender);

        return (int) round($this->futureValueCalculator->getLifeExpectancyYears($currentAge, $gender));
    }

    /**
     * Check if user or spouse has main residence
     */
    private function hasMainResidence(User $user, ?User $spouse): bool
    {
        $userHasMainRes = \App\Models\Property::where('user_id', $user->id)
            ->where('property_type', 'main_residence')
            ->exists();

        if ($userHasMainRes) {
            return true;
        }

        if ($spouse) {
            return \App\Models\Property::where('user_id', $spouse->id)
                ->where('property_type', 'main_residence')
                ->exists();
        }

        return false;
    }

    /**
     * Get cached calculation if valid
     */
    private function getCachedCalculation(User $user, ?User $spouse, bool $dataSharingEnabled): ?array
    {
        // Get latest calculation for this user
        $cached = IHTCalculation::where('user_id', $user->id)
            ->where('is_married', $spouse !== null)
            ->where('data_sharing_enabled', $dataSharingEnabled)
            ->latest('calculation_date')
            ->first();

        if (! $cached) {
            return null;
        }

        // Generate current hashes
        $currentHashes = $this->generateHashes($user, $spouse, $dataSharingEnabled);

        // Check if hashes match (data hasn't changed)
        if ($cached->assets_hash === $currentHashes['assets_hash'] &&
            $cached->liabilities_hash === $currentHashes['liabilities_hash'] &&
            $cached->result_json) {
            return $cached->result_json;
        }

        return null;
    }

    /**
     * Generate hashes for cache invalidation
     */
    private function generateHashes(User $user, ?User $spouse, bool $dataSharingEnabled): array
    {
        $userAssets = $this->aggregator->gatherUserAssets($user);
        $spouseAssets = ($spouse && $dataSharingEnabled) ? $this->aggregator->gatherUserAssets($spouse) : collect();

        $assetsString = $userAssets->pluck('current_value')->join(',').'|'.$spouseAssets->pluck('current_value')->join(',');
        $assetsHash = hash('sha256', $assetsString);

        $userLiabilities = $this->aggregator->calculateUserLiabilities($user);
        $spouseLiabilities = ($spouse && $dataSharingEnabled) ? $this->aggregator->calculateUserLiabilities($spouse) : 0;

        $liabilitiesString = $userLiabilities.'|'.$spouseLiabilities;
        $liabilitiesHash = hash('sha256', $liabilitiesString);

        return [
            'assets_hash' => $assetsHash,
            'liabilities_hash' => $liabilitiesHash,
        ];
    }

    /**
     * Save calculation to database
     */
    private function saveCalculation(
        User $user,
        array $result,
        Collection $userAssets,
        Collection $spouseAssets,
        float $userLiabilities,
        float $spouseLiabilities
    ): void {
        // Generate hashes
        $assetsString = $userAssets->pluck('current_value')->join(',').'|'.$spouseAssets->pluck('current_value')->join(',');
        $liabilitiesString = $userLiabilities.'|'.$spouseLiabilities;

        IHTCalculation::create([
            'user_id' => $user->id,
            'user_gross_assets' => $result['user_gross_assets'],
            'spouse_gross_assets' => $result['spouse_gross_assets'],
            'total_gross_assets' => $result['total_gross_assets'],
            'user_total_liabilities' => $result['user_total_liabilities'],
            'spouse_total_liabilities' => $result['spouse_total_liabilities'],
            'total_liabilities' => $result['total_liabilities'],
            'user_net_estate' => $result['user_net_estate'],
            'spouse_net_estate' => $result['spouse_net_estate'],
            'total_net_estate' => $result['total_net_estate'],
            'nrb_available' => $result['nrb_available'],
            'nrb_message' => $result['nrb_message'],
            'rnrb_available' => $result['rnrb_available'],
            'rnrb_status' => $result['rnrb_status'],
            'rnrb_message' => $result['rnrb_message'],
            'total_allowances' => $result['total_allowances'],
            'taxable_estate' => $result['taxable_estate'],
            'iht_liability' => $result['iht_liability'],
            'effective_rate' => $result['effective_rate'],
            'projected_gross_assets' => $result['projected_gross_assets'],
            'projected_liabilities' => $result['projected_liabilities'],
            'projected_net_estate' => $result['projected_net_estate'],
            'projected_taxable_estate' => $result['projected_taxable_estate'],
            'projected_iht_liability' => $result['projected_iht_liability'],
            'projected_cash' => $result['projected_cash'] ?? null,
            'projected_investments' => $result['projected_investments'] ?? null,
            'projected_properties' => $result['projected_properties'] ?? null,
            'retirement_age' => $result['retirement_age'] ?? null,
            'result_json' => $result,
            'years_to_death' => $result['years_to_death'],
            'estimated_age_at_death' => $result['estimated_age_at_death'],
            'calculation_date' => now(),
            'is_married' => $result['is_married'],
            'data_sharing_enabled' => $result['data_sharing_enabled'],
            'assets_hash' => hash('sha256', $assetsString),
            'liabilities_hash' => hash('sha256', $liabilitiesString),
        ]);
    }

    /**
     * Get life event cash impacts keyed by user age.
     *
     * Returns an array where keys are ages and values are the net cash impact
     * (income events positive, expense events negative) using raw amounts.
     *
     * @return array<int, float>
     */
    private function getLifeEventImpactsByAge(User $user, ?User $spouse, bool $dataSharingEnabled): array
    {
        $impacts = [];

        $userDob = $user->date_of_birth ? Carbon::parse($user->date_of_birth) : null;
        if (! $userDob) {
            return $impacts;
        }

        // Get user's active life events
        $events = $this->lifeEventService->getActiveEventsForProjection($user->id, false);

        foreach ($events as $event) {
            $age = (int) $userDob->diffInYears($event->expected_date);
            $amount = (float) $event->amount;

            if ($event->impact_type === 'expense') {
                $amount = -$amount;
            }

            $impacts[$age] = ($impacts[$age] ?? 0) + $amount;
        }

        // Include spouse events if data sharing enabled
        if ($dataSharingEnabled && $spouse) {
            $spouseEvents = $this->lifeEventService->getActiveEventsForProjection($spouse->id, false);

            foreach ($spouseEvents as $event) {
                // Map spouse event date to primary user's age timeline
                $age = (int) $userDob->diffInYears($event->expected_date);
                $amount = (float) $event->amount;

                if ($event->impact_type === 'expense') {
                    $amount = -$amount;
                }

                $impacts[$age] = ($impacts[$age] ?? 0) + $amount;
            }
        }

        return $impacts;
    }

    /**
     * Calculate NRB deduction for the primary user's gifts (PETs and CLTs).
     *
     * Implements the 14-year rule (Direction B): historical CLTs made 7-14 years
     * before death reduce the NRB available for PETs in the final 7 years.
     *
     * Only deducts from the PRIMARY user's own NRB. Spouse NRB is tracked
     * separately by SpouseNRBTrackerService.
     *
     * @param  User  $user  The primary user
     * @param  float  $nrbSingle  The individual NRB amount
     * @return array NRB deduction breakdown
     */
    private function calculateNRBDeductionForGifts(User $user, float $nrbSingle): array
    {
        // PETs within 7 years of today (assumed death date for calculation)
        $petsIn7Years = Gift::where('user_id', $user->id)
            ->where('gift_type', 'pet')
            ->where('gift_date', '>', today()->subYears(7))
            ->sum('gift_value');

        // CLTs within 7 years
        $cltsIn7Years = Gift::where('user_id', $user->id)
            ->where('gift_type', 'clt')
            ->where('gift_date', '>', today()->subYears(7))
            ->sum('gift_value');

        // 14-year rule (Direction B): CLTs made 7-14 years before death
        // These CLTs don't incur IHT themselves (outside 7-year window),
        // but they DO reduce the NRB available for PETs in the final 7 years
        $clts7to14Years = Gift::where('user_id', $user->id)
            ->where('gift_type', 'clt')
            ->where('gift_date', '>', today()->subYears(14))
            ->where('gift_date', '<=', today()->subYears(7))
            ->sum('gift_value');

        // CLTs (both recent and historical) consume NRB first
        $nrbUsedByCLTs = min($nrbSingle, (float) $cltsIn7Years + (float) $clts7to14Years);

        // Remaining NRB available for PETs after CLT consumption
        $nrbRemainingForPETs = max(0, $nrbSingle - $nrbUsedByCLTs);
        $nrbUsedByPETs = min($nrbRemainingForPETs, (float) $petsIn7Years);

        $totalNRBUsed = $nrbUsedByCLTs + $nrbUsedByPETs;

        return [
            'pets_in_7_years' => round((float) $petsIn7Years, 2),
            'clts_in_7_years' => round((float) $cltsIn7Years, 2),
            'clts_7_to_14_years' => round((float) $clts7to14Years, 2),
            'nrb_used_by_clts' => round($nrbUsedByCLTs, 2),
            'nrb_used_by_pets' => round($nrbUsedByPETs, 2),
            'total_nrb_used' => round($totalNRBUsed, 2),
            'fourteen_year_rule_applied' => $clts7to14Years > 0,
        ];
    }

    /**
     * Calculate the 2027 pension Inheritance Tax amendment dual-scenario projection.
     *
     * From April 2027, unused defined contribution pension pots will be included
     * in the taxable estate for Inheritance Tax purposes (Autumn Budget 2024).
     *
     * Returns both the current rules scenario and the post-2027 scenario,
     * allowing users to understand the potential impact.
     *
     * @param  User  $user  The primary user
     * @param  User|null  $spouse  The spouse
     * @param  bool  $dataSharingEnabled  Whether spouse data sharing is enabled
     * @param  array  $baseCalc  The base IHT calculation result
     * @return array Dual-scenario pension amendment data
     */
    private function calculatePensionAmendmentScenario(
        User $user,
        ?User $spouse,
        bool $dataSharingEnabled,
        array $baseCalc
    ): array {
        $pensionInclusion = $this->taxConfig->get('inheritance_tax.pension_iht_inclusion');

        // If pension IHT inclusion config not set, return no amendment
        if (! $pensionInclusion || ! isset($pensionInclusion['effective_date'])) {
            return [
                'amendment_warning' => false,
                'message' => 'No pension Inheritance Tax amendment configuration found.',
            ];
        }

        $effectiveDate = Carbon::parse($pensionInclusion['effective_date']);

        // Get total DC pension values
        $userPensionValue = (float) DCPension::where('user_id', $user->id)
            ->sum('current_fund_value');
        $spousePensionValue = 0;
        if ($dataSharingEnabled && $spouse) {
            $spousePensionValue = (float) DCPension::where('user_id', $spouse->id)
                ->sum('current_fund_value');
        }
        $totalPensionValue = $userPensionValue + $spousePensionValue;

        // If no pension value, no impact
        if ($totalPensionValue <= 0) {
            return [
                'amendment_warning' => false,
                'message' => 'No defined contribution pension values to include.',
            ];
        }

        // Calculate the post-2027 scenario: pensions included in estate
        $currentNetEstate = $baseCalc['total_net_estate'] ?? 0;
        $postAmendmentNetEstate = $currentNetEstate + $totalPensionValue;
        $totalAllowances = $baseCalc['total_allowances'] ?? 0;
        $ihtRate = $baseCalc['iht_rate'] ?? (float) $this->taxConfig->getInheritanceTax()['standard_rate'];

        $postAmendmentTaxableEstate = max(0, $postAmendmentNetEstate - $totalAllowances);
        $postAmendmentIHTLiability = $postAmendmentTaxableEstate * $ihtRate;

        $currentIHTLiability = $baseCalc['iht_liability'] ?? 0;
        $additionalIHT = $postAmendmentIHTLiability - $currentIHTLiability;

        return [
            'amendment_warning' => true,
            'effective_date' => $effectiveDate->format('Y-m-d'),
            'announced' => $pensionInclusion['announced'] ?? 'Autumn Budget 2024',
            'current_rules' => [
                'net_estate' => round($currentNetEstate, 2),
                'iht_liability' => round($currentIHTLiability, 2),
                'pensions_included' => false,
                'description' => 'Under current rules, defined contribution pensions pass outside the estate and are not subject to Inheritance Tax.',
            ],
            'post_2027_rules' => [
                'net_estate' => round($postAmendmentNetEstate, 2),
                'pension_value_included' => round($totalPensionValue, 2),
                'user_pension_value' => round($userPensionValue, 2),
                'spouse_pension_value' => round($spousePensionValue, 2),
                'iht_liability' => round($postAmendmentIHTLiability, 2),
                'additional_iht' => round($additionalIHT, 2),
                'pensions_included' => true,
                'description' => 'From April 2027, unused defined contribution pension pots will be included in the taxable estate for Inheritance Tax purposes.',
            ],
            'impact_summary' => $additionalIHT > 0
                ? 'The 2027 pension amendment could increase your Inheritance Tax liability by £'.number_format($additionalIHT).' if your defined contribution pension pots (£'.number_format($totalPensionValue).') are included in your estate.'
                : 'The 2027 pension amendment would not increase your Inheritance Tax liability based on current pension values.',
        ];
    }

    /**
     * Invalidate cache when assets or liabilities change
     */
    public function invalidateCache(User $user): void
    {
        IHTCalculation::where('user_id', $user->id)->delete();
    }
}
