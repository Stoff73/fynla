<?php

declare(strict_types=1);

namespace App\Services\Estate;

use App\Models\Estate\IHTProfile;
use App\Models\User;
use App\Services\TaxConfigService;

/**
 * Service for generating IHT mitigation strategies
 *
 * Extracted from EstateController to reduce controller complexity
 */
class IHTStrategyGeneratorService
{
    public function __construct(
        private readonly TaxConfigService $taxConfig
    ) {}

    /**
     * Generate default gifting strategy for users without linked spouse
     */
    public function generateDefaultGiftingStrategy(float $ihtLiability, User $user): ?array
    {
        if ($ihtLiability === 0) {
            return null;
        }

        $ihtConfig = $this->taxConfig->getInheritanceTax();
        $giftingConfig = $this->taxConfig->getGiftingExemptions();
        $ihtRate = (float) $ihtConfig['standard_rate']; // 0.40 (40%)

        $strategies = [];
        $totalIhtSaved = 0;

        // 1. Annual Exemption Strategy
        $annualExemption = (float) $giftingConfig['annual_exemption'];
        $currentAge = $user->date_of_birth
            ? (int) $user->date_of_birth->diffInYears(now())
            : 70;
        $lifeExpectancy = $user->life_expectancy_override ?? 90;
        $yearsToProject = min(20, max(1, $lifeExpectancy - $currentAge));
        $totalAnnualGifting = $annualExemption * $yearsToProject;
        $annualIhtSaved = $totalAnnualGifting * $ihtRate;

        $strategies[] = [
            'strategy_name' => 'Annual Exemption Gifting',
            'total_gifted' => $totalAnnualGifting,
            'iht_saved' => $annualIhtSaved,
            'implementation_steps' => [
                'Gift £'.number_format($annualExemption).' per year using the annual gift exemption',
                'Both spouses can gift £'.number_format($annualExemption).' each (£'.number_format($annualExemption * 2).' total per year)',
                'Unused allowance from previous year can be carried forward one year',
                "Total potential saving over {$yearsToProject} years: £".number_format($annualIhtSaved, 0),
            ],
        ];
        $totalIhtSaved += $annualIhtSaved;

        // 2. Potentially Exempt Transfers (PETs) - Lump Sum Gifting
        $targetReduction = min($ihtLiability / $ihtRate, 1000000); // Max £1m or enough to eliminate IHT
        $petGifting = $targetReduction;
        $petIhtSaved = $petGifting * $ihtRate;

        $strategies[] = [
            'strategy_name' => 'Potentially Exempt Transfers (PETs)',
            'total_gifted' => $petGifting,
            'iht_saved' => $petIhtSaved,
            'implementation_steps' => [
                'Make lump sum gifts of £'.number_format($petGifting, 0).' to family members',
                'Gifts become fully exempt after 7 years',
                'Taper relief applies if death occurs between 3-7 years',
                'Consider gifting over multiple years to use annual exemptions',
                'Potential IHT saving: £'.number_format($petIhtSaved, 0).' (if you survive 7 years)',
            ],
        ];
        $totalIhtSaved += $petIhtSaved;

        // 3. Normal Expenditure Out of Income
        $strategies[] = [
            'strategy_name' => 'Gifts from Surplus Income',
            'total_gifted' => 0, // Depends on user's income
            'iht_saved' => 0, // Variable
            'implementation_steps' => [
                'Establish a regular pattern of gifts from surplus income',
                'Must be from income (not capital)',
                'Must leave you with sufficient income to maintain your standard of living',
                'No 7-year rule - immediately exempt from IHT',
                'Ideal for pension income or rental income',
                'Add income and expenditure data to calculate your surplus',
            ],
        ];

        return [
            'strategies' => $strategies,
            'summary' => [
                'total_gifted' => $totalAnnualGifting + $petGifting,
                'total_iht_saved' => $totalIhtSaved,
                'implementation_timeframe' => $yearsToProject.' years',
            ],
        ];
    }

