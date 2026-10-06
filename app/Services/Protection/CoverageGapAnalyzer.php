<?php

declare(strict_types=1);

namespace App\Services\Protection;

use App\Models\ProtectionProfile;
use App\Models\User;
use App\Services\Retirement\StatePensionAgeResolver;
use App\Services\Shared\CrossModuleAssetAggregator;
use App\Services\Tax\IncomeDefinitionsService;
use App\Services\Tax\PensionAffordability;
use App\Services\TaxConfigService;
use App\Services\UKTaxCalculator;
use App\Services\UserProfile\UserProfileService;
use App\Traits\ResolvesExpenditure;
use App\Traits\ResolvesIncome;
use Illuminate\Support\Collection;

class CoverageGapAnalyzer
{
    use ResolvesExpenditure;
    use ResolvesIncome;

    public function __construct(
        private UKTaxCalculator $taxCalculator,
        private readonly TaxConfigService $taxConfig,
        private readonly CrossModuleAssetAggregator $assetAggregator
    ) {}

    /**
     * The lump sum that pays the family's yearly income gap for $years years:
     * gap x (1 - (1 + r)^-n) / r, with r the Personal Injury Discount Rate, the
     * rate the law uses to turn a future lost income into a lump sum
     * (protection.needs_calculation.life_cover.income_replacement; CSJ
     * 2026-10-06, D1). It replaced a 4.7% perpetuity with no source.
     */
    public function calculateHumanCapital(float $annualIncomeGap, float $years): float
    {
        if ($annualIncomeGap <= 0 || $years <= 0) {
            return 0.0;
        }

        $rate = (float) $this->taxConfig->getProtectionNeeds()['life_cover']['income_replacement']['discount_rate'];
        if ($rate == 0.0) {
            return $annualIncomeGap * $years;
        }

        return $annualIncomeGap * (1 - (1 + $rate) ** -$years) / $rate;
    }

    /**
     * Calculate debt protection need.
     * Built from the user's actual records; the ProtectionProfile summary fields are
     * a fallback used only where no records exist. See debtProtectionBasis().
     *
     * **A protection need is personal, so it is the user's SHARE of each debt.**
     * This read the whole of every record the user was primary owner of, at 100%:
     * a joint mortgage charged him his wife's half as well, and a tenants-in-common
     * mortgage charged him the share belonging to a co-owner who has no account
     * here at all (W-0187). Counting a third party's debt as cover a user must buy
     * is the same class of error as putting their share of a property into his
     * estate, which the property and estate modules already refuse to do.
     *
     * Both halves now come from CrossModuleAssetAggregator, the one home for
     * "what does this user owe" — reach-complete (it picks up a mortgage on a
     * jointly-owned property even where the user is not the borrower) and
     * fraction-correct (a share belonging to someone with no account reduces the
     * user's figure without being credited to anyone).
     */
    public function calculateDebtProtectionNeed(ProtectionProfile $profile): float
    {
        return (float) $this->debtProtectionBasis($profile)['total'];
    }

    /**
     * The debt protection need, WITH the components it was built from and which
     * source produced it.
     *
     * **W-0227 — the records now win where records exist, and the panel discloses
     * what it actually used.**
     *
     * `protection_profiles.mortgage_balance` and `.other_debts` are a manual summary
     * that predates the `mortgages` and `liabilities` tables. This method used to
     * read them FIRST and return early on them, so a figure typed once outranked
     * every mortgage record the user owns, permanently and with nothing on screen
     * saying so — records could change by hundreds of thousands and the need would
     * not move. No form writes those two columns; only
     * `StoreProtectionProfileRequest` accepts them, so the override was reachable
     * without ever being visible.
     *
     * The order is reversed rather than the fields deleted, which is the option the
     * item's acceptance offered as "keep it only where no records exist". Deleting
     * them outright would drop the need to zero for anyone who had supplied a
     * summary and holds no records — a regression dressed as a fix.
     *
     * `components` is what the caller must disclose. Publishing the profile columns
     * beside a need computed from the records is what made the panel state
     * `mortgage_balance £0, other_debts £0` above a need of £182,500: the two
     * numbers offered as the inputs had contributed nothing to it.
     *
     * @return array{total: float, source: string, components: array<string, float>}
     */
    public function debtProtectionBasis(ProtectionProfile $profile): array
    {
        // Records first — at the user's share of each, from the one home for
        // "what does this user owe" (W-0187).
        $fromRecords = $this->assetAggregator->calculateLiabilityTotals((int) $profile->user_id);

        if ((float) $fromRecords['total'] > 0) {
            return [
                'total' => (float) $fromRecords['total'],
                'source' => 'records',
                'components' => [
                    'mortgages' => round((float) $fromRecords['mortgages'], 2),
                    'other_debts' => round((float) $fromRecords['other'], 2),
                ],
            ];
        }

        // No records. Fall back to the summary the user supplied, and say so.
        $mortgageBalance = (float) ($profile->mortgage_balance ?? 0);
        $otherDebts = (float) ($profile->other_debts ?? 0);

        return [
            'total' => $mortgageBalance + $otherDebts,
            'source' => 'profile',
            'components' => [
                'mortgages' => round($mortgageBalance, 2),
                'other_debts' => round($otherDebts, 2),
            ],
        ];
    }

