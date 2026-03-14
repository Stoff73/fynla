<?php

declare(strict_types=1);

namespace App\Services\Investment\Recommendation;

use App\Constants\TaxDefaults;
use App\Models\Estate\Liability;
use App\Models\Investment\InvestmentAccount;
use App\Models\Investment\RiskProfile;
use App\Models\LifeEvent;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Risk\RiskPreferenceService;
use App\Services\TaxConfigService;
use App\Traits\ResolvesExpenditure;
use App\Traits\ResolvesIncome;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Assembles comprehensive user context for the investment recommendation pipeline.
 *
 * Two entry points:
 *  - build(User)            — fetches everything from scratch
 *  - buildFromExisting(...)  — derives context from data already assembled by InvestmentPlanService
 */
class UserContextBuilder
{
    use ResolvesExpenditure;
    use ResolvesIncome;

    public function __construct(
        private readonly TaxConfigService $taxConfig,
        private readonly RiskPreferenceService $riskPreferenceService,
        private readonly \App\Services\UKTaxCalculator $taxCalculator
    ) {}

    /**
     * Build context from scratch — queries the database directly.
     *
     * @return array Structured context consumed by every downstream pipeline phase
     */
    public function build(User $user): array
    {
        $grossIncome = $this->resolveGrossAnnualIncome($user);
        $netIncome = $grossIncome > 0 ? $this->resolveNetAnnualIncome($user) : 0.0;
        $expenditure = $this->resolveMonthlyExpenditure($user);
        $monthlyExpenditure = $expenditure['amount'];
        $annualExpenditure = $monthlyExpenditure * 12;
        $disposableIncome = max(0, $netIncome - $annualExpenditure);

        $riskProfile = RiskProfile::where('user_id', $user->id)->first();
        $riskLevel = $riskProfile?->risk_level ?? 'medium';

        $taxBand = $this->determineTaxBand($grossIncome);

        // ISA usage — combine savings + investment ISAs
        $taxYear = $this->taxConfig->getTaxYear();
        $isaAllowance = $this->taxConfig->getISAAllowances()['annual_allowance'] ?? TaxDefaults::ISA_ALLOWANCE;
        $investmentIsaUsed = InvestmentAccount::where('user_id', $user->id)
            ->where('account_type', 'isa')
            ->sum('isa_subscription_current_year');
        $savingsIsaUsed = SavingsAccount::where('user_id', $user->id)
            ->whereIn('account_type', ['isa', 'cash_isa'])
            ->where('isa_subscription_year', $taxYear)
            ->sum('isa_subscription_amount');
        $isaUsed = (float) $investmentIsaUsed + (float) $savingsIsaUsed;
        $isaRemaining = max(0, $isaAllowance - $isaUsed);

        // Pension allowance
        $pensionAllowances = $this->taxConfig->getPensionAllowances();
        $annualAllowance = $pensionAllowances['annual_allowance'] ?? TaxDefaults::PENSION_ANNUAL_ALLOWANCE;
        $pensionContributionsThisYear = $this->calculatePensionContributions($user);
        $pensionRemaining = max(0, $annualAllowance - $pensionContributionsThisYear);

        // CGT
        $cgtConfig = $this->taxConfig->getCapitalGainsTax();
        $cgtAnnualExempt = $cgtConfig['annual_exempt_amount'] ?? TaxDefaults::CGT_ANNUAL_EXEMPT;

        // Personal Savings Allowance
        $psa = $this->taxConfig->getPersonalSavingsAllowance($taxBand);

        // Emergency fund
        $savingsAccounts = SavingsAccount::forUserOrJoint($user->id)->get();
        $totalSavings = $savingsAccounts->sum('current_balance');
        $emergencyTarget = $monthlyExpenditure * 6;
        $emergencyShortfall = max(0, $emergencyTarget - $totalSavings);
        $emergencyRunway = $monthlyExpenditure > 0 ? $totalSavings / $monthlyExpenditure : 0;

        // Debts
        $debts = $this->assembleDebts($user);

        // Investment accounts and portfolio
        $investmentAccounts = InvestmentAccount::forUserOrJoint($user->id)->with('holdings')->get();
        $portfolioValue = $investmentAccounts->sum('current_value');

        // Age calculations
        $age = $user->date_of_birth ? (int) Carbon::parse($user->date_of_birth)->age : null;
        $retirementAge = $user->target_retirement_age ? (int) $user->target_retirement_age : null;
        $yearsToRetirement = ($age !== null && $retirementAge !== null && $retirementAge > $age)
            ? $retirementAge - $age
            : null;

        // Spouse context
        $spouseContext = $this->buildSpouseContext($user);

        // Life events
        $lifeEvents = LifeEvent::where(function ($q) use ($user) {
            $q->where('user_id', $user->id)
                ->orWhere('joint_owner_id', $user->id);
        })
            ->active()
            ->orderBy('expected_date')
            ->get()
            ->toArray();

        // Goals
        $goals = $user->goals()
            ->where('status', '!=', 'completed')
            ->get()
            ->toArray();

        // DC Pensions for employer match
        $dcPensions = $user->dcPensions()->get();

        return [
            'user_id' => $user->id,
            'personal' => [
                'age' => $age,
                'gender' => $user->gender ?? null,
                'marital_status' => $user->marital_status ?? null,
                'employment_status' => $user->employment_status ?? null,
                'retirement_age' => $retirementAge,
                'years_to_retirement' => $yearsToRetirement,
                'date_of_birth' => $user->date_of_birth?->toDateString(),
            ],
            'financial' => [
                'gross_income' => round($grossIncome, 2),
                'net_income' => round($netIncome, 2),
                'monthly_expenditure' => round($monthlyExpenditure, 2),
                'annual_expenditure' => round($annualExpenditure, 2),
                'disposable_income' => round($disposableIncome, 2),
                'monthly_disposable' => round($disposableIncome / 12, 2),
                'tax_band' => $taxBand,
            ],
            'risk' => [
                'risk_level' => $riskLevel,
                'risk_tolerance' => $riskProfile?->risk_tolerance ?? null,
                'is_self_assessed' => $riskProfile?->is_self_assessed ?? false,
            ],
            'debt' => $debts,
            'emergency_fund' => [
                'total_savings' => round((float) $totalSavings, 2),
                'runway_months' => round($emergencyRunway, 1),
                'target_months' => 6,
                'target_amount' => round($emergencyTarget, 2),
                'shortfall' => round($emergencyShortfall, 2),
            ],
            'allowances' => [
                'isa_annual' => $isaAllowance,
                'isa_used' => round($isaUsed, 2),
                'isa_remaining' => round($isaRemaining, 2),
                'lisa_annual' => $this->taxConfig->getISAAllowances()['lifetime_isa'] ?? TaxDefaults::LISA_ALLOWANCE,
                'pension_annual_allowance' => $annualAllowance,
                'pension_contributions_this_year' => round($pensionContributionsThisYear, 2),
                'pension_remaining' => round($pensionRemaining, 2),
                'cgt_annual_exempt' => $cgtAnnualExempt,
                'psa' => $psa,
            ],
            'spouse' => $spouseContext,
            'portfolio' => [
                'accounts' => $investmentAccounts->map(fn ($a) => [
                    'id' => $a->id,
                    'name' => $a->account_name,
                    'type' => $a->account_type,
                    'provider' => $a->provider,
                    'value' => round((float) $a->current_value, 2),
                    'holdings_count' => $a->holdings->count(),
                ])->toArray(),
                'total_value' => round((float) $portfolioValue, 2),
            ],
            'pensions' => [
                'dc_pensions' => $dcPensions->map(fn ($p) => [
                    'id' => $p->id,
                    'scheme_name' => $p->scheme_name,
                    'scheme_type' => $p->scheme_type,
                    'current_fund_value' => round((float) $p->current_fund_value, 2),
                    'employer_contribution_percent' => (float) ($p->employer_contribution_percent ?? 0),
                    'employee_contribution_percent' => (float) ($p->employee_contribution_percent ?? 0),
                    'employer_matching_limit' => (float) ($p->employer_matching_limit ?? 0),
                    'has_flexibly_accessed' => $p->has_flexibly_accessed ?? false,
                ])->toArray(),
            ],
            'life_events' => $lifeEvents,
            'goals' => $goals,
        ];
    }