    /**
     * Generate prioritized IHT mitigation strategies
     *
     * Only shows strategies that are applicable and effective
     */
    public function generateIHTMitigationStrategies(
        array $secondDeathAnalysis,
        ?array $giftingStrategy,
        ?array $lifeCoverRecommendations,
        IHTProfile $profile
    ): array {
        $strategies = [];

        // Handle different data structures (second death vs standard IHT)
        if (isset($secondDeathAnalysis['second_death'])) {
            // Second death analysis structure
            $estateValue = $secondDeathAnalysis['second_death']['projected_combined_estate_at_second_death'];
            $ihtLiability = $secondDeathAnalysis['iht_calculation']['iht_liability'];
            $rnrb = $secondDeathAnalysis['iht_calculation']['rnrb'];
            $rnrbEligible = $secondDeathAnalysis['iht_calculation']['rnrb_eligible'];
            $taxableNetEstate = $secondDeathAnalysis['iht_calculation']['taxable_net_estate'] ?? 0;
        } elseif (isset($secondDeathAnalysis['iht_calculation'])) {
            // Wrapper structure: ['iht_calculation' => $ihtData]
            $ihtData = $secondDeathAnalysis['iht_calculation'];
            $estateValue = $ihtData['net_estate_value'] ?? 0;
            $ihtLiability = $ihtData['iht_liability'] ?? 0;
            $rnrb = $ihtData['rnrb'] ?? 0;
            $rnrbEligible = $ihtData['rnrb_eligible'] ?? false;
            $taxableNetEstate = $ihtData['taxable_net_estate'] ?? 0;
        } else {
            // Direct IHT calculation structure (already unwrapped)
            $estateValue = $secondDeathAnalysis['net_estate_value'] ?? 0;
            $ihtLiability = $secondDeathAnalysis['iht_liability'] ?? 0;
            $rnrb = $secondDeathAnalysis['rnrb'] ?? 0;
            $rnrbEligible = $secondDeathAnalysis['rnrb_eligible'] ?? false;
            $taxableNetEstate = $secondDeathAnalysis['taxable_net_estate'] ?? 0;
        }

        // For married users with spouse exemption, use taxable_net_estate instead of iht_liability
        // This represents what WILL be taxable on second death (after spouse exemption on first death)
        $effectiveIHTLiability = $ihtLiability;
        if ($ihtLiability === 0 && $taxableNetEstate > 0) {
            // Married user with spouse exemption - calculate potential IHT on taxable net estate
            // This is the estate that will be subject to IHT on second death
            $ihtConfig = $this->taxConfig->getInheritanceTax();
            $totalNRB = $secondDeathAnalysis['iht_calculation']['total_nrb'] ?? $ihtConfig['nil_rate_band'];
            $totalAllowance = $totalNRB + $rnrb;
            $potentialTaxableEstate = max(0, $taxableNetEstate - $totalAllowance);
            $effectiveIHTLiability = $potentialTaxableEstate * $ihtConfig['standard_rate']; // 40% IHT rate
        }

        // Only show strategies if there's actual or potential IHT liability
        if ($effectiveIHTLiability === 0 && $taxableNetEstate === 0) {
            return [[
                'message' => 'No IHT liability projected - no mitigation strategies needed',
                'status' => 'success',
            ]];
        }

        // 1. Gifting Strategy (if effective)
        if ($giftingStrategy && isset($giftingStrategy['summary']['total_iht_saved']) && $giftingStrategy['summary']['total_iht_saved'] > 0) {
            // Build useful summary of gifting strategy
            $giftingSummary = [];

            foreach ($giftingStrategy['strategies'] as $strategy) {
                // Only include strategies that actually save IHT
                if (($strategy['iht_saved'] ?? 0) > 0) {
                    $giftingSummary[] = sprintf(
                        '%s: Gift £%s over lifetime → Saves £%s IHT',
                        $strategy['strategy_name'],
                        number_format($strategy['total_gifted'] ?? 0, 0),
                        number_format($strategy['iht_saved'] ?? 0, 0)
                    );
                }
            }

            $strategies[] = [
                'priority' => 1,
                'strategy_name' => 'Gifting Strategy',
                'effectiveness' => 'High',
                'iht_saved' => $giftingStrategy['summary']['total_iht_saved'],
                'implementation_complexity' => 'Medium',
                'description' => 'Reduce estate value through strategic lifetime gifting to eliminate or reduce IHT liability',
                'specific_actions' => $giftingSummary,
                'total_gifted' => $giftingStrategy['summary']['total_gifted'] ?? 0,
                'reduction_percentage' => $giftingStrategy['summary']['reduction_percentage'] ?? 0,
            ];
        }

        // 2. Life Insurance (if needed after gifting)
        $totalIhtSaved = $giftingStrategy['summary']['total_iht_saved'] ?? 0;
        $ihtAfterGifting = max(0, $effectiveIHTLiability - $totalIhtSaved);
        if ($ihtAfterGifting > 10000 && $lifeCoverRecommendations && isset($lifeCoverRecommendations['scenarios']['cover_less_gifting']['annual_premium'])) {
            // Only recommend if material amount remains and life cover data available
            $strategies[] = [
                'priority' => 2,
                'strategy_name' => 'Life Insurance in Trust',
                'effectiveness' => 'High',
                'cover_needed' => $ihtAfterGifting,
                'estimated_annual_premium' => $lifeCoverRecommendations['scenarios']['cover_less_gifting']['annual_premium'],
                'implementation_complexity' => 'Low',
                'description' => 'Whole of Life policy written in trust to provide funds for IHT payment',
                'specific_actions' => [
                    'Arrange joint life second death policy for £'.number_format($ihtAfterGifting, 0),
                    'Write policy in trust (outside estate)',
                    'Review cover annually for inflation',
                ],
            ];
        } elseif ($ihtAfterGifting > 10000) {
            // Recommend life insurance even without specific quotes
            $strategies[] = [
                'priority' => 2,
                'strategy_name' => 'Life Insurance in Trust',
                'effectiveness' => 'High',
                'cover_needed' => $ihtAfterGifting,
                'implementation_complexity' => 'Low',
                'description' => 'Whole of Life policy written in trust to provide funds for IHT payment',
                'specific_actions' => [
                    'Arrange joint life second death policy for £'.number_format($ihtAfterGifting, 0),
                    'Write policy in trust (outside estate)',
                    'Review cover annually for inflation',
                    'Obtain quotes from multiple providers',
                ],
            ];
        }

        // 3. RNRB Strategy (only if not already claimed and estate qualifies)
        $ihtConfig = $this->taxConfig->getInheritanceTax();
        $rnrbTaperThreshold = (float) ($ihtConfig['rnrb_taper_threshold'] ?? 2000000);
        if (! $rnrbEligible && $estateValue <= $rnrbTaperThreshold) {
            $rnrbAmount = $ihtConfig['residence_nil_rate_band'];
            $ihtRate = (float) $ihtConfig['standard_rate'];

            $strategies[] = [
                'priority' => 3,
                'strategy_name' => 'Claim Residence Nil Rate Band (RNRB)',
                'effectiveness' => 'Medium',
                'iht_saved' => $rnrbAmount * $ihtRate, // RNRB * IHT rate
                'implementation_complexity' => 'Low',
                'description' => 'Ensure main residence passes to direct descendants to claim £'.number_format($rnrbAmount, 0).' RNRB',
                'specific_actions' => [
                    'Update will to leave main residence to children/grandchildren',
                    'Ensure estate value stays below £2m (RNRB taper threshold)',
                    'Document residence ownership and direct descendant beneficiaries',
                ],
            ];
        }

        // 4. Charitable Giving (if not already at 10%+)
        if ($profile->charitable_giving_percent < 10 && $effectiveIHTLiability > 0) {
            $charitableAmount = $estateValue * 0.10;
            $standardRate = (float) $ihtConfig['standard_rate']; // 0.40
            $charityRate = (float) ($ihtConfig['charity_rate'] ?? 0.36); // 0.36
            $rateDifference = $standardRate - $charityRate; // 40% - 36% = 4%
            $saving = ($estateValue - ($secondDeathAnalysis['iht_calculation']['total_nrb'] + $rnrb)) * $rateDifference;

            if ($saving > 5000) {
                // Only show if material saving
                $strategies[] = [
                    'priority' => 4,
                    'strategy_name' => 'Charitable Giving (10%+ of Estate)',
                    'effectiveness' => 'Medium',
                    'iht_saved' => $saving,
                    'charitable_amount_required' => $charitableAmount,
                    'implementation_complexity' => 'Low',
                    'description' => sprintf('Leave 10%%+ of estate to charity to reduce IHT rate from %s%% to %s%%', round($standardRate * 100), round($charityRate * 100)),
                    'specific_actions' => [
                        'Update will to leave £'.number_format($charitableAmount, 0).' (10% of estate) to registered charities',
                        'Select charities and notify executors',
                        'Review annually as estate value changes',
                    ],
                ];
            }
        }

        // 5. Trust Planning (always relevant for larger estates)
        if ($effectiveIHTLiability > 100000) {
            $strategies[] = [
                'priority' => 5,
                'strategy_name' => 'Discretionary Trust Planning',
                'effectiveness' => 'High',
                'iht_saved' => null, // Variable depending on implementation
                'implementation_complexity' => 'High',
                'description' => 'Transfer assets into discretionary trusts to remove them from your estate',
                'specific_actions' => [
                    'Consider a Discounted Gift Trust for lump sum investments',
                    'Set up a Loan Trust to retain access to capital',
                    'Use Bare Trusts for gifts to children/grandchildren',
                    'Seek professional advice on trust structure',
                    'Note: Gifts to discretionary trusts are Chargeable Lifetime Transfers (20% immediate charge if over NRB)',
                ],
            ];
        }

        // 6. Pension Planning (pensions are outside the estate)
        if ($effectiveIHTLiability > 50000) {
            $strategies[] = [
                'priority' => 6,
                'strategy_name' => 'Pension Planning',
                'effectiveness' => 'High',
                'iht_saved' => null, // Depends on pension value
                'implementation_complexity' => 'Low',
                'description' => 'Pensions are outside your estate if beneficiaries are nominated',
                'specific_actions' => [
                    'Maximize pension contributions (£'.number_format((float) $this->taxConfig->getPensionAllowances()['annual_allowance'], 0).' annual allowance)',
                    'Use carry forward from previous 3 years if available',
                    'Nominate beneficiaries on all pension schemes',
                    'Consider drawdown instead of annuity (drawdown can pass to beneficiaries)',
                    'Delay taking pension if you have other income sources',
                    'Pension funds are IHT-free if you die before age 75',
                ],
            ];
        }

        // 7. Spend and Enjoy (often overlooked!)
        if ($effectiveIHTLiability > 50000) {
            $strategies[] = [
                'priority' => 7,
                'strategy_name' => 'Enjoy Your Wealth',
                'effectiveness' => 'High',
                'iht_saved' => null, // Variable
                'implementation_complexity' => 'Very Low',
                'description' => 'The simplest way to reduce IHT is to spend and enjoy your wealth',
                'specific_actions' => [
                    'Travel, experiences, and lifestyle improvements are IHT-free',
                    'Home improvements increase your quality of life (and may increase RNRB eligibility)',
                    'Support family members with education, weddings, or helping with house deposits',
                    'Consider gifting assets that will appreciate (e.g., property to children)',
                    'Remember: you cannot take it with you!',
                ],
            ];
        }

        // 8. Business Relief (if applicable)
        if ($effectiveIHTLiability > 100000) {
            $strategies[] = [
                'priority' => 8,
                'strategy_name' => 'Business Relief (BR) Investments',
                'effectiveness' => 'High',
                'iht_saved' => null, // Depends on investment amount
                'implementation_complexity' => 'Medium',
                'description' => 'Invest in BR-qualifying assets to reduce estate value (100% IHT relief after 2 years)',
                'specific_actions' => [
                    'Consider AIM-listed shares (many qualify for BR)',
                    'Invest in BR-qualifying investment funds',
                    'Hold for minimum 2 years to qualify',
                    'Higher risk - not suitable for everyone',
                    'Seek professional financial advice before investing',
                    'Note: Not a substitute for diversification',
                ],
            ];
        }

        // Sort by priority
        usort($strategies, fn ($a, $b) => $a['priority'] <=> $b['priority']);

        return $strategies;
    }
}