    /**
     * Final expenses: SunLife's cost of dying (funeral, professional fees and
     * send-off), protection.needs_calculation.life_cover.final_expenses (D4).
     *
     * There is no separate education figure (D5): children's costs sit in the
     * household living costs the income gap replaces, and tuition in England is
     * paid by the student's own loan.
     */
    public function calculateFinalExpenses(): float
    {
        return (float) $this->taxConfig->getProtectionNeeds()['life_cover']['final_expenses']['amount'];
    }

    /**
     * Critical illness need: the rule-of-thumb multiple of gross earned income
     * (protection.needs_calculation.critical_illness; CSJ 2026-10-06, D3: a rule
     * of thumb, shown to the user as one). The one place it is worked out.
     */
    public function criticalIllnessNeed(float $grossEarnedIncome): float
    {
        return max(0.0, $grossEarnedIncome) * (float) $this->taxConfig->getProtectionNeeds()['critical_illness']['income_multiple'];
    }

    /**
     * Income protection need: the insurer limit on gross earned income, tier by
     * tier (protection.needs_calculation.income_protection.benefit_tiers; D6,
     * Legal & General 60% of the first £60,000 and 50% above).
     */
    public function incomeProtectionNeed(float $grossEarnedIncome): float
    {
        $need = 0.0;
        $floor = 0.0;
        foreach ($this->taxConfig->getProtectionNeeds()['income_protection']['benefit_tiers'] as $tier) {
            $ceiling = $tier['up_to'] === null ? INF : (float) $tier['up_to'];
            $slice = max(0.0, min($grossEarnedIncome, $ceiling) - $floor);
            $need += $slice * (float) $tier['rate'];
            if ($grossEarnedIncome <= $ceiling) {
                break;
            }
            $floor = $ceiling;
        }

        return $need;
    }

    /**
     * Monthly living costs that continue after a death: recorded spending plus a
     * home's running costs, without mortgage, loan, pension, saving, investment
     * or protection payments, which are cleared by the debt part of the need or
     * stop (D7). The one spending source (UserProfileService).
     */
    public function monthlyLivingCosts(User $user): float
    {
        $profileService = app(UserProfileService::class);
        $manual = (float) $profileService->getExpenditureBreakdown($user)['monthly_manual'];
        $homeRunningCosts = 0.0;
        foreach ($profileService->getFinancialCommitments($user)['properties'] ?? [] as $property) {
            $homeRunningCosts += (float) ($property['monthly_amount'] ?? 0) - (float) ($property['breakdown']['mortgage'] ?? 0);
        }

        return round($manual + max(0.0, $homeRunningCosts), 2);
    }