    /**
     * Build context from data already assembled by InvestmentPlanService.
     *
     * Preferred path — avoids duplicate database queries.
     */
    public function buildFromExisting(
        array $investmentAnalysis,
        array $savingsAnalysis,
        Collection $accounts,
        User $user
    ): array {
        $grossIncome = $this->resolveGrossAnnualIncome($user);
        $netIncome = $grossIncome > 0 ? $this->resolveNetAnnualIncome($user) : 0.0;
        $expenditure = $this->resolveMonthlyExpenditure($user);
        $monthlyExpenditure = $expenditure['amount'];
        $annualExpenditure = $monthlyExpenditure * 12;
        $disposableIncome = max(0, $netIncome - $annualExpenditure);

        $riskProfile = RiskProfile::where('user_id', $user->id)->first();
        $riskLevel = $riskProfile?->risk_level ?? 'medium';
        $taxBand = $this->determineTaxBand($grossIncome);

        // ISA remaining — from investmentAnalysis which already cross-checks savings ISAs
        $taxWrappers = $investmentAnalysis['tax_wrappers'] ?? [];
        $isaAllowance = $taxWrappers['isa_allowance']
            ?? $this->taxConfig->getISAAllowances()['annual_allowance']
            ?? TaxDefaults::ISA_ALLOWANCE;
        $isaUsed = (float) ($taxWrappers['isa_used_this_year'] ?? 0);
        $isaRemaining = (float) ($taxWrappers['isa_remaining'] ?? max(0, $isaAllowance - $isaUsed));

        // Pension
        $pensionAllowances = $this->taxConfig->getPensionAllowances();
        $annualAllowance = $pensionAllowances['annual_allowance'] ?? TaxDefaults::PENSION_ANNUAL_ALLOWANCE;
        $pensionContributionsThisYear = $this->calculatePensionContributions($user);
        $pensionRemaining = max(0, $annualAllowance - $pensionContributionsThisYear);

        // CGT and PSA
        $cgtConfig = $this->taxConfig->getCapitalGainsTax();
        $cgtAnnualExempt = $cgtConfig['annual_exempt_amount'] ?? TaxDefaults::CGT_ANNUAL_EXEMPT;
        $psa = $this->taxConfig->getPersonalSavingsAllowance($taxBand);

        // Emergency fund from savings analysis
        $emergencyFund = $savingsAnalysis['emergency_fund'] ?? [];
        $totalSavings = $savingsAnalysis['summary']['total_savings'] ?? 0;
        $emergencyRunway = $emergencyFund['runway_months'] ?? 0;
        $emergencyTarget = $monthlyExpenditure * 6;
        $emergencyShortfall = max(0, $emergencyTarget - $totalSavings);

        // Debts
        $debts = $this->assembleDebts($user);

        // Age calculations
        $age = $user->date_of_birth ? (int) Carbon::parse($user->date_of_birth)->age : null;
        $retirementAge = $user->target_retirement_age ? (int) $user->target_retirement_age : null;
        $yearsToRetirement = ($age !== null && $retirementAge !== null && $retirementAge > $age)
            ? $retirementAge - $age
            : null;

        // Spouse context
        $spouseContext = $this->buildSpouseContext($user);

        // Portfolio from pre-computed accounts
        $portfolioValue = $accounts->sum('current_value');

        // Life events and goals
        $lifeEvents = LifeEvent::where(function ($q) use ($user) {
            $q->where('user_id', $user->id)
                ->orWhere('joint_owner_id', $user->id);
        })
            ->active()
            ->orderBy('expected_date')
            ->get()
            ->toArray();

        $goals = $user->goals()
            ->where('status', '!=', 'completed')
            ->get()
            ->toArray();

        $dcPensions = $user->dcPensions()->get();

        return [
            'user_id' => $user->id,
            'personal' => [
                'age' => $age,
                'gender' => $user->gender ?? null,
                'marital_status' => $user->marital_status ?? null,
                'employment_status' => $user->employment_status ?? null,
                'retirement_age' => $retirementAge,
                'years_to_retirement' => $yearsToRetirement,
                'date_of_birth' => $user->date_of_birth?->toDateString(),
            ],
            'financial' => [
                'gross_income' => round($grossIncome, 2),
                'net_income' => round($netIncome, 2),
                'monthly_expenditure' => round($monthlyExpenditure, 2),
                'annual_expenditure' => round($annualExpenditure, 2),
                'disposable_income' => round($disposableIncome, 2),
                'monthly_disposable' => round($disposableIncome / 12, 2),
                'tax_band' => $taxBand,
            ],
            'risk' => [
                'risk_level' => $riskLevel,
                'risk_tolerance' => $riskProfile?->risk_tolerance ?? null,
                'is_self_assessed' => $riskProfile?->is_self_assessed ?? false,
            ],
            'debt' => $debts,
            'emergency_fund' => [
                'total_savings' => round((float) $totalSavings, 2),
                'runway_months' => round((float) $emergencyRunway, 1),
                'target_months' => 6,
                'target_amount' => round($emergencyTarget, 2),
                'shortfall' => round($emergencyShortfall, 2),
            ],
            'allowances' => [
                'isa_annual' => $isaAllowance,
                'isa_used' => round($isaUsed, 2),
                'isa_remaining' => round($isaRemaining, 2),
                'lisa_annual' => $this->taxConfig->getISAAllowances()['lifetime_isa'] ?? TaxDefaults::LISA_ALLOWANCE,
                'pension_annual_allowance' => $annualAllowance,
                'pension_contributions_this_year' => round($pensionContributionsThisYear, 2),
                'pension_remaining' => round($pensionRemaining, 2),
                'cgt_annual_exempt' => $cgtAnnualExempt,
                'psa' => $psa,
            ],
            'spouse' => $spouseContext,
            'portfolio' => [
                'accounts' => $accounts->map(fn ($a) => [
                    'id' => $a->id,
                    'name' => $a->account_name,
                    'type' => $a->account_type,
                    'provider' => $a->provider,
                    'value' => round((float) $a->current_value, 2),
                    'holdings_count' => $a->holdings->count(),
                ])->toArray(),
                'total_value' => round((float) $portfolioValue, 2),
            ],
            'pensions' => [
                'dc_pensions' => $dcPensions->map(fn ($p) => [
                    'id' => $p->id,
                    'scheme_name' => $p->scheme_name,
                    'scheme_type' => $p->scheme_type,
                    'current_fund_value' => round((float) $p->current_fund_value, 2),
                    'employer_contribution_percent' => (float) ($p->employer_contribution_percent ?? 0),
                    'employee_contribution_percent' => (float) ($p->employee_contribution_percent ?? 0),
                    'employer_matching_limit' => (float) ($p->employer_matching_limit ?? 0),
                    'has_flexibly_accessed' => $p->has_flexibly_accessed ?? false,
                ])->toArray(),
            ],
            'life_events' => $lifeEvents,
            'goals' => $goals,
        ];
    }

