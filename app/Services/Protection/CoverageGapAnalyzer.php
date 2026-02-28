<?php

declare(strict_types=1);

namespace App\Services\Protection;

use App\Models\ProtectionProfile;
use App\Services\UKTaxCalculator;
use Illuminate\Support\Collection;

class CoverageGapAnalyzer
{
    public function __construct(
        private UKTaxCalculator $taxCalculator
    ) {}

    /**
     * Calculate the life cover capital required to replace the family's lost income.
     *
     * Uses a sustainable drawdown approach: the lump sum needed so the family
     * can draw the required annual income indefinitely at a 4.7% withdrawal rate.
     *
     * Formula: Annual Income Need / 0.047
     */
    public function calculateHumanCapital(float $annualIncomeNeed): float
    {
        if ($annualIncomeNeed <= 0) {
            return 0.0;
        }

        return $annualIncomeNeed / 0.047;
    }

    /**
     * Calculate debt protection need.
     * Uses ProtectionProfile fields if provided, otherwise pulls from actual records.
     */
    public function calculateDebtProtectionNeed(ProtectionProfile $profile): float
    {
        // Use ProtectionProfile summary fields if available
        $mortgageBalance = (float) ($profile->mortgage_balance ?? 0);
        $otherDebts = (float) ($profile->other_debts ?? 0);

        // If profile has data, use it
        if ($mortgageBalance > 0 || $otherDebts > 0) {
            return $mortgageBalance + $otherDebts;
        }

        // Otherwise, pull from actual records
        $user = $profile->user;

        // Get total mortgage debt from mortgages table
        $totalMortgageDebt = (float) $user->mortgages()->sum('outstanding_balance');

        // Get total other liabilities from liabilities table
        $totalOtherDebt = (float) $user->liabilities()->sum('current_balance');

        return $totalMortgageDebt + $totalOtherDebt;
    }

    /**
     * Calculate education funding need.
     * Assumes £9,000 per year until age 21 for each child.
     */
    public function calculateEducationFunding(int $numChildren, array $ages): float
    {
        $annualCostPerChild = 9000;
        $educationEndAge = 21;
        $totalFunding = 0.0;

        foreach ($ages as $age) {
            $yearsRemaining = max(0, $educationEndAge - $age);
            $totalFunding += $annualCostPerChild * $yearsRemaining;
        }

        return $totalFunding;
    }

    /**
     * Calculate final expenses.
     */
    public function calculateFinalExpenses(): float
    {
        return 7500; // Fixed amount for funeral and final expenses
    }

