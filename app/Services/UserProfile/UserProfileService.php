<?php

declare(strict_types=1);

namespace App\Services\UserProfile;

use App\Models\Property;
use App\Models\User;
use App\Services\Benefits\ChildBenefitService;
use App\Services\Shared\CrossModuleAssetAggregator;
use App\Services\UKTaxCalculator;

class UserProfileService
{
    public function __construct(
        private readonly CrossModuleAssetAggregator $assetAggregator,
        private readonly UKTaxCalculator $taxCalculator,
        private readonly ChildBenefitService $childBenefitService
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
                'good_health' => $user->good_health,
                'smoker' => $user->smoker,
                'life_expectancy_override' => $user->life_expectancy_override,
            ],
            'household' => $user->household,
            'spouse' => $user->spouse ? [
                'id' => $user->spouse->id,
                'name' => $user->spouse->name,
                'email' => $user->spouse->email,
            ] : null,
            'income_occupation' => $this->buildIncomeOccupation($user),
            'expenditure' => [
                'monthly_expenditure' => $user->monthly_expenditure,
                'annual_expenditure' => $user->annual_expenditure,
                'categories' => [
                    'food_groceries' => $user->food_groceries,
                    'transport_fuel' => $user->transport_fuel,
                    'clothing_personal_care' => $user->clothing_personal_care,
                    'entertainment_dining' => $user->entertainment_dining,
                    'childcare' => $user->childcare,
                    'other_expenditure' => $user->other_expenditure,
                ],
            ],
            'family_members' => $this->getFamilyMembersWithSharing($user),
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

        $user->update($data);

