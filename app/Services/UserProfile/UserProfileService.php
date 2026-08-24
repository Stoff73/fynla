<?php

declare(strict_types=1);

namespace App\Services\UserProfile;

use App\Models\CriticalIllnessPolicy;
use App\Models\DisabilityPolicy;
use App\Models\Estate\Liability;
use App\Models\FamilyMember;
use App\Models\IncomeProtectionPolicy;
use App\Models\Investment\InvestmentAccount;
use App\Models\LifeInsurancePolicy;
use App\Models\SavingsAccount;
use App\Models\SicknessIllnessPolicy;
use App\Models\TaxStrategyHouseholdInput;
use App\Models\User;
use App\Services\Benefits\ChildBenefitService;
use App\Services\Estate\WillAnalysisService;
use App\Services\Gamification\PointsService;
use App\Services\Property\PropertyService;
use App\Services\Shared\CrossModuleAssetAggregator;
use App\Services\Stores\MortgageStore;
use App\Services\Stores\PensionStore;
use App\Services\Stores\PropertyStore;
use App\Services\Tax\IncomeDefinitionsService;
use App\Services\UKTaxCalculator;
use App\Traits\CalculatesOwnershipShare;
use App\Traits\ResolvesIncome;
use Carbon\Carbon;

class UserProfileService
{
    use CalculatesOwnershipShare;
    use ResolvesIncome;

    public function __construct(
        private readonly CrossModuleAssetAggregator $assetAggregator,
        private readonly UKTaxCalculator $taxCalculator,
        private readonly ChildBenefitService $childBenefitService,
        private readonly PropertyStore $propertyStore,
        private readonly MortgageStore $mortgageStore,
        private readonly IncomeDefinitionsService $incomeDefinitions,
        private readonly WillAnalysisService $willAnalysis,
    ) {}