    /**
     * Calculate total coverage from policies.
     */
    public function calculateTotalCoverage(
        Collection $lifePolicies,
        Collection $criticalIllnessPolicies,
        Collection $incomeProtectionPolicies,
        Collection $disabilityPolicies,
        Collection $sicknessIllnessPolicies
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

        return [
            'life_coverage' => $lifeCoverage,
            'critical_illness_coverage' => $criticalIllnessCoverage,
            'income_protection_coverage' => $incomeProtectionCoverage,
            'disability_coverage' => $disabilityCoverage,
            'sickness_illness_coverage' => $sicknessIllnessCoverage,
            'total_coverage' => $lifeCoverage + $criticalIllnessCoverage,
            'total_income_coverage' => $incomeProtectionCoverage + $disabilityCoverage + $sicknessIllnessCoverage,
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
                   + $needs['education_funding']
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

        // STEP 4: Allocate remaining excess to education funding
        $excessAfterFinalExpenses = max(0, $excessAfterHumanCapital - $finalExpensesCovered);
        $educationCovered = min($excessAfterFinalExpenses, $needs['education_funding']);
        $educationGap = max(0, $needs['education_funding'] - $educationCovered);

        // STEP 5: Income-based policies (separate track from life cover allocation)
        $totalIncomeCoverage = $coverage['income_protection_coverage']
                             + $coverage['disability_coverage']
                             + $coverage['sickness_illness_coverage'];

        // Income protection need (60% of gross) vs total income coverage
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
        $totalCoverageUsed = $debtCovered + $humanCapitalCovered + $finalExpensesCovered + $educationCovered;

        // Total gap is based on total coverage (life + CI), not just allocated amount
        $totalGap = max(0, $totalNeed - $totalCoverage);

        return [
            'total_need' => $totalNeed,
            'total_coverage' => $totalCoverage,
            'total_coverage_used' => $totalCoverageUsed,
            'total_gap' => $totalGap,
            'gaps_by_category' => [
                'human_capital_gap' => $humanCapitalGap,
                'debt_protection_gap' => $debtGap,
                'final_expenses_gap' => $finalExpensesGap,
                'education_funding_gap' => $educationGap,
                'income_protection_gap' => $incomeProtectionGap,
                'disability_coverage_gap' => $disabilityGap,
                'sickness_illness_gap' => $sicknessGap,
            ],
            'coverage_allocated' => [
                'debt_covered' => $debtCovered,
                'human_capital_covered' => $humanCapitalCovered,
                'final_expenses_covered' => $finalExpensesCovered,
                'education_covered' => $educationCovered,
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
            (float) ($user->annual_other_income ?? 0)
        );

        $userGrossIncome = $userTaxCalculation['gross_income'];
        $userNetIncome = $userTaxCalculation['net_income'];

        // Calculate USER'S continuing income (rental + dividend) - these CONTINUE after death
        $userContinuingIncome = (float) ($user->annual_rental_income ?? 0)
                              + (float) ($user->annual_dividend_income ?? 0);

        // Track spouse income separately
        $spouseIncluded = false;
        $spouseGrossIncome = 0;
        $spouseNetIncome = 0;
        $spouseContinuingIncome = 0;
        $spousePermissionDenied = false;

        // Check for spouse and track spouse income separately
        if ($user->spouse_id && $user->marital_status === 'married') {
            // Check if spouse permission is accepted (either direction)
            if ($user->hasAcceptedSpousePermission()) {
                // Permission granted - track spouse income (REDUCES protection need)
                $spouse = $user->spouse;
                if ($spouse) {
                    // Get spouse income from spouse's user record
                    $spouseEmploymentIncome = (float) ($spouse->annual_employment_income ?? 0);
                    $spouseSelfEmploymentIncome = (float) ($spouse->annual_self_employment_income ?? 0);
                    $spouseRentalIncome = (float) ($spouse->annual_rental_income ?? 0);
                    $spouseDividendIncome = (float) ($spouse->annual_dividend_income ?? 0);
                    $spouseOtherIncome = (float) ($spouse->annual_other_income ?? 0);

                    // Spouse earned income (employment/self-employment)
                    $spouseTaxCalc = $this->taxCalculator->calculateNetIncome(
                        $spouseEmploymentIncome,
                        $spouseSelfEmploymentIncome,
                        0, // Rental income calculated separately
                        0, // Dividend income calculated separately
                        $spouseOtherIncome
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

        $age = $user->date_of_birth ?
               (int) $user->date_of_birth->diffInYears(now()) : 40;

        // Calculate income that STOPS on death: User's earned income
        $incomeThatStops = $userNetIncome;

        // Calculate income that CONTINUES after death:
        // 1. User's rental/dividend income
        // 2. Spouse's total income (earned + continuing)
        $incomeThatContinues = $userContinuingIncome
                             + $spouseNetIncome
                             + $spouseContinuingIncome;

        // Net income difference = What stops - What continues
        // This is what the family actually LOSES if user dies
        $netIncomeDifference = $incomeThatStops - $incomeThatContinues;

        // If spouse earns more or equal, no income protection needed
        // (family income would stay same or increase)
        $humanCapital = 0;
        if ($netIncomeDifference > 0) {
            $humanCapital = $this->calculateHumanCapital($netIncomeDifference);
        }

        $debtProtection = $this->calculateDebtProtectionNeed($profile);

        $educationFunding = $this->calculateEducationFunding(
            $profile->number_of_dependents,
            $profile->dependents_ages ?? []
        );

        $finalExpenses = $this->calculateFinalExpenses();

        // Total need = Human capital (income difference) + debt + education + final expenses
        $totalNeed = $humanCapital + $debtProtection + $educationFunding + $finalExpenses;

        // Income protection need = 60% of gross income (standard IP recommendation)
        $incomeProtectionNeed = $userGrossIncome * 0.6;

        return [
            'human_capital' => $humanCapital,
            'debt_protection' => $debtProtection,
            'education_funding' => $educationFunding,
            'final_expenses' => $finalExpenses,
            'income_protection_need' => $incomeProtectionNeed,
            'total_need' => $totalNeed,
            'gross_income' => $userGrossIncome,
            'net_income' => $userNetIncome,
            'continuing_income' => $userContinuingIncome,
            'income_that_stops' => $incomeThatStops,
            'income_that_continues' => $incomeThatContinues,
            'net_income_difference' => max(0, $netIncomeDifference),
            'income_tax' => $userTaxCalculation['income_tax'] ?? 0,
            'national_insurance' => $userTaxCalculation['national_insurance'] ?? 0,
            'spouse_included' => $spouseIncluded,
            'spouse_gross_income' => $spouseGrossIncome,
            'spouse_net_income' => $spouseNetIncome,
            'spouse_continuing_income' => $spouseContinuingIncome,
            'spouse_permission_denied' => $spousePermissionDenied,
        ];
    }
}