    /**
     * Calculate total coverage from policies, including employer benefits.
     */
    public function calculateTotalCoverage(
        Collection $lifePolicies,
        Collection $criticalIllnessPolicies,
        Collection $incomeProtectionPolicies,
        Collection $disabilityPolicies,
        Collection $sicknessIllnessPolicies,
        ?ProtectionProfile $profile = null,
        ?User $user = null
    ): array {
        $lifeCoverage = $lifePolicies->sum('sum_assured');
        $criticalIllnessCoverage = $criticalIllnessPolicies->sum('sum_assured');

        // Income protection: annualized benefit amount
        $incomeProtectionCoverage = 0;
        foreach ($incomeProtectionPolicies as $policy) {
            if ($policy->benefit_frequency === 'monthly') {
                $incomeProtectionCoverage += $policy->benefit_amount * 12;
            } elseif ($policy->benefit_frequency === 'weekly') {
                $incomeProtectionCoverage += $policy->benefit_amount * 52;
            }
        }

        // Disability coverage: annualized benefit amount
        $disabilityCoverage = 0;
        foreach ($disabilityPolicies as $policy) {
            if ($policy->benefit_frequency === 'monthly') {
                $disabilityCoverage += $policy->benefit_amount * 12;
            } elseif ($policy->benefit_frequency === 'weekly') {
                $disabilityCoverage += $policy->benefit_amount * 52;
            }
        }

        // Sickness/Illness coverage: can be lump sum, monthly, or weekly
        $sicknessIllnessCoverage = 0;
        foreach ($sicknessIllnessPolicies as $policy) {
            if ($policy->benefit_frequency === 'monthly') {
                $sicknessIllnessCoverage += $policy->benefit_amount * 12;
            } elseif ($policy->benefit_frequency === 'weekly') {
                $sicknessIllnessCoverage += $policy->benefit_amount * 52;
            } elseif ($policy->benefit_frequency === 'lump_sum') {
                $sicknessIllnessCoverage += $policy->benefit_amount;
            }
        }

        // Employer benefits integration
        $deathInServiceCoverage = 0.0;
        $groupIpCoverage = 0.0;
        $groupCiCoverage = 0.0;
        $employerWarnings = [];

        if ($profile !== null) {
            // Death in service: multiple x gross employment salary
            // Use User.annual_employment_income (primary source), fall back to profile
            $salary = ($user?->annual_employment_income ?? 0) > 0
                ? (float) $user->annual_employment_income
                : (float) ($profile->annual_income ?? 0);

            if ($profile->death_in_service_multiple !== null && $profile->death_in_service_multiple > 0) {
                $deathInServiceCoverage = (float) $profile->death_in_service_multiple * $salary;
                $lifeCoverage += $deathInServiceCoverage;
            }

            // Employer reliance warning: if death in service exceeds configured threshold of total life cover
            $disRelianceThreshold = (float) $this->taxConfig->getProtectionNeeds()['employer_cover']['reliance_share'];
            if ($deathInServiceCoverage > 0 && $lifeCoverage > 0
                && ($deathInServiceCoverage / $lifeCoverage) > $disRelianceThreshold) {
                $employerWarnings[] = 'Over half your life cover comes from death in service. This cover is lost if you leave employment.';
            }

            // Group income protection: percent of salary, annualised
            if ($profile->group_ip_benefit_percent !== null && $profile->group_ip_benefit_percent > 0) {
                $groupIpCoverage = ($salary * (float) $profile->group_ip_benefit_percent / 100);
                $incomeProtectionCoverage += $groupIpCoverage;
            }

            // Group critical illness
            if ($profile->group_ci_amount !== null && $profile->group_ci_amount > 0) {
                $groupCiCoverage = (float) $profile->group_ci_amount;
                $criticalIllnessCoverage += $groupCiCoverage;
            }
        }

        return [
            'life_coverage' => $lifeCoverage,
            'critical_illness_coverage' => $criticalIllnessCoverage,
            'income_protection_coverage' => $incomeProtectionCoverage,
            'disability_coverage' => $disabilityCoverage,
            'sickness_illness_coverage' => $sicknessIllnessCoverage,
            'total_coverage' => $lifeCoverage + $criticalIllnessCoverage,
            'total_income_coverage' => $incomeProtectionCoverage + $disabilityCoverage + $sicknessIllnessCoverage,
            'employer_benefits' => [
                'death_in_service' => $deathInServiceCoverage,
                'group_income_protection' => $groupIpCoverage,
                'group_critical_illness' => $groupCiCoverage,
                'has_employer_pmi' => (bool) ($profile?->has_employer_pmi ?? false),
            ],
            'employer_warnings' => $employerWarnings,
        ];
    }