        return $user->fresh();
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
            $arrivalDate = \Carbon\Carbon::parse($user->uk_arrival_date);
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
     * Calculate total annual taxable rental income from user's BTL properties.
     * Uses PropertyService::calculateTaxPosition() as the single source of truth.
     *
     * Includes both:
     * - Properties where user is primary owner (user_id)
     * - Properties where user is joint owner (joint_owner_id)
     */
    private function calculateAnnualRentalIncome(User $user): array
    {
        $propertyService = app(\App\Services\Property\PropertyService::class);
        $properties = [];
        $totalTaxableIncome = 0;
        $totalSection24Credit = 0;

        // Get all BTL properties where user is either primary owner OR joint owner
        $btlProperties = Property::where('property_type', 'buy_to_let')
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhere('joint_owner_id', $user->id);
            })
            ->with('mortgages')
            ->get();

        foreach ($btlProperties as $property) {
            // Pass user ID so calculateTaxPosition returns the correct ownership share
            $taxPosition = $propertyService->calculateTaxPosition($property, $user->id);

            if ($taxPosition['annual_taxable_income'] <= 0 && $taxPosition['section_24_annual_credit'] <= 0) {
                continue;
            }

            $totalTaxableIncome += $taxPosition['annual_taxable_income'];
            $totalSection24Credit += $taxPosition['section_24_annual_credit'];

            $properties[] = [
                'name' => $taxPosition['property_name'],
                'annual_taxable' => $taxPosition['annual_taxable_income'],
                'annual_credit' => $taxPosition['section_24_annual_credit'],
                'ownership_percentage' => $taxPosition['ownership_percentage'],
            ];
        }

        return [
            'total' => round($totalTaxableIncome, 2),
            'section_24_credit' => round($totalSection24Credit, 2),
            'properties' => $properties,
        ];
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
     */
    private function calculateAnnualPensionIncome(User $user): float
    {
        $pensionIncome = 0.0;

        // Sum DB pensions that are in payment (user has reached retirement age or pension is marked as in payment)
        foreach ($user->dbPensions as $dbPension) {
            // Check if pension is in payment (accrued_annual_pension represents current annual amount)
            if ($dbPension->accrued_annual_pension > 0) {
                $pensionIncome += (float) $dbPension->accrued_annual_pension;
            }
        }

        // Add state pension if receiving
        $statePension = $user->statePension;
        if ($statePension && $statePension->already_receiving) {
            $pensionIncome += (float) ($statePension->state_pension_forecast_annual ?? 0);
        }

        return $pensionIncome;
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

        // Get simple calculation for backwards compatibility
        $simpleTax = $this->taxCalculator->calculateNetIncome(
            $employmentIncome,
            $selfEmploymentIncome,
            $rentalIncome,
            $dividendIncome,
            $interestIncome,
            $trustIncome + $pensionIncome + $otherIncome
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
            // Use detailed net_income (includes pension contributions) for consistency with TaxSummaryCard
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
        $businessTotal = $user->businessInterests->sum(function ($business) {
            return $business->current_valuation * ($business->ownership_percentage / 100);
        });

        $chattelsTotal = $user->chattels->sum(function ($chattel) {
            return $chattel->current_value * ($chattel->ownership_percentage / 100);
        });

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
                'count' => $user->businessInterests->count(),
            ],
            'chattels' => [
                'total' => $chattelsTotal,
                'count' => $user->chattels->count(),
            ],
            'pensions' => [
                'total' => $pensionsTotal,
                'count' => $user->dcPensions->count(),
            ],
            'total' => $breakdown['cash']['total'] + $breakdown['investment']['total'] + $breakdown['property']['total'] + $businessTotal + $chattelsTotal + $pensionsTotal,
        ];
    }

    /**
     * Calculate total liabilities for the user
     */
    private function calculateLiabilitiesSummary(User $user): array
    {
        // Get mortgages from both Mortgage table and Estate\Liability table (type='mortgage')
        $mortgageRecords = $user->mortgages; // From mortgages table
        $mortgageLiabilities = $user->liabilities->where('liability_type', 'mortgage'); // From liabilities table

        $mortgagesTotal = $mortgageRecords->sum('outstanding_balance') +
                         $mortgageLiabilities->sum('current_balance');

        // Combine mortgage items from both sources
        $mortgageItems = collect();

        // Add Mortgage table records
        foreach ($mortgageRecords as $mortgage) {
            $mortgageItems->push([
                'id' => $mortgage->id,
                'lender' => $mortgage->lender_name,
                'outstanding_balance' => $mortgage->outstanding_balance,
                'interest_rate' => $mortgage->interest_rate,
                'monthly_payment' => $mortgage->monthly_payment,
                'property_id' => $mortgage->property_id,
                'source' => 'mortgage_table',
            ]);
        }

        // Add Estate\Liability mortgage records
        foreach ($mortgageLiabilities as $liability) {
            $mortgageItems->push([
                'id' => $liability->id,
                'lender' => $liability->liability_name,
                'outstanding_balance' => $liability->current_balance,
                'interest_rate' => $liability->interest_rate,
                'monthly_payment' => $liability->monthly_payment,
                'property_id' => null,
                'source' => 'liability_table',
            ]);
        }

        // Get other liabilities (exclude mortgages)
        $otherLiabilities = $user->liabilities->whereNotIn('liability_type', ['mortgage']);
        $otherLiabilitiesTotal = $otherLiabilities->sum('current_balance');

        return [
            'mortgages' => [
                'total' => $mortgagesTotal,
                'count' => $mortgageItems->count(),
                'items' => $mortgageItems,
            ],
            'other' => [
                'total' => $otherLiabilitiesTotal,
                'count' => $otherLiabilities->count(),
                'items' => $otherLiabilities->map(function ($liability) {
                    return [
                        'id' => $liability->id,
                        'liability_type' => $liability->liability_type,
                        'liability_name' => $liability->liability_name,
                        'description' => $liability->liability_name,
                        'amount' => $liability->current_balance,
                        'monthly_payment' => $liability->monthly_payment,
                        'interest_rate' => $liability->interest_rate,
                        'notes' => $liability->notes,
                    ];
                }),
            ],
            'total' => $mortgagesTotal + $otherLiabilitiesTotal,
        ];
    }

    /**
     * Get family members including shared members from linked spouse
     */
    private function getFamilyMembersWithSharing(User $user): array
    {
        // Get user's own family members
        $familyMembers = $user->familyMembers->map(function ($member) use ($user) {
            $memberArray = $member->toArray();
            $memberArray['is_shared'] = false;
            $memberArray['owner'] = 'self';

            // If this is a spouse and user has a spouse_id, get the spouse's email
            if ($member->relationship === 'spouse' && $user->spouse_id && $user->spouse) {
                $memberArray['email'] = $user->spouse->email;
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
                    'annual_income' => $spouseUser->annual_employment_income,
                    'is_dependent' => false,
                    'notes' => null,
                    'email' => $spouseUser->email,
                    'is_shared' => false,
                    'owner' => 'self',
                    'created_at' => null,
                    'updated_at' => null,
                ]);
            }
        }

        // If user has a linked spouse, get spouse's children (NOT the spouse record itself)
        if ($user->spouse_id) {
            $spouseFamilyMembers = \App\Models\FamilyMember::where('user_id', $user->spouse_id)
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
        $dcPensions = \App\Models\DCPension::where('user_id', $user->id)->get();
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
        $properties = \App\Models\Property::where(function ($query) use ($user) {
            $query->where('user_id', $user->id)
                ->orWhere('joint_owner_id', $user->id);
        })->get();
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
        $investmentAccounts = \App\Models\Investment\InvestmentAccount::where(function ($query) use ($user) {
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
                $lumpSumDate = \Carbon\Carbon::parse($account->planned_lump_sum_date);

                // Only include if lump sum is planned within the next 12 months
                if ($lumpSumDate->isFuture() && $lumpSumDate->diffInMonths(\Carbon\Carbon::now()) <= 12) {
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
        $savingsAccounts = \App\Models\SavingsAccount::where(function ($query) use ($user) {
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
        $lifeInsurancePolicies = \App\Models\LifeInsurancePolicy::where('user_id', $user->id)->get();
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
        $criticalIllnessPolicies = \App\Models\CriticalIllnessPolicy::where('user_id', $user->id)->get();
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
        $incomeProtectionPolicies = \App\Models\IncomeProtectionPolicy::where('user_id', $user->id)->get();
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
        $disabilityPolicies = \App\Models\DisabilityPolicy::where('user_id', $user->id)->get();
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
        $sicknessIllnessPolicies = \App\Models\SicknessIllnessPolicy::where('user_id', $user->id)->get();
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
        $liabilities = \App\Models\Estate\Liability::where(function ($query) use ($user) {
            $query->where('user_id', $user->id)
                ->orWhere('joint_owner_id', $user->id);
        })->where('liability_type', '!=', 'mortgage')->get();

        foreach ($liabilities as $liability) {
            if ($liability->monthly_payment > 0) {
                // Adjust for joint ownership
                $isJoint = $liability->ownership_type === 'joint';
                $userIsOwner = $liability->user_id === $user->id;
                $ownershipPercentage = $isJoint
                    ? ($userIsOwner ? ($liability->ownership_percentage ?? 50) : (100 - ($liability->ownership_percentage ?? 50)))
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