    // ──────────────────────────────────────────────
    // Private helpers
    // ──────────────────────────────────────────────

    /**
     * Determine the user's income tax band from gross income.
     */
    private function determineTaxBand(float $grossIncome): string
    {
        $personalAllowance = TaxDefaults::PERSONAL_ALLOWANCE;

        if ($grossIncome <= $personalAllowance) {
            return 'non_taxpayer';
        }

        if ($grossIncome <= $personalAllowance + TaxDefaults::BASIC_RATE_BAND) {
            return 'basic';
        }

        if ($grossIncome <= TaxDefaults::ADDITIONAL_RATE_THRESHOLD) {
            return 'higher';
        }

        return 'additional';
    }

    /**
     * Assemble debt summary from user liabilities (excluding mortgages and student loans).
     */
    private function assembleDebts(User $user): array
    {
        $liabilities = Liability::where(function ($q) use ($user) {
            $q->where('user_id', $user->id)
                ->orWhere('joint_owner_id', $user->id);
        })
            ->whereNotIn('liability_type', ['mortgage', 'student_loan'])
            ->get();

        $highInterest = $liabilities->filter(fn ($l) => (float) $l->interest_rate > 15);
        $mediumInterest = $liabilities->filter(fn ($l) => (float) $l->interest_rate > 5 && (float) $l->interest_rate <= 15);

        return [
            'total_balance' => round($liabilities->sum('current_balance'), 2),
            'total_monthly_payment' => round($liabilities->sum('monthly_payment'), 2),
            'high_interest' => [
                'count' => $highInterest->count(),
                'total_balance' => round($highInterest->sum('current_balance'), 2),
                'total_monthly_payment' => round($highInterest->sum('monthly_payment'), 2),
            ],
            'medium_interest' => [
                'count' => $mediumInterest->count(),
                'total_balance' => round($mediumInterest->sum('current_balance'), 2),
                'total_monthly_payment' => round($mediumInterest->sum('monthly_payment'), 2),
            ],
            'items' => $liabilities->map(fn ($l) => [
                'id' => $l->id,
                'name' => $l->liability_name,
                'type' => $l->liability_type,
                'balance' => round((float) $l->current_balance, 2),
                'interest_rate' => (float) $l->interest_rate,
                'monthly_payment' => round((float) $l->monthly_payment, 2),
            ])->toArray(),
        ];
    }