    /**
     * Calculate coverage gaps.
     * Allocation priority: Life insurance covers debts FIRST, then excess reduces human capital need.
     */
    public function calculateCoverageGap(array $needs, array $coverage): array
    {
        $totalNeed = $needs['human_capital']
                   + $needs['debt_protection']
                   + $needs['final_expenses'];

        $lifeCoverage = $coverage['life_coverage'];

        // STEP 1: Allocate life cover to debts FIRST
        $debtNeed = $needs['debt_protection'];
        $debtCovered = min($lifeCoverage, $debtNeed); // How much debt is covered
        $debtGap = max(0, $debtNeed - $debtCovered);

        // STEP 2: Any excess life cover reduces human capital need
        $excessAfterDebt = max(0, $lifeCoverage - $debtNeed);
        $humanCapitalNeed = $needs['human_capital'];
        $humanCapitalCovered = min($excessAfterDebt, $humanCapitalNeed);
        $humanCapitalGap = max(0, $humanCapitalNeed - $humanCapitalCovered);

        // STEP 3: Allocate remaining excess to final expenses
        $excessAfterHumanCapital = max(0, $excessAfterDebt - $humanCapitalCovered);
        $finalExpensesCovered = min($excessAfterHumanCapital, $needs['final_expenses']);
        $finalExpensesGap = max(0, $needs['final_expenses'] - $finalExpensesCovered);

        // STEP 5: Income-based policies (separate track from life cover allocation)
        $totalIncomeCoverage = $coverage['income_protection_coverage']
                             + $coverage['disability_coverage']
                             + $coverage['sickness_illness_coverage'];

        // Income protection need (the insurer limit on gross earned income) vs total income coverage
        $incomeProtectionNeed = $needs['income_protection_need'] ?? 0;
        $incomeProtectionGap = max(0, $incomeProtectionNeed - $totalIncomeCoverage);

        // Break down by policy type for granular reporting
        $ipCoverage = $coverage['income_protection_coverage'] ?? 0;
        $disabilityCoverage = $coverage['disability_coverage'] ?? 0;
        $sicknessCoverage = $coverage['sickness_illness_coverage'] ?? 0;

        // Individual gaps (IP is primary; disability and sickness are supplementary)
        $ipSpecificGap = max(0, $incomeProtectionNeed - $ipCoverage);
        $disabilityGap = $ipCoverage >= $incomeProtectionNeed ? 0 : max(0, $incomeProtectionGap - $disabilityCoverage);
        $sicknessGap = ($ipCoverage + $disabilityCoverage) >= $incomeProtectionNeed ? 0 : max(0, $incomeProtectionGap - $disabilityCoverage - $sicknessCoverage);

        // Use passed total_coverage (life + CI) for reporting
        $totalCoverage = $coverage['total_coverage'] ?? ($lifeCoverage + ($coverage['critical_illness_coverage'] ?? 0));

        // Calculate total coverage used (from allocation)
        $totalCoverageUsed = $debtCovered + $humanCapitalCovered + $finalExpensesCovered;

        // Total gap is based on total coverage (life + CI), not just allocated amount
        $totalGap = max(0, $totalNeed - $totalCoverage);

        $gapsByCategory = [
            'human_capital_gap' => $humanCapitalGap,
            'debt_protection_gap' => $debtGap,
            'final_expenses_gap' => $finalExpensesGap,
            'income_protection_gap' => $incomeProtectionGap,
            'disability_coverage_gap' => $disabilityGap,
            'sickness_illness_gap' => $sicknessGap,
        ];

        return [
            'total_need' => $totalNeed,
            'total_coverage' => $totalCoverage,
            'total_coverage_used' => $totalCoverageUsed,
            'total_gap' => $totalGap,
            // W-0479 — published here rather than counted by each consumer. Two
            // dashboards were deriving it from shapes this method has never emitted
            // (`$gap['gap']` on `/m` and native, `$gap['shortfall']` on web), so both
            // read ZERO for every household in the application's history — and the
            // count gates `detectProtectionAdequate()`, which told households with at
            // least one policy that "your protection now covers what your family would
            // need". The producer publishes the number; consumers stop guessing.
            'critical_gap_count' => $this->countCriticalGaps($gapsByCategory, $totalGap),
            'gaps_by_category' => $gapsByCategory,
            'coverage_allocated' => [
                'debt_covered' => $debtCovered,
                'human_capital_covered' => $humanCapitalCovered,
                'final_expenses_covered' => $finalExpensesCovered,
                'excess_unused' => max(0, $lifeCoverage - $totalCoverageUsed),
            ],
            'income_replacement_coverage' => $totalIncomeCoverage,
            'coverage_percentage' => $totalNeed > 0 ? ($totalCoverage / $totalNeed) * 100 : 100,
        ];
    }