    /**
     * Get the complete profile for a user including all related data
     */
    public function getCompleteProfile(User $user): array
    {
        // Load all relationships
        $user->load([
            'household',
            'spouse',
            'familyMembers',
            'properties',
            'mortgages',
            'liabilities',
            'businessInterests',
            'chattels',
            'cashAccounts',
            'investmentAccounts.holdings',
            'dcPensions',
            'dbPensions',
            'statePension',
        ]);

        // Calculate asset summary
        $assetsSummary = $this->calculateAssetsSummary($user);

        // Calculate liabilities summary
        $liabilitiesSummary = $this->calculateLiabilitiesSummary($user);

        return [
            'personal_info' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'date_of_birth' => $user->date_of_birth?->format('Y-m-d'),
                'age' => $user->date_of_birth?->age,
                'gender' => $user->gender,
                'marital_status' => $user->marital_status,
                'national_insurance_number' => $user->national_insurance_number ? '***'.substr($user->national_insurance_number, -4) : null,
                'address' => [
                    'line_1' => $user->address_line_1,
                    'line_2' => $user->address_line_2,
                    'city' => $user->city,
                    'county' => $user->county,
                    'postcode' => $user->postcode,
                ],
                'phone' => $user->phone,
                'education_level' => $user->education_level,
                // `good_health` / `smoker` are not columns on `users` and never
                // were, so both keys published null on every request. The real
                // columns are these two (W-0006).
                'health_status' => $user->health_status,
                'smoking_status' => $user->smoking_status,
                'life_expectancy_override' => $user->life_expectancy_override,
            ],
            'household' => $user->household,
            'spouse' => $user->spouse ? [
                'id' => $user->spouse->id,
                'name' => $user->spouse->name,
                'email' => $user->spouse->email,
            ] : null,
            'income_occupation' => $this->buildIncomeOccupation($user),
            // Flat per-source income for the user and (when linked) the spouse —
            // consumed by the /m Income screen. Additive; never read by existing
            // consumers of income_occupation.
            'income_summary' => [
                'user' => $this->incomeSources($user, 'user'),
                'spouse' => $this->spouseIncomeSources($user),
            ],
            'expenditure' => [
                'monthly_expenditure' => $user->monthly_expenditure,
                'annual_expenditure' => $user->annual_expenditure,
                'categories' => [
                    'food_groceries' => $user->food_groceries,
                    'transport_fuel' => $user->transport_fuel,
                    'clothing_personal_care' => $user->clothing_personal_care,
                    'entertainment_dining' => $user->entertainment_dining,
                    'childcare' => $user->childcare,
                    'charitable_donations' => $user->charitable_donations,
                    'other_expenditure' => $user->other_expenditure,
                ],
                'presentation' => $this->expenditurePresentation($user),
            ],
            'family_members' => $this->getFamilyMembersWithSharing($user),
            // W-0132 — what this person is actually leaving to charity, read from
            // their will rather than from the `users.charitable_bequest` toggle the
            // Family settings card used to display. Additive, and the same shape the
            // estate module's own answer comes from.
            'charitable_bequests' => $this->willAnalysis->charitableBequestSummary($user),
            'domicile_info' => $user->getDomicileInfo(),
            'assets_summary' => $assetsSummary,
            'liabilities_summary' => $liabilitiesSummary,
            'net_worth' => $assetsSummary['total'] - $liabilitiesSummary['total'],
        ];
    }

    /**
     * Update personal information
     */
    public function updatePersonalInfo(User $user, array $data): User
    {
        // Ensure annual_expenditure is set when monthly_expenditure is provided
        if (isset($data['monthly_expenditure']) && ! isset($data['annual_expenditure'])) {
            $data['annual_expenditure'] = (float) $data['monthly_expenditure'] * 12;
        }

        $user->update($data);

        return $user->fresh();
    }

    /**
     * Update income and occupation information
     */
    public function updateIncomeOccupation(User $user, array $data): User
    {
        // Calculate annual rental income from properties
        $rentalBreakdown = $this->calculateAnnualRentalIncome($user);

        // Override the annual_rental_income with calculated total
        $data['annual_rental_income'] = $rentalBreakdown['total'];

        $hadIncomeBefore = $this->totalGrossAnnualIncome($user) > 0;

        $user->update($data);

        $fresh = $user->fresh();

        // First-capture gamification award: income transitioned empty -> set.
        // Dedup on the key guarantees the bonus pays exactly once. Never throws.
        if (! $hadIncomeBefore && $this->totalGrossAnnualIncome($fresh) > 0) {
            app(PointsService::class)->award(
                $fresh,
                'data',
                'data:income:first',
                (int) config('gamification.points.data_first_in_category'),
                ['category' => 'income'],
            );
        }

        return $fresh;
    }

    /**
     * Sum of all annual gross income sources on a user.
     *
     * Public so the gamification backfill can detect "income is set" with the
     * exact same logic the live first-capture award uses (dedup-key parity).
     */
    public function totalGrossAnnualIncome(User $user): float
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
     * Update domicile information and calculate deemed domicile status
     */
    public function updateDomicileInfo(User $user, array $data): User
    {
        // Update the basic fields
        $user->update([
            'domicile_status' => $data['domicile_status'],
            'country_of_birth' => $data['country_of_birth'],
            'uk_arrival_date' => $data['uk_arrival_date'] ?? null,
        ]);

        // Refresh to get updated values
        $user = $user->fresh();

        // Calculate and update years_uk_resident
        $yearsResident = $user->calculateYearsUKResident();
        if ($yearsResident !== null) {
            $user->years_uk_resident = $yearsResident;
        }

        // Calculate and set deemed_domicile_date if applicable
        if ($user->isDeemedDomiciled() && ! $user->deemed_domicile_date && $user->uk_arrival_date) {
            // Calculate the date when they became deemed domiciled (15 years after arrival)
            $arrivalDate = Carbon::parse($user->uk_arrival_date);
            $user->deemed_domicile_date = $arrivalDate->copy()->addYears(15);
        }

        // If they are no longer deemed domiciled (e.g., status changed to uk_domiciled), clear the date
        if (! $user->isDeemedDomiciled() && $user->domicile_status !== 'uk_domiciled') {
            $user->deemed_domicile_date = null;
        }

        $user->save();

        return $user->fresh();
    }

    /**
     * The user's annual rental profit, Section 24 credit and per-property
     * composition, from the one home for that figure (W-0175).
     *
     * @see PropertyService::annualRentalTaxPosition()
     */
    private function calculateAnnualRentalIncome(User $user): array
    {
        return app(PropertyService::class)->annualRentalTaxPosition($user);
    }

    /**
     * Get annual expenditure from user profile.
     * Calculates: manual expenditure + financial commitments (matches Expenditure tab display).
     */
    private function calculateAnnualExpenditure(User $user): float
    {
        $breakdown = $this->getExpenditureBreakdown($user);

        return $breakdown['annual'];
    }

    /**
     * Get expenditure breakdown including financial commitments.
     * Uses categories sum when entry_mode is 'category', otherwise uses monthly_expenditure.
     */
    private function getExpenditureBreakdown(User $user): array
    {
        // Calculate manual expenditure based on entry mode
        if ($user->expenditure_entry_mode === 'category') {
            // Sum all category fields (same as Expenditure tab's totalMonthlyExpenditure)
            $monthlyManual = (float) ($user->food_groceries ?? 0)
                + (float) ($user->transport_fuel ?? 0)
                + (float) ($user->healthcare_medical ?? 0)
                + (float) ($user->insurance ?? 0)
                + (float) ($user->mobile_phones ?? 0)
                + (float) ($user->internet_tv ?? 0)
                + (float) ($user->subscriptions ?? 0)
                + (float) ($user->clothing_personal_care ?? 0)
                + (float) ($user->entertainment_dining ?? 0)
                + (float) ($user->holidays_travel ?? 0)
                + (float) ($user->pets ?? 0)
                + (float) ($user->childcare ?? 0)
                + (float) ($user->school_fees ?? 0)
                + (float) ($user->school_lunches ?? 0)
                + (float) ($user->school_extras ?? 0)
                + (float) ($user->university_fees ?? 0)
                + (float) ($user->children_activities ?? 0)
                + (float) ($user->gifts_charity ?? 0)
                + (float) ($user->regular_savings ?? 0)
                + (float) ($user->other_expenditure ?? 0);
        } else {
            // Simple mode - use the monthly_expenditure field
            $monthlyManual = (float) ($user->monthly_expenditure ?? 0);
        }

        $commitments = $this->getFinancialCommitments($user);
        $monthlyCommitments = (float) ($commitments['totals']['total'] ?? 0);
        $monthlyTotal = $monthlyManual + $monthlyCommitments;

        return [
            'monthly_manual' => round($monthlyManual, 2),
            'monthly_commitments' => round($monthlyCommitments, 2),
            'monthly' => round($monthlyTotal, 2),
            'annual' => round($monthlyTotal * 12, 2),
        ];
    }

    /**
     * Calculate annual pension income for the user.
     * Includes DB pensions (if in payment) and state pension (if receiving).
     *
     * The docblock above is unchanged; it always described the intended rule. What
     * changed is that the code now performs it — see ResolvesIncome, which is the
     * one implementation the three copies of this function collapsed into (W-0036).
     */
    private function calculateAnnualPensionIncome(User $user): float
    {
        return $this->resolvePensionIncomeInPayment($user);
    }

    /**
     * Get the primary trust type for tax calculation purposes.
     * If user has multiple trusts, returns the type of the first active trust.
     */
    private function getPrimaryTrustType(User $user): ?string
    {
        // Load trusts if not already loaded
        if (! $user->relationLoaded('trusts')) {
            $user->load('trusts');
        }

        // Get the first active trust
        $primaryTrust = $user->trusts
            ->where('is_active', true)
            ->first();

        return $primaryTrust?->trust_type;
    }

    /**
     * Calculate annual employee pension contributions from occupational pensions.
     * These are contributions from salary (workplace pensions) that are deducted before tax.
     */
    private function calculateAnnualPensionContributions(User $user): float
    {
        $totalContributions = 0.0;

        // Sum employee contributions from occupational/workplace pensions
        foreach ($user->dcPensions as $pension) {
            // Only include workplace/occupational pensions (not SIPPs which are personal contributions)
            if (in_array($pension->scheme_type, ['workplace', 'occupational', 'auto_enrolment'])) {
                // Calculate from percentage if available
                if ($pension->employee_contribution_percent && $pension->annual_salary) {
                    $monthlyContribution = ($pension->annual_salary * $pension->employee_contribution_percent / 100) / 12;
                    $totalContributions += $monthlyContribution * 12;
                }
            }
        }

        return $totalContributions;
    }

    /**
     * Flat per-source annual income for one person (user or spouse), for the /m
     * Income screen. Raw earned/investment columns only, symmetric across user and
     * spouse; rental is property-derived and shown on the property/net-worth screens.
     *
     * @return array{employment: float, self_employment: float, dividend: float, interest: float, other: float, total: float}
     */
    private function incomeSources(User $person, string $ownership): array
    {
        $definition = $this->incomeDefinitions->calculate($person->id);
        $components = $definition['components'];
        $sourceDefinitions = [
            'employment' => ['Employment', 'Taxable earned income'],
            'self_employment' => ['Self-employment', 'Taxable earned income'],
            'rental' => ['Rental income', 'Taxable property income'],
            'dividend' => ['Dividends', 'Dividend income'],
            'interest' => ['Interest', 'Savings income'],
            'other' => ['Other', 'Other taxable income'],
            'trust' => ['Trust income', 'Trust income'],
            'pension_income' => ['Pension income', 'Taxable pension income'],
        ];
        $sources = $components;
        $sources['total'] = (float) $definition['total_income'];
        // Identifying detail so the verify screen shows WHAT the user entered
        // (employer + role), not just the employment amount.
        $sources['employer'] = $person->employer ?: null;
        $sources['occupation'] = $person->occupation ?: null;
        $sources['sources'] = collect($sourceDefinitions)
            ->map(function (array $labels, string $key) use ($components, $ownership, $person): array {
                $detail = null;
                if ($key === 'employment') {
                    $detail = collect([$person->employer, $person->occupation])
                        ->filter(fn ($value): bool => is_string($value) && trim($value) !== '')
                        ->implode(' · ') ?: null;
                }

                return [
                    'key' => $key,
                    'label' => $labels[0],
                    'amount' => (float) ($components[$key] ?? 0),
                    'frequency' => 'annual',
                    'ownership' => $ownership,
                    'ownership_label' => $ownership === 'spouse' ? 'Your spouse' : 'You',
                    'detail' => $detail,
                    'tax_position' => $labels[1],
                ];
            })
            ->filter(fn (array $source): bool => $source['amount'] > 0)
            ->values()
            ->all();
        $allowances = $definition['adjusted_allowances'];
        $sources['tax_position'] = [
            'total_income' => (float) $definition['total_income'],
            'adjusted_net_income' => (float) $definition['adjusted_net_income'],
            'personal_allowance' => (float) $allowances['personal_allowance'],
            'personal_allowance_label' => $allowances['personal_allowance_tapered']
                ? 'Tapered personal allowance'
                : 'Standard personal allowance',
            'pension_annual_allowance' => (float) $allowances['pension_annual_allowance'],
            'pension_annual_allowance_label' => $allowances['pension_aa_tapered']
                ? 'Tapered pension annual allowance'
                : 'Standard pension annual allowance',
        ];

        return $sources;
    }

    /**
     * Spouse income for the /m Income screen's spouse-verify view.
     *
     * A linked spouse account returns its own income sources. Otherwise the
     * SaveTax campaign captures the spouse's income on the household input (no
     * account is linked during onboarding — linking is surfaced later via the
     * dashboard), so surface that figure as the spouse's employment income.
     * Returns null when there is no spouse income to show.
     *
     * @return array{employment: float, self_employment: float, dividend: float, interest: float, other: float, total: float}|null
     */
    private function spouseIncomeSources(User $user): ?array
    {
        if ($user->spouse) {
            return $this->incomeSources($user->spouse, 'spouse');
        }

        $spouseIncome = (float) (TaxStrategyHouseholdInput::where('user_id', $user->id)
            ->value('spouse_annual_income') ?? 0);
        if ($spouseIncome <= 0) {
            return null;
        }

        return [
            'employment' => $spouseIncome,
            'self_employment' => 0.0,
            'dividend' => 0.0,
            'interest' => 0.0,
            'other' => 0.0,
            'total' => $spouseIncome,
            'employer' => null,
            'occupation' => null,
            'sources' => [[
                'key' => 'employment',
                'label' => 'Employment',
                'amount' => $spouseIncome,
                'frequency' => 'annual',
                'ownership' => 'spouse',
                'ownership_label' => 'Your spouse',
                'detail' => null,
                'tax_position' => 'Estimated earned income',
            ]],
            'tax_position' => [
                'total_income' => $spouseIncome,
                'adjusted_net_income' => null,
                'personal_allowance' => null,
                'personal_allowance_label' => 'Link a spouse profile for a calculated tax position',
                'pension_annual_allowance' => null,
                'pension_annual_allowance_label' => 'Link a spouse profile for a calculated tax position',
            ],
        ];
    }

    /**
     * Server-owned presentation contract consumed without client arithmetic.
     */
    private function expenditurePresentation(User $user): array
    {
        $breakdown = $this->getExpenditureBreakdown($user);
        $isCategory = $user->expenditure_entry_mode === 'category';

        // W-0140. The composed figure is entries PLUS commitments, and Disposable
        // Income depends on it staying that way. What was wrong is that a user who
        // has recorded nothing was still described as having entered a total: the
        // basis named a component that does not exist for them.
        $hasRecordedExpenditure = $breakdown['monthly_manual'] > 0;

        return [
            'entry_mode' => $isCategory ? 'category' : 'summary',
            'entry_mode_label' => $isCategory ? 'Category detail' : 'Monthly summary',
            'active_monthly_total' => $breakdown['monthly'],
            'active_annual_total' => $breakdown['annual'],
            'manual_monthly_total' => $breakdown['monthly_manual'],
            'commitments_monthly_total' => $breakdown['monthly_commitments'],
            'manual_annual_total' => round($breakdown['monthly_manual'] * 12, 2),
            'commitments_annual_total' => round($breakdown['monthly_commitments'] * 12, 2),
            'has_recorded_expenditure' => $hasRecordedExpenditure,
            'total_basis' => match (true) {
                ! $hasRecordedExpenditure => 'Financial commitments only — no expenditure recorded',
                $isCategory => 'Category entries plus financial commitments',
                default => 'Monthly summary plus financial commitments',
            },
            'detail_available' => $isCategory,
            'reconciles' => true,
            'summary_only_reason' => match (true) {
                $isCategory => null,
                ! $hasRecordedExpenditure => 'No expenditure has been recorded. Add your spending to improve your insights.',
                default => 'Only a monthly summary has been entered. Add category details to improve your insights.',
            },
        ];
    }

    /**
     * Build income and occupation section with detailed tax breakdown
     */
    private function buildIncomeOccupation(User $user): array
    {
        $rentalBreakdown = $this->calculateAnnualRentalIncome($user);
        $rentalIncome = $rentalBreakdown['total'];
        $section24Credit = $rentalBreakdown['section_24_credit'];
        $pensionIncome = $this->calculateAnnualPensionIncome($user);
        $pensionContributions = $this->calculateAnnualPensionContributions($user);

        $employmentIncome = (float) ($user->annual_employment_income ?? 0);
        $selfEmploymentIncome = (float) ($user->annual_self_employment_income ?? 0);
        $dividendIncome = (float) ($user->annual_dividend_income ?? 0);
        $interestIncome = (float) ($user->annual_interest_income ?? 0);
        $trustIncome = (float) ($user->annual_trust_income ?? 0);
        $otherIncome = (float) ($user->annual_other_income ?? 0);

        // Get primary trust type if user has trusts (for correct tax treatment)
        $trustType = $this->getPrimaryTrustType($user);

        $totalAnnualIncome = $employmentIncome + $selfEmploymentIncome + $rentalIncome
            + $dividendIncome + $interestIncome + $trustIncome + $pensionIncome + $otherIncome;

        // Get detailed tax breakdown (new method with per-income breakdowns)
        $detailedTax = $this->taxCalculator->calculateDetailedNetIncome(
            $employmentIncome,
            $selfEmploymentIncome,
            $rentalIncome,
            $pensionIncome,
            $trustIncome,
            $interestIncome,
            $dividendIncome,
            $trustType,
            $pensionContributions,
            $section24Credit
        );

        // Get simple calculation for backwards compatibility. Pension contributions
        // reduce taxable earned income and ANI for PA taper; grossed-up Gift Aid
        // reduces ANI only.
        $giftAidGross = $user->is_gift_aid
            ? (float) ($user->annual_charitable_donations ?? 0) * 1.25
            : 0.0;
        $simpleTax = $this->taxCalculator->calculateNetIncome(
            $employmentIncome,
            $selfEmploymentIncome,
            $rentalIncome,
            $dividendIncome,
            $interestIncome,
            $trustIncome + $pensionIncome + $otherIncome,
            $pensionContributions,
            $giftAidGross
        );

        // Calculate expenditure once (includes financial commitments to match Expenditure tab)
        $expenditureBreakdown = $this->getExpenditureBreakdown($user);
        $annualExpenditure = $expenditureBreakdown['annual'];
        $monthlyExpenditure = $expenditureBreakdown['monthly'];
        $netIncome = $detailedTax['summary']['net_income'];

        // Calculate Child Benefit and HICBC
        $childBenefitPosition = $this->childBenefitService->calculateChildBenefitPosition($user, $totalAnnualIncome);

        return [
            'occupation' => $user->occupation,
            'employer' => $user->employer,
            'industry' => $user->industry,
            'employment_status' => $user->employment_status,
            'target_retirement_age' => $user->target_retirement_age,
            'retirement_date' => $user->retirement_date,
            'payday_day_of_month' => $user->payday_day_of_month,
            'annual_employment_income' => $user->annual_employment_income,
            'annual_self_employment_income' => $user->annual_self_employment_income,
            'annual_rental_income' => $rentalIncome,
            'annual_dividend_income' => $user->annual_dividend_income,
            'annual_interest_income' => $user->annual_interest_income,
            'annual_trust_income' => $user->annual_trust_income,
            'annual_other_income' => $user->annual_other_income,
            'annual_pension_income' => $pensionIncome,
            'annual_pension_contributions' => $pensionContributions,
            'total_annual_income' => $totalAnnualIncome,
            // Backwards compatible fields from simple calculation
            'gross_income' => $simpleTax['gross_income'],
            'income_tax' => $simpleTax['income_tax'],
            'national_insurance' => $simpleTax['national_insurance'],
            'total_deductions' => $simpleTax['total_deductions'],
            // Total income less income tax and National Insurance, plus any
            // Section 24 credit. Employee pension contributions are NOT deducted
            // here — they reduce the tax, not this figure — and the comment that
            // used to sit on this line said they were (W-0422). The label on the
            // Income tab now states the same three deductions this makes.
            'net_income' => $netIncome,
            'effective_tax_rate' => $simpleTax['effective_tax_rate'],
            'breakdown' => $simpleTax['breakdown'],
            // Expenditure and disposable income (includes financial commitments to match Expenditure tab)
            'expenditure_breakdown' => $expenditureBreakdown,
            'annual_expenditure' => $annualExpenditure,
            'monthly_expenditure' => $monthlyExpenditure,
            'disposable_income' => $netIncome - $annualExpenditure,
            'monthly_disposable' => ($netIncome - $annualExpenditure) / 12,
            // Rental income per-property breakdown for UI display
            'rental_breakdown' => $rentalBreakdown,
            // New detailed breakdown for UI display
            'detailed_tax_breakdown' => $detailedTax,
            // Child Benefit and HICBC
            'child_benefit' => [
                'annual_amount' => $childBenefitPosition['benefit']['annual_amount'],
                'eligible_children' => $childBenefitPosition['benefit']['eligible_children_count'],
                'breakdown' => $childBenefitPosition['benefit']['breakdown'],
            ],
            'hicbc' => [
                'applies' => $childBenefitPosition['hicbc']['applies'],
                'charge' => $childBenefitPosition['hicbc']['charge'],
                'net_benefit' => $childBenefitPosition['net_annual_benefit'],
                'clawback_percentage' => $childBenefitPosition['hicbc']['clawback_percentage'] ?? 0,
            ],
        ];
    }

    /**
     * Calculate total assets for the user
     */
    private function calculateAssetsSummary(User $user): array
    {
        // Use CrossModuleAssetAggregator for cross-module assets
        $breakdown = $this->assetAggregator->getAssetBreakdown($user->id);

        // Calculate Estate-specific assets (business, chattels)
        // Same consolidation as chattels below: the local sum this replaced read
        // `$user->businessInterests` (user_id only), so a business the user held as
        // JOINT owner was worth nothing here.
        $businessTotal = (float) $breakdown['business']['total'];

        // W-0138: chattels come from the same aggregator as cash/investments/property.
        // The local sum this replaced read `$user->chattels` (user_id only), so a
        // chattel the user held as JOINT owner was worth nothing here, and applied
        // ownership_percentage to individually-owned records that are wholly theirs.
        $chattelsTotal = (float) $breakdown['chattel']['total'];

        // Calculate pensions
        $pensionsTotal = $user->dcPensions->sum('current_fund_value');

        return [
            'cash' => [
                'total' => $breakdown['cash']['total'],
                'count' => $breakdown['cash']['count'],
            ],
            'investments' => [
                'total' => $breakdown['investment']['total'],
                'count' => $breakdown['investment']['count'],
            ],
            'properties' => [
                'total' => $breakdown['property']['total'],
                'count' => $breakdown['property']['count'],
            ],
            'business' => [
                'total' => $businessTotal,
                'count' => $breakdown['business']['count'],
            ],
            'chattels' => [
                'total' => $chattelsTotal,
                'count' => $breakdown['chattel']['count'],
            ],
            'pensions' => [
                'total' => $pensionsTotal,
                'count' => $user->dcPensions->count(),
            ],
            'total' => $breakdown['cash']['total'] + $breakdown['investment']['total'] + $breakdown['property']['total'] + $businessTotal + $chattelsTotal + $pensionsTotal,
        ];
    }

    /**
     * Calculate total liabilities for the user.
     *
     * Public because it is the one itemisation of this user's debts at their own
     * share, and a third surface now reads it: the Letter to Loved Ones, which
     * had been summing raw balances at 100% and charging the household £72,000
     * belonging to an off-platform co-owner (W-0421). Routed here rather than
     * given a fourth implementation.
     */
    public function calculateLiabilitiesSummary(User $user): array
    {
        // Every figure here is the user's SHARE of the debt, not the whole of every
        // record they happen to be primary owner of. This read `forUserPrimaryOnly`
        // and `$user->liabilities`, both scoped to `user_id` alone and both at 100%,
        // so `/protection` showed "Mortgage Debt £365,000" for a household whose
        // primary owner owes £182,500 — including his wife's halves and £72,000
        // belonging to a co-owner with no account here (W-0187). The items and the
        // totals now come from the same share, so the list adds up to the figure
        // above it and both agree with the property cards.
        $userId = (int) $user->id;
        $debtTotals = $this->assetAggregator->calculateLiabilityTotals($userId);

        // Reach: a mortgage secured on a jointly-owned property counts even where
        // the user is not the borrower. Fraction: their side of the split.
        $mortgageRecords = $this->assetAggregator->getMortgages($userId);
        $mortgageLiabilities = Liability::forUserOrJoint($userId)
            ->where('liability_type', 'mortgage')
            ->get();

        $mortgagesTotal = $debtTotals['mortgages'];

        // Combine mortgage items from both sources
        $mortgageItems = collect();

        // Add Mortgage table records
        foreach ($mortgageRecords as $mortgage) {
            $mortgageItems->push([
                'id' => $mortgage->id,
                'lender' => $mortgage->lender_name,
                'outstanding_balance' => round($this->calculateUserMortgageShare($mortgage, $userId), 2),
                'interest_rate' => $mortgage->interest_rate,
                'monthly_payment' => round($this->calculateUserMortgageMonthlyPaymentShare($mortgage, $userId), 2),
                'property_id' => $mortgage->property_id,
                'source' => 'mortgage_table',
            ]);
        }

        // Add Estate\Liability mortgage records
        foreach ($mortgageLiabilities as $liability) {
            $mortgageItems->push([
                'id' => $liability->id,
                'lender' => $liability->liability_name,
                'outstanding_balance' => round($this->calculateUserShare($liability, $userId), 2),
                'interest_rate' => $liability->interest_rate,
                'monthly_payment' => $liability->monthly_payment,
                'property_id' => null,
                'source' => 'liability_table',
            ]);
        }

        // Get other liabilities (exclude mortgages)
        $otherLiabilities = Liability::forUserOrJoint($userId)
            ->whereNotIn('liability_type', ['mortgage'])
            ->get();
        $otherLiabilitiesTotal = $debtTotals['other'];

        return [
            'mortgages' => [
                'total' => $mortgagesTotal,
                'count' => $mortgageItems->count(),
                'items' => $mortgageItems,
            ],
            'other' => [
                'total' => $otherLiabilitiesTotal,
                'count' => $otherLiabilities->count(),
                'items' => $otherLiabilities->map(function ($liability) use ($userId) {
                    return [
                        'id' => $liability->id,
                        'liability_type' => $liability->liability_type,
                        'liability_name' => $liability->liability_name,
                        'description' => $liability->liability_name,
                        'amount' => round($this->calculateUserShare($liability, $userId), 2),
                        'monthly_payment' => $liability->monthly_payment,
                        'interest_rate' => $liability->interest_rate,
                        'notes' => $liability->notes,
                    ];
                }),
            ],
            'total' => $debtTotals['total'],
        ];
    }

    /**
     * The annual income shown for a family-member row that a real account sits
     * behind — one definition for both the stored-row path and the virtual
     * spouse path, which previously disagreed (W-0176).
     */
    private function linkedAccountAnnualIncome(User $linkedUser): ?float
    {
        $income = $linkedUser->annual_employment_income;

        return $income === null ? null : (float) $income;
    }

    /**
     * Get family members including shared members from linked spouse.
     *
     * Falls back to a virtual spouse record constructed from the User model when
     * `users.spouse_id` is set but no `family_members` row with `relationship='spouse'`
     * exists — keeps `/api/user/profile` and `/api/user/family-members` in sync.
     */
    public function getFamilyMembersWithSharing(User $user): array
    {
        // Get user's own family members
        $familyMembers = $user->familyMembers->map(function ($member) {
            $memberArray = $member->toArray();
            $memberArray['is_shared'] = false;
            $memberArray['owner'] = 'self';

            // The email belongs to the account THIS row links to. Reading it
            // off `users.spouse_id` handed the real spouse's address to a row
            // that links to nobody, which is what made an orphan render as
            // "Account Linked" beside the genuine one (W-0051).
            if ($linkedUser = $member->liveLinkedUser()) {
                $memberArray['email'] = $linkedUser->email;
                // Same rule for income: the row's own `annual_income` column is
                // whatever was typed before the accounts were linked and is never
                // written again, so a linked spouse earning £120,000 rendered as
                // £0 — the column holds the string '0.00', which is truthy in
                // JavaScript, so the card printed it instead of hiding it
                // (W-0176). Once an account is behind the row, that account is
                // the source.
                $memberArray['annual_income'] = $this->linkedAccountAnnualIncome($linkedUser);
            }

            return $memberArray;
        });

        // If user has a linked spouse but no spouse family_member record, add spouse from User record
        $hasOwnSpouseRecord = $familyMembers->contains(function ($fm) {
            return $fm['relationship'] === 'spouse';
        });

        if ($user->spouse_id && ! $hasOwnSpouseRecord && $user->spouse) {
            $spouseUser = $user->spouse;
            if ($spouseUser) {
                // Create a virtual spouse family member from the User record
                $familyMembers->push([
                    'id' => null,  // Virtual record, no ID
                    'user_id' => $user->id,
                    'household_id' => $user->household_id,
                    'relationship' => 'spouse',
                    'name' => $spouseUser->name,
                    'date_of_birth' => $spouseUser->date_of_birth?->format('Y-m-d'),
                    'gender' => $spouseUser->gender,
                    'national_insurance_number' => $spouseUser->national_insurance_number ? '***'.substr($spouseUser->national_insurance_number, -4) : null,
                    'annual_income' => $this->linkedAccountAnnualIncome($spouseUser),
                    'is_dependent' => false,
                    'notes' => null,
                    'email' => $spouseUser->email,
                    // Hand-built rather than serialised from a model, so the
                    // predicate every surface reads has to be set explicitly.
                    // This row exists BECAUSE the accounts are linked.
                    'is_linked_account' => true,
                    'is_shared' => false,
                    'owner' => 'self',
                    'created_at' => null,
                    'updated_at' => null,
                ]);
            }
        }

        // If user has a linked spouse, get spouse's children (NOT the spouse record
        // itself). Keyed on the LIVE spouse: a deleted spouse's records are kept
        // for regulatory purposes but must stop being visible to their partner,
        // and this payload was still handing them over — tagged owner: 'spouse'
        // alongside a spouse field the same payload had already nulled out.
        $liveSpouseId = $user->liveSpouseId();
        if ($liveSpouseId) {
            $spouseFamilyMembers = FamilyMember::where('user_id', $liveSpouseId)
                ->where('relationship', 'child')  // Only children, not spouse record
                ->orderBy('date_of_birth')
                ->get();

            // Process spouse's children (mark as shared if not duplicate)
            $sharedFromSpouse = $spouseFamilyMembers->map(function ($member) use ($familyMembers) {
                $memberArray = $member->toArray();

                // Check if this child already exists in user's family members (duplicate)
                $isDuplicate = $familyMembers->contains(function ($fm) use ($member) {
                    return $fm['relationship'] === 'child' &&
                           $fm['name'] === $member->name &&
                           $fm['date_of_birth'] === $member->date_of_birth;
                });

                if (! $isDuplicate) {
                    $memberArray['is_shared'] = true;
                    $memberArray['owner'] = 'spouse';

                    return $memberArray;
                }

                return null;
            })->filter(); // Remove nulls

            // Merge user's family members with spouse's shared records
            $allMembers = $familyMembers->concat($sharedFromSpouse);

            return $allMembers->values()->toArray();
        }

        return $familyMembers->toArray();
    }

    /**
     * Get all financial commitments for expenditure tracking
     * Returns monthly payments from pensions, properties, investments, protection, and liabilities
     */
    public function getFinancialCommitments(User $user, string $ownershipFilter = 'all'): array
    {
        $commitments = [
            'retirement' => [],
            'properties' => [],
            'investments' => [],
            'savings' => [],
            'protection' => [],
            'liabilities' => [],
        ];

        // 1. DC Pension Contributions
        // Note: DC Pensions are always individual - no joint ownership support
        $dcPensions = app(PensionStore::class)->forUserByType($user, 'dc');
        foreach ($dcPensions as $pension) {
            if ($pension->monthly_contribution_amount > 0) {
                // Apply ownership filter - DC pensions are always individual
                if (! $this->shouldIncludeByOwnership(false, $ownershipFilter)) {
                    continue;
                }

                $commitments['retirement'][] = [
                    'id' => $pension->id,
                    'name' => $pension->scheme_name ?? 'DC Pension',
                    'type' => 'dc_pension',
                    'monthly_amount' => $pension->monthly_contribution_amount,
                    'is_joint' => false,
                    'ownership_type' => 'individual',
                ];
            }
        }

        // 2. Property Expenses (mortgage + council tax + utilities + maintenance)
        // Include properties owned by user OR where user is the joint owner
        $properties = $this->propertyStore->forUserWithJointOwner($user);
        foreach ($properties as $property) {
            $totalMonthlyExpense = 0;
            $breakdown = [];
            $isJoint = in_array($property->ownership_type, ['joint', 'tenants_in_common']);
            $userIsOwner = $property->user_id === $user->id;
            $ownershipPercentage = $isJoint
                ? ($userIsOwner ? ($property->ownership_percentage ?? 50) : (100 - ($property->ownership_percentage ?? 50)))
                : 100;
            $ownershipMultiplier = $ownershipPercentage / 100;

            // Mortgage payment - respect mortgage's own ownership_type
            $mortgage = $property->mortgages()->first();
            $mortgageOwnershipPercentage = 100; // Default to 100% for individual or no mortgage
            if ($mortgage && $mortgage->monthly_payment > 0) {
                // Check mortgage's ownership_type, not property's
                $mortgageAmount = $mortgage->monthly_payment;
                if ($mortgage->ownership_type === 'joint') {
                    // Joint mortgage: apply property ownership percentage
                    $mortgageAmount = $mortgage->monthly_payment * $ownershipMultiplier;
                    $mortgageOwnershipPercentage = $ownershipPercentage;
                }
                // Individual mortgage: full amount belongs to this owner (100%)
                $totalMonthlyExpense += $mortgageAmount;
                $breakdown['mortgage'] = $mortgageAmount;
            }

            // Non-mortgage expenses: apply property ownership percentage for joint/tenants_in_common
            // Council tax
            if ($property->monthly_council_tax > 0) {
                $amount = $property->monthly_council_tax * $ownershipMultiplier;
                $totalMonthlyExpense += $amount;
                $breakdown['council_tax'] = $amount;
            }

            // Utilities (individual)
            if (($property->monthly_gas ?? 0) > 0) {
                $amount = $property->monthly_gas * $ownershipMultiplier;
                $totalMonthlyExpense += $amount;
                $breakdown['gas'] = $amount;
            }
            if (($property->monthly_electricity ?? 0) > 0) {
                $amount = $property->monthly_electricity * $ownershipMultiplier;
                $totalMonthlyExpense += $amount;
                $breakdown['electricity'] = $amount;
            }
            if (($property->monthly_water ?? 0) > 0) {
                $amount = $property->monthly_water * $ownershipMultiplier;
                $totalMonthlyExpense += $amount;
                $breakdown['water'] = $amount;
            }

            // Insurance (individual)
            if (($property->monthly_building_insurance ?? 0) > 0) {
                $amount = $property->monthly_building_insurance * $ownershipMultiplier;
                $totalMonthlyExpense += $amount;
                $breakdown['building_insurance'] = $amount;
            }
            if (($property->monthly_contents_insurance ?? 0) > 0) {
                $amount = $property->monthly_contents_insurance * $ownershipMultiplier;
                $totalMonthlyExpense += $amount;
                $breakdown['contents_insurance'] = $amount;
            }

            // Service charge
            if (($property->monthly_service_charge ?? 0) > 0) {
                $amount = $property->monthly_service_charge * $ownershipMultiplier;
                $totalMonthlyExpense += $amount;
                $breakdown['service_charge'] = $amount;
            }

            // Maintenance reserve
            if (($property->monthly_maintenance_reserve ?? 0) > 0) {
                $amount = $property->monthly_maintenance_reserve * $ownershipMultiplier;
                $totalMonthlyExpense += $amount;
                $breakdown['maintenance'] = $amount;
            }

            // Other costs
            if (($property->other_monthly_costs ?? 0) > 0) {
                $amount = $property->other_monthly_costs * $ownershipMultiplier;
                $totalMonthlyExpense += $amount;
                $breakdown['other'] = $amount;
            }

            // Managing agent fee
            if (($property->managing_agent_fee ?? 0) > 0) {
                $amount = $property->managing_agent_fee * $ownershipMultiplier;
                $totalMonthlyExpense += $amount;
                $breakdown['managing_agent'] = $amount;
            }

            if ($totalMonthlyExpense > 0) {
                // Apply ownership filter
                if (! $this->shouldIncludeByOwnership($isJoint, $ownershipFilter)) {
                    continue;
                }

                // monthly_amount is now the user's actual share
                $commitments['properties'][] = [
                    'id' => $property->id,
                    'name' => $property->property_name ?? $property->address_line_1,
                    'type' => 'property',
                    'monthly_amount' => $totalMonthlyExpense,
                    'breakdown' => $breakdown,
                    'is_joint' => $isJoint,
                    'ownership_type' => $property->ownership_type,
                    'ownership_percentage' => $ownershipPercentage,
                    'mortgage_ownership_percentage' => $mortgageOwnershipPercentage,
                ];
            }
        }

        // 3. Investment Contributions
        // Include accounts owned by user OR where user is the joint owner
        $investmentAccounts = InvestmentAccount::where(function ($query) use ($user) {
            $query->where('user_id', $user->id)
                ->orWhere('joint_owner_id', $user->id);
        })->get();
        foreach ($investmentAccounts as $account) {
            $isJoint = in_array($account->ownership_type, ['joint', 'tenants_in_common']);
            $userIsOwner = $account->user_id === $user->id;
            $ownershipPercentage = $isJoint
                ? ($userIsOwner ? ($account->ownership_percentage ?? 50) : (100 - ($account->ownership_percentage ?? 50)))
                : 100;
            $ownershipMultiplier = $ownershipPercentage / 100;

            // Calculate monthly contribution based on frequency
            $monthlyContribution = 0;
            if ($account->monthly_contribution_amount > 0) {
                $monthlyContribution = match ($account->contribution_frequency) {
                    'quarterly' => $account->monthly_contribution_amount / 3,
                    'annually' => $account->monthly_contribution_amount / 12,
                    default => $account->monthly_contribution_amount, // monthly
                };
            }

            // Track lump sum as a one-off amount (not spread monthly)
            $lumpSumAmount = 0;
            if ($account->planned_lump_sum_amount > 0 && $account->planned_lump_sum_date) {
                $lumpSumDate = Carbon::parse($account->planned_lump_sum_date);

                // Only include if lump sum is planned within the next 12 months
                if ($lumpSumDate->isFuture() && $lumpSumDate->diffInMonths(Carbon::now()) <= 12) {
                    $lumpSumAmount = $account->planned_lump_sum_amount;
                }
            }

            $totalMonthly = $monthlyContribution * $ownershipMultiplier;
            $totalLumpSum = $lumpSumAmount * $ownershipMultiplier;

            if ($totalMonthly > 0 || $totalLumpSum > 0) {
                // Apply ownership filter
                if (! $this->shouldIncludeByOwnership($isJoint, $ownershipFilter)) {
                    continue;
                }

                $commitments['investments'][] = [
                    'id' => $account->id,
                    'name' => $account->account_name ?? $account->provider ?? 'Investment Account',
                    'type' => $account->account_type ?? 'investment',
                    'monthly_amount' => $totalMonthly,
                    'lump_sum_amount' => $totalLumpSum,
                    'lump_sum_date' => $account->planned_lump_sum_date,
                    'is_joint' => $isJoint,
                    'ownership_type' => $account->ownership_type,
                    'ownership_percentage' => $ownershipPercentage,
                ];
            }
        }

        // 4. Savings Account Contributions
        // Include accounts owned by user OR where user is the joint owner
        $savingsAccounts = SavingsAccount::where(function ($query) use ($user) {
            $query->where('user_id', $user->id)
                ->orWhere('joint_owner_id', $user->id);
        })->where('regular_contribution_amount', '>', 0)->get();
        foreach ($savingsAccounts as $account) {
            $isJoint = in_array($account->ownership_type, ['joint', 'tenants_in_common']);
            $userIsOwner = $account->user_id === $user->id;
            $ownershipPercentage = $isJoint
                ? ($userIsOwner ? ($account->ownership_percentage ?? 50) : (100 - ($account->ownership_percentage ?? 50)))
                : 100;
            $ownershipMultiplier = $ownershipPercentage / 100;

            // Calculate monthly contribution based on frequency
            $monthlyContribution = match ($account->contribution_frequency) {
                'quarterly' => $account->regular_contribution_amount / 3,
                'annually' => $account->regular_contribution_amount / 12,
                default => $account->regular_contribution_amount, // monthly
            };

            $totalMonthly = $monthlyContribution * $ownershipMultiplier;

            if ($totalMonthly > 0) {
                // Apply ownership filter
                if (! $this->shouldIncludeByOwnership($isJoint, $ownershipFilter)) {
                    continue;
                }

                $commitments['savings'][] = [
                    'id' => $account->id,
                    'name' => $account->account_name ?? $account->institution ?? 'Savings Account',
                    'type' => $account->account_type ?? 'savings',
                    'monthly_amount' => $totalMonthly,
                    'is_joint' => $isJoint,
                    'ownership_type' => $account->ownership_type,
                    'ownership_percentage' => $ownershipPercentage,
                ];
            }
        }

        // 5. Protection Premiums
        // Life Insurance
        $lifeInsurancePolicies = LifeInsurancePolicy::where('user_id', $user->id)->get();
        foreach ($lifeInsurancePolicies as $policy) {
            // Calculate monthly premium based on frequency
            $monthlyPremium = $policy->premium_amount;
            if ($policy->premium_frequency === 'quarterly') {
                $monthlyPremium = $policy->premium_amount / 3;
            } elseif ($policy->premium_frequency === 'annually') {
                $monthlyPremium = $policy->premium_amount / 12;
            }

            if ($monthlyPremium > 0) {
                $commitments['protection'][] = [
                    'id' => $policy->id,
                    'name' => $policy->policy_name ?? 'Life Insurance',
                    'type' => 'life_insurance',
                    'monthly_amount' => $monthlyPremium,
                    'is_joint' => false, // Life insurance not typically joint
                    'ownership_type' => 'individual',
                ];
            }
        }

        // Critical Illness
        $criticalIllnessPolicies = CriticalIllnessPolicy::where('user_id', $user->id)->get();
        foreach ($criticalIllnessPolicies as $policy) {
            // Calculate monthly premium based on frequency
            $monthlyPremium = $policy->premium_amount;
            if ($policy->premium_frequency === 'quarterly') {
                $monthlyPremium = $policy->premium_amount / 3;
            } elseif ($policy->premium_frequency === 'annually') {
                $monthlyPremium = $policy->premium_amount / 12;
            }

            if ($monthlyPremium > 0) {
                $commitments['protection'][] = [
                    'id' => $policy->id,
                    'name' => $policy->policy_name ?? 'Critical Illness',
                    'type' => 'critical_illness',
                    'monthly_amount' => $monthlyPremium,
                    'is_joint' => false,
                    'ownership_type' => 'individual',
                ];
            }
        }

        // Income Protection
        $incomeProtectionPolicies = IncomeProtectionPolicy::where('user_id', $user->id)->get();
        foreach ($incomeProtectionPolicies as $policy) {
            // Calculate monthly premium based on frequency
            $monthlyPremium = $policy->premium_amount;
            if ($policy->premium_frequency === 'quarterly') {
                $monthlyPremium = $policy->premium_amount / 3;
            } elseif ($policy->premium_frequency === 'annually') {
                $monthlyPremium = $policy->premium_amount / 12;
            }

            if ($monthlyPremium > 0) {
                $commitments['protection'][] = [
                    'id' => $policy->id,
                    'name' => $policy->policy_name ?? 'Income Protection',
                    'type' => 'income_protection',
                    'monthly_amount' => $monthlyPremium,
                    'is_joint' => false,
                    'ownership_type' => 'individual',
                ];
            }
        }

        // Disability
        $disabilityPolicies = DisabilityPolicy::where('user_id', $user->id)->get();
        foreach ($disabilityPolicies as $policy) {
            // Calculate monthly premium based on frequency
            $monthlyPremium = $policy->premium_amount;
            if ($policy->premium_frequency === 'quarterly') {
                $monthlyPremium = $policy->premium_amount / 3;
            } elseif ($policy->premium_frequency === 'annually') {
                $monthlyPremium = $policy->premium_amount / 12;
            }

            if ($monthlyPremium > 0) {
                $commitments['protection'][] = [
                    'id' => $policy->id,
                    'name' => $policy->policy_name ?? 'Disability',
                    'type' => 'disability',
                    'monthly_amount' => $monthlyPremium,
                    'is_joint' => false,
                    'ownership_type' => 'individual',
                ];
            }
        }

        // Sickness/Illness
        $sicknessIllnessPolicies = SicknessIllnessPolicy::where('user_id', $user->id)->get();
        foreach ($sicknessIllnessPolicies as $policy) {
            // Calculate monthly premium based on frequency
            $monthlyPremium = $policy->premium_amount;
            if ($policy->premium_frequency === 'quarterly') {
                $monthlyPremium = $policy->premium_amount / 3;
            } elseif ($policy->premium_frequency === 'annually') {
                $monthlyPremium = $policy->premium_amount / 12;
            }

            if ($monthlyPremium > 0) {
                $commitments['protection'][] = [
                    'id' => $policy->id,
                    'name' => $policy->policy_name ?? 'Sickness/Illness',
                    'type' => 'sickness_illness',
                    'monthly_amount' => $monthlyPremium,
                    'is_joint' => false,
                    'ownership_type' => 'individual',
                ];
            }
        }

        // 6. Liability Payments (excluding mortgages - they're in properties)
        // Include liabilities owned by user OR where user is the joint owner
        $liabilities = Liability::where(function ($query) use ($user) {
            $query->where('user_id', $user->id)
                ->orWhere('joint_owner_id', $user->id);
        })->where('liability_type', '!=', 'mortgage')->get();

        foreach ($liabilities as $liability) {
            if ($liability->monthly_payment > 0) {
                // Adjust for joint ownership
                $isJoint = in_array($liability->ownership_type, ['joint', 'tenants_in_common'], true);
                $userIsOwner = $liability->user_id === $user->id;
                $primaryPercentage = (float) ($liability->ownership_percentage ?? 50);
                if ($primaryPercentage === 100.0) {
                    $primaryPercentage = 50.0;
                }
                $ownershipPercentage = $isJoint
                    ? ($userIsOwner ? $primaryPercentage : (100 - $primaryPercentage))
                    : 100;

                // Apply ownership filter
                if (! $this->shouldIncludeByOwnership($isJoint, $ownershipFilter)) {
                    continue;
                }

                $displayAmount = $liability->monthly_payment * ($ownershipPercentage / 100);

                $commitments['liabilities'][] = [
                    'id' => $liability->id,
                    'name' => $liability->liability_name,
                    'type' => $liability->liability_type,
                    'monthly_amount' => $displayAmount,
                    'is_joint' => $isJoint,
                    'ownership_type' => $liability->ownership_type,
                    'ownership_percentage' => $ownershipPercentage,
                ];
            }
        }

        // Calculate totals for each category
        $totals = [
            'retirement' => collect($commitments['retirement'])->sum('monthly_amount'),
            'properties' => collect($commitments['properties'])->sum('monthly_amount'),
            'investments' => collect($commitments['investments'])->sum('monthly_amount'),
            'savings' => collect($commitments['savings'])->sum('monthly_amount'),
            'protection' => collect($commitments['protection'])->sum('monthly_amount'),
            'liabilities' => collect($commitments['liabilities'])->sum('monthly_amount'),
        ];

        // Lump sum totals (one-off amounts, not monthly)
        $totals['investments_lump_sum'] = collect($commitments['investments'])->sum('lump_sum_amount');
        $totals['annual_lump_sum'] = $totals['investments_lump_sum'];

        $totals['total'] = $totals['retirement'] + $totals['properties'] + $totals['investments'] + $totals['savings'] + $totals['protection'] + $totals['liabilities'];

        return [
            'commitments' => $commitments,
            'totals' => $totals,
        ];
    }

    /**
     * Helper method to determine if an item should be included based on ownership filter
     *
     * @param  bool  $isJoint  Whether the item is jointly owned
     * @param  string  $filter  The ownership filter ('all', 'joint_only', 'individual_only')
     * @return bool True if item should be included, false if it should be skipped
     */
    private function shouldIncludeByOwnership(bool $isJoint, string $filter): bool
    {
        return match ($filter) {
            'joint_only' => $isJoint,
            'individual_only' => ! $isJoint,
            'all' => true,
            default => true,
        };
    }
}