    /**
     * Calculate total pension contributions for the current tax year.
     */
    private function calculatePensionContributions(User $user): float
    {
        $dcPensions = $user->dcPensions()->get();

        return $dcPensions->sum(function ($pension) {
            $annualSalary = (float) ($pension->annual_salary ?? 0);
            $employeePercent = (float) ($pension->employee_contribution_percent ?? 0);
            $employerPercent = (float) ($pension->employer_contribution_percent ?? 0);

            return ($annualSalary * ($employeePercent + $employerPercent) / 100)
                + (float) ($pension->lump_sum_contribution ?? 0);
        });
    }

    /**
     * Build spouse context if the user is married/civil partnership and has a linked spouse.
     */
    private function buildSpouseContext(User $user): ?array
    {
        if (! in_array($user->marital_status, ['married', 'civil_partnership'])) {
            return null;
        }

        if (! $user->spouse_id) {
            return null;
        }

        $spouse = User::find($user->spouse_id);
        if (! $spouse) {
            return null;
        }

        $spouseGross = $this->resolveGrossAnnualIncome($spouse);
        $spouseTaxBand = $this->determineTaxBand($spouseGross);

        // Spouse ISA usage
        $taxYear = $this->taxConfig->getTaxYear();
        $isaAllowance = $this->taxConfig->getISAAllowances()['annual_allowance'] ?? TaxDefaults::ISA_ALLOWANCE;
        $spouseInvestmentIsa = InvestmentAccount::where('user_id', $spouse->id)
            ->where('account_type', 'isa')
            ->sum('isa_subscription_current_year');
        $spouseSavingsIsa = SavingsAccount::where('user_id', $spouse->id)
            ->whereIn('account_type', ['isa', 'cash_isa'])
            ->where('isa_subscription_year', $taxYear)
            ->sum('isa_subscription_amount');
        $spouseIsaUsed = (float) $spouseInvestmentIsa + (float) $spouseSavingsIsa;
        $spouseIsaRemaining = max(0, $isaAllowance - $spouseIsaUsed);

        // Spouse pension
        $spousePensionAllowance = $this->taxConfig->getPensionAllowances()['annual_allowance'] ?? TaxDefaults::PENSION_ANNUAL_ALLOWANCE;
        $spousePensionContributions = $this->calculatePensionContributions($spouse);
        $spousePensionRemaining = max(0, $spousePensionAllowance - $spousePensionContributions);

        $spousePsa = $this->taxConfig->getPersonalSavingsAllowance($spouseTaxBand);

        return [
            'user_id' => $spouse->id,
            'name' => trim(($spouse->first_name ?? '').' '.($spouse->surname ?? '')),
            'age' => $spouse->date_of_birth ? (int) Carbon::parse($spouse->date_of_birth)->age : null,
            'gross_income' => round($spouseGross, 2),
            'tax_band' => $spouseTaxBand,
            'isa_remaining' => round($spouseIsaRemaining, 2),
            'pension_remaining' => round($spousePensionRemaining, 2),
            'psa' => $spousePsa,
            'employment_status' => $spouse->employment_status ?? null,
        ];
    }

    /**
     * Provide the UKTaxCalculator for the ResolvesIncome trait.
     */
    protected function getIncomeTaxCalculator(): \App\Services\UKTaxCalculator
    {
        return $this->taxCalculator;
    }
}