    /**
     * Calculate total protection needs.
     * Pulls income from user's actual income fields to reflect current situation.
     * Tracks spouse income separately - spouse income REDUCES protection need (continues after user's death).
     * Excludes rental and dividend income (continues after death).
     */
    public function calculateProtectionNeeds(ProtectionProfile $profile): array
    {
        $user = $profile->user;

        // Calculate USER'S NET annual income after tax and NI (EMPLOYMENT/SELF-EMPLOYMENT ONLY)
        // These are earned income streams that STOP on death
        $userTaxCalculation = $this->taxCalculator->calculateNetIncome(
            (float) ($user->annual_employment_income ?? 0),
            (float) ($user->annual_self_employment_income ?? 0),
            0, // Rental income calculated separately
            0, // Dividend income calculated separately
            (float) ($user->annual_other_income ?? 0),
            // W-0511 — the allowance the household is entitled to, from the one place
            // that answers the entitlement question.
            blindPersonsAllowance: $this->taxConfig->blindPersonsAllowanceFor($user)
        );

        $userGrossIncome = $userTaxCalculation['gross_income'];
        $userNetIncome = $userTaxCalculation['net_income'];

        // Calculate USER'S continuing income (rental + dividend) - these CONTINUE after death
        // Rent as the rental profit the Income page shows (IncomeDefinitionsService;
        // CSJ 2026-10-02, one income figure), not the stored users column.
        $userContinuingIncome = (float) app(IncomeDefinitionsService::class)->calculateFor($user)['components']['rental']
                              + (float) ($user->annual_dividend_income ?? 0);

        // Track spouse income separately
        $spouseIncluded = false;
        $spouseGrossIncome = 0;
        $spouseNetIncome = 0;
        $spouseContinuingIncome = 0;
        $spousePermissionDenied = false;

        // Check for spouse and track spouse income separately
        if ($user->liveSpouseId() && $user->marital_status === 'married') {
            // Check if spouse permission is accepted (either direction)
            if ($user->hasAcceptedSpousePermission()) {
                // Permission granted - track spouse income (REDUCES protection need)
                $spouse = $user->spouse;
                if ($spouse) {
                    // Get spouse income from spouse's user record
                    $spouseEmploymentIncome = (float) ($spouse->annual_employment_income ?? 0);
                    $spouseSelfEmploymentIncome = (float) ($spouse->annual_self_employment_income ?? 0);
                    $spouseRentalIncome = (float) app(IncomeDefinitionsService::class)->calculateFor($spouse)['components']['rental'];
                    $spouseDividendIncome = (float) ($spouse->annual_dividend_income ?? 0);
                    $spouseOtherIncome = (float) ($spouse->annual_other_income ?? 0);

                    // Spouse earned income (employment/self-employment)
                    $spouseTaxCalc = $this->taxCalculator->calculateNetIncome(
                        $spouseEmploymentIncome,
                        $spouseSelfEmploymentIncome,
                        0, // Rental income calculated separately
                        0, // Dividend income calculated separately
                        $spouseOtherIncome,
                        // W-0511 — the spouse's own entitlement, not the user's.
                        blindPersonsAllowance: $this->taxConfig->blindPersonsAllowanceFor($spouse)
                    );

                    $spouseGrossIncome = $spouseTaxCalc['gross_income'];
                    $spouseNetIncome = $spouseTaxCalc['net_income'];

                    // Spouse continuing income (rental + dividend)
                    $spouseContinuingIncome = $spouseRentalIncome + $spouseDividendIncome;

                    $spouseIncluded = true;
                }
            } else {
                // Spouse exists but permission not granted
                $spousePermissionDenied = true;
            }
        }

        // If no income in user profile, fall back to protection profile
        if ($userGrossIncome == 0) {
            $userNetIncome = $profile->annual_income; // Assume net if using profile fallback
            $userGrossIncome = $profile->annual_income;
        }

        // The family's income gap (D7, CSJ 2026-10-06): the household's living
        // costs that continue, less the income that continues. Before, this was
        // the user's net income less the partner's whole income, so a couple who
        // earned the same "lost nothing". Spending not recorded: the gap is not
        // guessed; the need says so and the surfaces ask for it (as #1027).
        $spendingRecorded = app(PensionAffordability::class)->spendingRecorded($user);
        $householdLivingCosts = $this->monthlyLivingCosts($user) * 12;
        if ($spouseIncluded && isset($spouse)) {
            $householdLivingCosts += $this->monthlyLivingCosts($spouse) * 12;
        }

        $incomeThatStops = $userNetIncome;
        $incomeThatContinues = $userContinuingIncome + $spouseNetIncome + $spouseContinuingIncome;
        $incomeGap = $spendingRecorded ? max(0.0, $householdLivingCosts - $incomeThatContinues) : 0.0;

        // The term: until the user's State Pension age, when their earnings would
        // have stopped (D2). Past it, no earnings are lost.
        $statePensionDate = app(StatePensionAgeResolver::class)->dateForUser($user);
        $termYears = $statePensionDate !== null && $statePensionDate->isFuture()
            ? round(now()->diffInDays($statePensionDate) / 365.25, 2)
            : 0.0;

        $humanCapital = $this->calculateHumanCapital($incomeGap, $termYears);

        $debtProtection = $this->calculateDebtProtectionNeed($profile);
        $finalExpenses = $this->calculateFinalExpenses();

        // Total life need = income replacement + debts + final expenses.
        $totalNeed = $humanCapital + $debtProtection + $finalExpenses;

        $incomeProtectionNeed = $this->incomeProtectionNeed((float) $userGrossIncome);
        $criticalIllnessNeed = $this->criticalIllnessNeed((float) $userGrossIncome);
        $needsConfig = $this->taxConfig->getProtectionNeeds();

        // Statutory Sick Pay, from tax config only (Rule 2). Employees only.
        // https://www.gov.uk/statutory-sick-pay/what-youll-get: the weekly rate
        // "or 80% of your normal weekly earnings - whichever is lower", for up to
        // max_weeks. From April 2026 the lower earnings limit is null (abolished)
        // and lower_earner_rate carries the 80%; earlier years have a limit and
        // no lower-earner rate.
        $sspRate = (float) $this->taxConfig->get('benefits.ssp.weekly_rate');
        $sspMaxWeeks = (int) $this->taxConfig->get('benefits.ssp.max_weeks');
        $sspLowerEarningsLimit = $this->taxConfig->get('benefits.ssp.lower_earnings_limit');
        $sspLowerEarnerRate = $this->taxConfig->get('benefits.ssp.lower_earner_rate');

        // Determine if the user is employed (earns employment income) or self-employed
        $hasEmploymentIncome = ((float) ($user->annual_employment_income ?? 0)) > 0;
        $hasSelfEmploymentIncome = ((float) ($user->annual_self_employment_income ?? 0)) > 0;
        $isSelfEmployed = $hasSelfEmploymentIncome && ! $hasEmploymentIncome;

        // SSP: total entitlement for the limited period (NOT annualised). A
        // missing rate or period means no figure, never a guessed one.
        $sspWeekly = 0.0;
        $totalSspEntitlement = 0.0;
        $sspEligible = false;
        if ($hasEmploymentIncome && ! $isSelfEmployed && $sspRate > 0 && $sspMaxWeeks > 0) {
            $weeklyEarnings = (float) $user->annual_employment_income / 52;
            if ($sspLowerEarningsLimit === null || $weeklyEarnings >= (float) $sspLowerEarningsLimit) {
                $sspWeekly = $sspLowerEarnerRate === null
                    ? $sspRate
                    : min($sspRate, round($weeklyEarnings * (float) $sspLowerEarnerRate, 2));
                $totalSspEntitlement = $sspWeekly * $sspMaxWeeks;
                $sspEligible = true;
            }
        }

        // ESA support rate (noted as potential, not guaranteed — subject to National Insurance contributions)
        $esaSupportRate = (float) $this->taxConfig->get('benefits.esa.assessment_rate_25_plus');
        $esaMonthlyEquivalent = ($esaSupportRate * 52) / 12;

        return [
            'human_capital' => $humanCapital,
            'debt_protection' => $debtProtection,
            'final_expenses' => $finalExpenses,
            'income_protection_need' => $incomeProtectionNeed,
            'critical_illness_need' => $criticalIllnessNeed,
            // How the life need's income part was worked out, for every surface
            // and Fyn to show as sent (Rule 20).
            'income_replacement' => [
                'spending_recorded' => $spendingRecorded,
                'household_living_costs' => round($householdLivingCosts, 2),
                'income_that_continues' => round($incomeThatContinues, 2),
                'income_gap' => round($incomeGap, 2),
                'term_years' => $termYears,
                'state_pension_date' => $statePensionDate?->toDateString(),
                'discount_rate' => (float) $needsConfig['life_cover']['income_replacement']['discount_rate'],
            ],
            // Plain-words provenance for Fyn. Live 2026-09-23 (fynla.org,
            // conversation 888): the model read the need as "£21,000 of annual
            // income you would lose" and then invented where it came from.
            'income_protection_basis' => sprintf(
                'Income protection would replace £%s a year: the most an insurer pays on £%s gross earned income (%s; employment, self-employment and other earned income). Rental and dividend income continue if the user cannot work, so they are excluded.',
                number_format((float) $incomeProtectionNeed, 2),
                number_format((float) $userGrossIncome, 2),
                $this->benefitTiersInWords()
            ),
            'critical_illness_basis' => sprintf(
                'A rule of thumb, not a set amount: %s times your gross earned income of £%s. Critical illness cover is usually set by what you can afford.',
                rtrim(rtrim(number_format((float) $needsConfig['critical_illness']['income_multiple'], 2), '0'), '.'),
                number_format((float) $userGrossIncome)
            ),
            'total_need' => $totalNeed,
            'gross_income' => $userGrossIncome,
            'net_income' => $userNetIncome,
            'continuing_income' => $userContinuingIncome,
            'income_that_stops' => $incomeThatStops,
            'income_that_continues' => $incomeThatContinues,
            'income_gap' => round($incomeGap, 2),
            'income_tax' => $userTaxCalculation['income_tax'] ?? 0,
            'national_insurance' => $userTaxCalculation['national_insurance'] ?? 0,
            'spouse_included' => $spouseIncluded,
            'spouse_gross_income' => $spouseGrossIncome,
            'spouse_net_income' => $spouseNetIncome,
            'spouse_continuing_income' => $spouseContinuingIncome,
            'spouse_permission_denied' => $spousePermissionDenied,
            'state_benefits' => [
                'ssp_eligible' => $sspEligible,
                'ssp_weekly_rate' => $sspWeekly,
                'ssp_max_weeks' => $sspMaxWeeks,
                'ssp_total_entitlement' => $totalSspEntitlement,
                'is_self_employed' => $isSelfEmployed,
                'esa_monthly_equivalent' => $esaMonthlyEquivalent,
                'esa_note' => 'Employment and Support Allowance is subject to National Insurance contribution eligibility',
            ],
        ];
    }

    /** "60% of the first £60,000 and 50% above", from the configured tiers. */
    public function benefitTiersInWords(): string
    {
        $parts = [];
        $floor = null;
        foreach ($this->taxConfig->getProtectionNeeds()['income_protection']['benefit_tiers'] as $tier) {
            $pct = rtrim(rtrim(number_format((float) $tier['rate'] * 100, 2), '0'), '.').'%';
            $parts[] = $tier['up_to'] === null
                ? $pct.($floor === null ? '' : ' above')
                : $pct.' of the first £'.number_format((float) $tier['up_to']);
            $floor = $tier['up_to'];
        }

        return implode(' and ', $parts);
    }

    /**
     * How many DISTINCT protection gaps this household has. W-0479.
     *
     * `disability_coverage_gap` and `sickness_illness_gap` are deliberately not
     * counted: the comment above their calculation says it outright — "IP is primary;
     * disability and sickness are supplementary" — and they carry the SAME shortfall
     * as `income_protection_gap` when there is no separate cover. Counting all three
     * turns one uncovered income into "3 critical gaps".
     *
     * @param  array<string, float|int>  $gapsByCategory
     */
    private function countCriticalGaps(array $gapsByCategory, float $totalGap): int
    {
        $countable = [
            'human_capital_gap',
            'debt_protection_gap',
            'final_expenses_gap',
            'income_protection_gap',
        ];

        $count = 0;

        foreach ($countable as $category) {
            if (($gapsByCategory[$category] ?? 0) > 0) {
                $count++;
            }
        }

        // A total shortfall with no category above zero is still a gap, and reporting
        // none would be the same silent under-count this item exists to end.
        if ($count === 0 && $totalGap > 0) {
            return 1;
        }

        return $count;
    }
}
