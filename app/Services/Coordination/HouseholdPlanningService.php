<?php

declare(strict_types=1);

namespace App\Services\Coordination;

use App\Constants\EstateDefaults;
use App\Constants\TaxDefaults;
use App\Models\BusinessInterest;
use App\Models\CashAccount;
use App\Models\Chattel;
use App\Models\CriticalIllnessPolicy;
use App\Models\DBPension;
use App\Models\DCPension;
use App\Models\Estate\Liability;
use App\Models\Investment\InvestmentAccount;
use App\Models\LifeInsurancePolicy;
use App\Models\Mortgage;
use App\Models\Property;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\TaxConfigService;
use App\Traits\CalculatesOwnershipShare;

/**
 * Service for household-level financial planning.
 *
 * Aggregates assets/liabilities across both spouses, generates spousal
 * optimisation recommendations, and models death-of-spouse scenarios.
 *
 * Single-Record Architecture:
 * - Joint assets stored once with user_id + joint_owner_id
 * - ownership_percentage = primary owner's share
 * - Query: WHERE user_id = ? OR joint_owner_id = ?
 * - Uses CalculatesOwnershipShare trait to avoid double counting
 */
class HouseholdPlanningService
{
    use CalculatesOwnershipShare;

    public function __construct(
        private readonly TaxConfigService $taxConfig
    ) {}

    /**
     * Calculate household net worth across both spouses.
     *
     * Gathers all assets for user AND spouse (if data sharing enabled),
     * deducts liabilities, and handles joint assets correctly to avoid
     * double counting.
     *
     * @return array Household net worth breakdown
     */
    public function calculateHouseholdNetWorth(User $user): array
    {
        $spouse = $this->getLinkedSpouse($user);
        $dataSharingEnabled = $user->hasAcceptedSpousePermission();

        // Gather assets for user
        $userAssets = $this->gatherAssetsForUser($user);
        $userLiabilities = $this->gatherLiabilitiesForUser($user);

        $userTotalAssets = $userAssets['total'];
        $userTotalLiabilities = $userLiabilities['total'];
        $userNetWorth = $userTotalAssets - $userTotalLiabilities;

        // Gather assets for spouse if linked and sharing enabled
        $spouseTotalAssets = 0.0;
        $spouseTotalLiabilities = 0.0;
        $spouseNetWorth = 0.0;
        $spouseBreakdown = $this->emptyBreakdown();
        $spouseLiabilitiesBreakdown = $this->emptyLiabilitiesBreakdown();

        if ($spouse && $dataSharingEnabled) {
            $spouseAssets = $this->gatherAssetsForUser($spouse);
            $spouseLiab = $this->gatherLiabilitiesForUser($spouse);

            $spouseTotalAssets = $spouseAssets['total'];
            $spouseTotalLiabilities = $spouseLiab['total'];
            $spouseNetWorth = $spouseTotalAssets - $spouseTotalLiabilities;
            $spouseBreakdown = $spouseAssets['breakdown'];
            $spouseLiabilitiesBreakdown = $spouseLiab['breakdown'];
        }

        $totalAssets = $userTotalAssets + $spouseTotalAssets;
        $totalLiabilities = $userTotalLiabilities + $spouseTotalLiabilities;
        $netWorth = $totalAssets - $totalLiabilities;

        return [
            'total_assets' => round($totalAssets, 2),
            'total_liabilities' => round($totalLiabilities, 2),
            'net_worth' => round($netWorth, 2),
            'user_share' => round($userNetWorth, 2),
            'spouse_share' => round($spouseNetWorth, 2),
            'has_spouse' => $spouse !== null && $dataSharingEnabled,
            'user_name' => $user->first_name ?? 'You',
            'spouse_name' => $spouse?->first_name ?? 'Partner',
            'breakdown_by_type' => [
                'properties' => [
                    'user' => round($userAssets['breakdown']['properties'], 2),
                    'spouse' => round($spouseBreakdown['properties'], 2),
                    'total' => round($userAssets['breakdown']['properties'] + $spouseBreakdown['properties'], 2),
                ],
                'savings' => [
                    'user' => round($userAssets['breakdown']['savings'], 2),
                    'spouse' => round($spouseBreakdown['savings'], 2),
                    'total' => round($userAssets['breakdown']['savings'] + $spouseBreakdown['savings'], 2),
                ],
                'investments' => [
                    'user' => round($userAssets['breakdown']['investments'], 2),
                    'spouse' => round($spouseBreakdown['investments'], 2),
                    'total' => round($userAssets['breakdown']['investments'] + $spouseBreakdown['investments'], 2),
                ],
                'pensions' => [
                    'user' => round($userAssets['breakdown']['pensions'], 2),
                    'spouse' => round($spouseBreakdown['pensions'], 2),
                    'total' => round($userAssets['breakdown']['pensions'] + $spouseBreakdown['pensions'], 2),
                ],
                'business' => [
                    'user' => round($userAssets['breakdown']['business'], 2),
                    'spouse' => round($spouseBreakdown['business'], 2),
                    'total' => round($userAssets['breakdown']['business'] + $spouseBreakdown['business'], 2),
                ],
                'cash' => [
                    'user' => round($userAssets['breakdown']['cash'], 2),
                    'spouse' => round($spouseBreakdown['cash'], 2),
                    'total' => round($userAssets['breakdown']['cash'] + $spouseBreakdown['cash'], 2),
                ],
                'chattels' => [
                    'user' => round($userAssets['breakdown']['chattels'], 2),
                    'spouse' => round($spouseBreakdown['chattels'], 2),
                    'total' => round($userAssets['breakdown']['chattels'] + $spouseBreakdown['chattels'], 2),
                ],
            ],
            'liabilities_breakdown' => [
                'mortgages' => [
                    'user' => round($userLiabilities['breakdown']['mortgages'], 2),
                    'spouse' => round($spouseLiabilitiesBreakdown['mortgages'], 2),
                    'total' => round($userLiabilities['breakdown']['mortgages'] + $spouseLiabilitiesBreakdown['mortgages'], 2),
                ],
                'other' => [
                    'user' => round($userLiabilities['breakdown']['other'], 2),
                    'spouse' => round($spouseLiabilitiesBreakdown['other'], 2),
                    'total' => round($userLiabilities['breakdown']['other'] + $spouseLiabilitiesBreakdown['other'], 2),
                ],
            ],
        ];
    }

    /**
     * Generate spousal optimisation recommendations.
     *
     * Compares tax positions of both spouses and recommends asset transfers,
     * ISA splitting, and pension contribution balancing.
     *
     * @return array List of optimisation recommendations
     */
    public function generateSpousalOptimisations(User $user): array
    {
        $spouse = $this->getLinkedSpouse($user);
        $dataSharingEnabled = $user->hasAcceptedSpousePermission();

        if (! $spouse || ! $dataSharingEnabled) {
            return [];
        }

        $recommendations = [];

        // Compare income tax positions
        $userIncome = $this->calculateTotalIncome($user);
        $spouseIncome = $this->calculateTotalIncome($spouse);

        $incomeTaxConfig = $this->taxConfig->getIncomeTax();
        $personalAllowance = (float) ($incomeTaxConfig['personal_allowance'] ?? TaxDefaults::PERSONAL_ALLOWANCE);

        $userTaxBand = $this->determineTaxBand($userIncome, $personalAllowance);
        $spouseTaxBand = $this->determineTaxBand($spouseIncome, $personalAllowance);

        // ISA allowance optimisation
        $isaConfig = $this->taxConfig->getISAAllowances();
        $isaAllowance = (float) ($isaConfig['annual_allowance'] ?? TaxDefaults::ISA_ALLOWANCE);
        $isaRecommendation = $this->generateISARecommendation($user, $spouse, $isaAllowance);
        if ($isaRecommendation) {
            $recommendations[] = $isaRecommendation;
        }

        // Pension contribution balancing
        $pensionRecommendation = $this->generatePensionRecommendation(
            $user,
            $spouse,
            $userIncome,
            $spouseIncome,
            $userTaxBand,
            $spouseTaxBand
        );
        if ($pensionRecommendation) {
            $recommendations[] = $pensionRecommendation;
        }

        // Asset transfer for lower-rate spouse
        if ($userTaxBand !== $spouseTaxBand) {
            $transferRecommendation = $this->generateTransferRecommendation(
                $user,
                $spouse,
                $userIncome,
                $spouseIncome,
                $userTaxBand,
                $spouseTaxBand,
                $personalAllowance
            );
            if ($transferRecommendation) {
                $recommendations[] = $transferRecommendation;
            }
        }

        // Marriage allowance recommendation
        $marriageAllowanceRec = $this->generateMarriageAllowanceRecommendation(
            $user,
            $spouse,
            $userIncome,
            $spouseIncome,
            $personalAllowance
        );
        if ($marriageAllowanceRec) {
            $recommendations[] = $marriageAllowanceRec;
        }

        return $recommendations;
    }

    /**
     * Model the financial impact if a spouse dies.
     *
     * Calculates estate impact including NRB/RNRB transfers, joint asset
     * treatment, pension death benefits, and life insurance payouts.
     *
     * @param  string  $whichSpouse  'primary' or 'partner'
     * @return array Death scenario analysis
     */
    public function modelDeathOfSpouseScenario(User $user, string $whichSpouse = 'primary'): array
    {
        $spouse = $this->getLinkedSpouse($user);
        $dataSharingEnabled = $user->hasAcceptedSpousePermission();

        if (! $spouse || ! $dataSharingEnabled) {
            return $this->singlePersonScenario($user);
        }

        $deceased = $whichSpouse === 'primary' ? $user : $spouse;
        $survivor = $whichSpouse === 'primary' ? $spouse : $user;

        $ihtConfig = $this->taxConfig->getInheritanceTax();
        $nrb = (float) ($ihtConfig['nil_rate_band'] ?? TaxDefaults::NRB);
        $rnrb = (float) ($ihtConfig['residence_nil_rate_band'] ?? TaxDefaults::RNRB);
        $ihtRate = (float) ($ihtConfig['rate'] ?? 0.40);

        // Gather deceased's assets
        $deceasedAssets = $this->gatherAssetsForUser($deceased);
        $deceasedLiabilities = $this->gatherLiabilitiesForUser($deceased);

        // Joint assets pass to survivor (joint tenancy)
        $jointAssetsPassingToSurvivor = $this->calculateJointAssetsPassingToSurvivor($deceased, $survivor);

        // Spouse exemption: assets passing to surviving spouse are IHT-exempt
        // For married couples, transfers between spouses are fully exempt
        $estatePassingToSpouse = $deceasedAssets['total'] - $deceasedLiabilities['total'];
        $ihtLiabilityFirstDeath = 0.0; // Spousal exemption means no IHT on first death

        // NRB/RNRB transfers to surviving spouse on first death
        $nrbTransferred = $nrb; // Full NRB transfers if not used
        $hasMainResidence = $deceased->properties()->where('property_type', 'main_residence')->exists();
        $hasDirectDescendants = $deceased->familyMembers()->whereIn('relationship', ['child', 'grandchild', 'step_child'])->exists();
        $qualifiesForRNRB = $hasMainResidence && $hasDirectDescendants;
        $rnrbTransferred = $qualifiesForRNRB ? $rnrb : 0.0; // RNRB transfers only if main residence passes to direct descendants

        // Survivor's position after first death
        $survivorAssets = $this->gatherAssetsForUser($survivor);
        $survivorLiabilities = $this->gatherLiabilitiesForUser($survivor);

        // Survivor inherits deceased's assets (minus any to other beneficiaries)
        $survivorTotalAssets = $survivorAssets['total'] + $estatePassingToSpouse;
        $survivorTotalLiabilities = $survivorLiabilities['total'];
        $survivorNetPosition = $survivorTotalAssets - $survivorTotalLiabilities;

        // Pension death benefits
        $pensionDeathBenefits = $this->calculatePensionDeathBenefits($deceased);

        // Life insurance payouts
        $lifeInsurancePayouts = $this->calculateLifeInsurancePayouts($deceased);

        // Estimate income impact
        $deceasedIncome = $this->calculateTotalIncome($deceased);
        $survivorIncome = $this->calculateTotalIncome($survivor);

        // DB pension spouse benefit (typically 50% of scheme pension)
        $dbSpouseBenefit = $this->calculateDBPensionSpouseBenefit($deceased);

        // State pension - deceased's is lost, survivor keeps their own
        // (Survivor may inherit some state pension in certain circumstances)

        $incomeAfterDeath = $survivorIncome + $dbSpouseBenefit;
        $incomeLost = $deceasedIncome - $dbSpouseBenefit;

        // Protection gaps
        $protectionGaps = $this->identifyProtectionGaps(
            $deceased,
            $survivor,
            $deceasedIncome,
            $lifeInsurancePayouts
        );

        // IHT on second death (survivor's combined estate)
        $combinedNrb = $nrb + $nrbTransferred; // Survivor gets transferred NRB
        $combinedRnrb = $rnrb + $rnrbTransferred;
        $totalAllowances = $combinedNrb + $combinedRnrb;

        // Pensions outside estate for IHT
        $survivorPensionValue = $this->calculateDCPensionValue($survivor);
        $inheritedPensionValue = $pensionDeathBenefits['dc_total'];

        // Net taxable estate (excluding pensions which are outside estate)
        $taxableEstate = max(0, $survivorNetPosition
            + $lifeInsurancePayouts['not_in_trust']
            - $survivorPensionValue
            - $inheritedPensionValue
            - $totalAllowances);

        $ihtOnSecondDeath = $taxableEstate * $ihtRate;

        return [
            'scenario' => $whichSpouse === 'primary' ? 'If you pass away' : 'If your partner passes away',
            'deceased_name' => $deceased->first_name ?? 'Deceased',
            'survivor_name' => $survivor->first_name ?? 'Survivor',
            'surviving_spouse_assets' => round($survivorTotalAssets, 2),
            'surviving_spouse_liabilities' => round($survivorTotalLiabilities, 2),
            'surviving_spouse_net_position' => round($survivorNetPosition, 2),
            'iht_first_death' => round($ihtLiabilityFirstDeath, 2),
            'iht_second_death' => round($ihtOnSecondDeath, 2),
            'nrb_transferred' => round($nrbTransferred, 2),
            'rnrb_transferred' => round($rnrbTransferred, 2),
            'total_allowances_on_second_death' => round($totalAllowances, 2),
            'taxable_estate_on_second_death' => round($taxableEstate, 2),
            'joint_assets_passing_to_survivor' => round($jointAssetsPassingToSurvivor, 2),
            'pension_death_benefits' => [
                'dc_total' => round($pensionDeathBenefits['dc_total'], 2),
                'db_spouse_benefit_annual' => round($dbSpouseBenefit, 2),
                'details' => $pensionDeathBenefits['details'],
            ],
            'life_insurance' => [
                'total_payout' => round($lifeInsurancePayouts['total'], 2),
                'in_trust' => round($lifeInsurancePayouts['in_trust'], 2),
                'not_in_trust' => round($lifeInsurancePayouts['not_in_trust'], 2),
            ],
            'income_impact' => [
                'income_before' => round($deceasedIncome + $survivorIncome, 2),
                'income_after' => round($incomeAfterDeath, 2),
                'income_lost' => round($incomeLost, 2),
                'db_spouse_benefit' => round($dbSpouseBenefit, 2),
            ],
            'protection_gaps' => $protectionGaps,
        ];
    }

    // =========================================================================
    // Private Helper Methods
    // =========================================================================

    /**
     * Get the linked spouse if exists.
     */
    private function getLinkedSpouse(User $user): ?User
    {
        if (! $user->spouse_id) {
            return null;
        }

        return $user->relationLoaded('spouse')
            ? $user->spouse
            : $user->spouse()->first();
    }

    /**
     * Gather all assets for a single user, calculating their ownership share.
     *
     * @return array{total: float, breakdown: array}
     */
    private function gatherAssetsForUser(User $user): array
    {
        $userId = $user->id;

        // Properties
        $properties = Property::forUserOrJoint($userId)
            ->get();
        $propertyValue = $properties->sum(fn ($p) => $this->calculateUserShare($p, $userId));

        // Savings accounts
        $savings = SavingsAccount::forUserOrJoint($userId)
            ->get();
        $savingsValue = $savings->sum(fn ($s) => $this->calculateUserShare($s, $userId));

        // Investment accounts
        $investments = InvestmentAccount::forUserOrJoint($userId)
            ->get();
        $investmentValue = $investments->sum(fn ($i) => $this->calculateUserShare($i, $userId));

        // DC Pensions (individual only)
        $dcPensions = DCPension::where('user_id', $userId)->get();
        $pensionValue = $dcPensions->sum('current_fund_value');

        // Business interests
        $businesses = BusinessInterest::forUserOrJoint($userId)
            ->get();
        $businessValue = $businesses->sum(fn ($b) => $this->calculateUserShare($b, $userId));

        // Cash accounts
        $cashAccounts = CashAccount::forUserOrJoint($userId)
            ->get();
        $cashValue = $cashAccounts->sum(fn ($c) => $this->calculateUserShare($c, $userId));

        // Chattels
        $chattels = Chattel::forUserOrJoint($userId)
            ->get();
        $chattelValue = $chattels->sum(fn ($ch) => $this->calculateUserShare($ch, $userId));

        $total = $propertyValue + $savingsValue + $investmentValue + $pensionValue
            + $businessValue + $cashValue + $chattelValue;

        return [
            'total' => $total,
            'breakdown' => [
                'properties' => $propertyValue,
                'savings' => $savingsValue,
                'investments' => $investmentValue,
                'pensions' => $pensionValue,
                'business' => $businessValue,
                'cash' => $cashValue,
                'chattels' => $chattelValue,
            ],
        ];
    }

    /**
     * Gather all liabilities for a single user, calculating their ownership share.
     *
     * @return array{total: float, breakdown: array}
     */
    private function gatherLiabilitiesForUser(User $user): array
    {
        $userId = $user->id;

        // Mortgages
        $mortgages = Mortgage::forUserOrJoint($userId)
            ->get();
        $mortgageTotal = $mortgages->sum(fn ($m) => $this->calculateUserMortgageShare($m, $userId));

        // Other liabilities (loans, credit cards, etc.)
        $liabilities = Liability::forUserOrJoint($userId)
            ->get();
        $otherTotal = $liabilities->sum(fn ($l) => $this->calculateUserShare($l, $userId));

        return [
            'total' => $mortgageTotal + $otherTotal,
            'breakdown' => [
                'mortgages' => $mortgageTotal,
                'other' => $otherTotal,
            ],
        ];
    }

    /**
     * Calculate total annual income for a user.
     */
    private function calculateTotalIncome(User $user): float
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
     * Determine income tax band based on total income.
     */
    private function determineTaxBand(float $income, float $personalAllowance): string
    {
        $incomeTaxConfig = $this->taxConfig->getIncomeTax();
        $basicRateBand = (float) ($incomeTaxConfig['bands'][0]['max'] ?? 37700);
        $additionalRateThreshold = (float) ($incomeTaxConfig['bands'][1]['upper_limit'] ?? 125140);

        $taxable = max(0, $income - $personalAllowance);

        if ($taxable === 0.0) {
            return 'none';
        }
        if ($taxable <= $basicRateBand) {
            return 'basic';
        }
        if ($income <= $additionalRateThreshold) {
            return 'higher';
        }

        return 'additional';
    }

    /**
     * Get the marginal tax rate for a given band from TaxConfigService.
     */
    private function getMarginalRate(string $band): float
    {
        $incomeTaxConfig = $this->taxConfig->getIncomeTax();
        $bands = $incomeTaxConfig['bands'] ?? [];

        $basicRate = (float) ($bands[0]['rate'] ?? 0.20);
        $higherRate = (float) ($bands[1]['rate'] ?? 0.40);
        $additionalRate = (float) ($bands[2]['rate'] ?? 0.45);

        return match ($band) {
            'none' => 0.0,
            'basic' => $basicRate,
            'higher' => $higherRate,
            'additional' => $additionalRate,
            default => $basicRate,
        };
    }

    /**
     * Generate ISA allowance splitting recommendation.
     */
    private function generateISARecommendation(User $user, User $spouse, float $isaAllowance): ?array
    {
        $userISAUsed = $this->calculateISAUsage($user);
        $spouseISAUsed = $this->calculateISAUsage($spouse);
        $totalAllowance = $isaAllowance * 2;
        $totalUsed = $userISAUsed + $spouseISAUsed;
        $unused = $totalAllowance - $totalUsed;

        if ($unused <= 0) {
            return null;
        }

        $userUnused = $isaAllowance - $userISAUsed;
        $spouseUnused = $isaAllowance - $spouseISAUsed;

        if ($userUnused <= 0 && $spouseUnused <= 0) {
            return null;
        }

        $lowerName = $userUnused > $spouseUnused
            ? ($user->first_name ?? 'you')
            : ($spouse->first_name ?? 'your partner');

        return [
            'type' => 'isa_allowance',
            'description' => "Your household has {$this->formatCurrencyValue($unused)} of unused ISA allowance this tax year. Consider maximising {$lowerName}'s ISA contributions for tax-free growth.",
            'potential_savings' => round($unused * 0.02 * 0.20, 2), // Estimated tax saving on 2% return at basic rate
            'action' => 'Review ISA contributions for both partners to maximise the combined annual allowance.',
        ];
    }

    /**
     * Calculate ISA usage for a user in the current tax year.
     */
    private function calculateISAUsage(User $user): float
    {
        $savingsISA = SavingsAccount::where('user_id', $user->id)
            ->where('is_isa', true)
            ->sum('isa_subscription_amount');

        $investmentISA = InvestmentAccount::where('user_id', $user->id)
            ->where('account_type', 'isa')
            ->sum('isa_subscription_current_year');

        return (float) $savingsISA + (float) $investmentISA;
    }

    /**
     * Generate pension contribution balancing recommendation.
     */
    private function generatePensionRecommendation(
        User $user,
        User $spouse,
        float $userIncome,
        float $spouseIncome,
        string $userTaxBand,
        string $spouseTaxBand
    ): ?array {
        // Only recommend if one spouse is in a higher band
        if ($userTaxBand === $spouseTaxBand) {
            return null;
        }

        $higherRateSpouse = $this->getMarginalRate($userTaxBand) > $this->getMarginalRate($spouseTaxBand)
            ? $user : $spouse;
        $higherBand = $this->getMarginalRate($userTaxBand) > $this->getMarginalRate($spouseTaxBand)
            ? $userTaxBand : $spouseTaxBand;
        $lowerBand = $this->getMarginalRate($userTaxBand) > $this->getMarginalRate($spouseTaxBand)
            ? $spouseTaxBand : $userTaxBand;

        $higherRate = $this->getMarginalRate($higherBand);
        $lowerRate = $this->getMarginalRate($lowerBand);
        $rateDifference = $higherRate - $lowerRate;

        if ($rateDifference <= 0) {
            return null;
        }

        $higherName = $higherRateSpouse->first_name ?? 'the higher earner';

        return [
            'type' => 'pension_contribution',
            'description' => "Maximising pension contributions for {$higherName} provides tax relief at ".round($higherRate * 100).'% rather than '.round($lowerRate * 100)."%. This is more tax-efficient than contributing to the lower earner's pension.",
            'potential_savings' => round(10000 * $rateDifference, 2), // Saving on notional £10k contribution
            'action' => "Prioritise pension contributions for {$higherName} to maximise tax relief.",
        ];
    }

    /**
     * Generate asset transfer recommendation for lower-rate spouse.
     */
    private function generateTransferRecommendation(
        User $user,
        User $spouse,
        float $userIncome,
        float $spouseIncome,
        string $userTaxBand,
        string $spouseTaxBand,
        float $personalAllowance
    ): ?array {
        $higherEarner = $userIncome > $spouseIncome ? $user : $spouse;
        $lowerEarner = $userIncome > $spouseIncome ? $spouse : $user;
        $lowerIncome = min($userIncome, $spouseIncome);
        $higherBand = $userIncome > $spouseIncome ? $userTaxBand : $spouseTaxBand;
        $lowerBand = $userIncome > $spouseIncome ? $spouseTaxBand : $userTaxBand;

        $higherRate = $this->getMarginalRate($higherBand);
        $lowerRate = $this->getMarginalRate($lowerBand);

        if ($higherRate <= $lowerRate) {
            return null;
        }

        // Calculate income-generating assets of higher earner
        $dividendIncome = (float) ($higherEarner->annual_dividend_income ?? 0);
        $interestIncome = (float) ($higherEarner->annual_interest_income ?? 0);
        $rentalIncome = (float) ($higherEarner->annual_rental_income ?? 0);
        $transferableIncome = $dividendIncome + $interestIncome;

        if ($transferableIncome <= 0) {
            return null;
        }

        // How much can be transferred before lower earner enters higher band
        $incomeTaxConfig = $this->taxConfig->getIncomeTax();
        $basicRateBand = (float) ($incomeTaxConfig['bands'][0]['max'] ?? 37700);
        $lowerTaxable = max(0, $lowerIncome - $personalAllowance);
        $headroom = max(0, $basicRateBand - $lowerTaxable); // Headroom before higher rate

        $transferAmount = min($transferableIncome, $headroom);
        $savingPerPound = $higherRate - $lowerRate;
        $estimatedSaving = $transferAmount * $savingPerPound;

        if ($estimatedSaving < 100) {
            return null; // Not worth recommending for small amounts
        }

        $lowerName = $lowerEarner->first_name ?? 'the lower earner';

        return [
            'type' => 'asset_transfer',
            'description' => "Transferring income-generating assets to {$lowerName} could save up to {$this->formatCurrencyValue($estimatedSaving)} per year in income tax, as they are in a lower tax band.",
            'potential_savings' => round($estimatedSaving, 2),
            'action' => "Consider transferring savings or investment assets to {$lowerName} to utilise their lower tax rate.",
        ];
    }

    /**
     * Generate marriage allowance recommendation.
     */
    private function generateMarriageAllowanceRecommendation(
        User $user,
        User $spouse,
        float $userIncome,
        float $spouseIncome,
        float $personalAllowance
    ): ?array {
        // Marriage allowance: one spouse can transfer 10% of PA if they are non-taxpayer
        // and the other is a basic rate taxpayer
        $transferAmount = floor($personalAllowance * 0.10);

        $nonTaxpayer = null;
        $basicRate = null;

        $incomeTaxConfig = $this->taxConfig->getIncomeTax();
        $basicRateBand = (float) ($incomeTaxConfig['bands'][0]['max'] ?? 37700);

        if ($userIncome <= $personalAllowance && $spouseIncome > $personalAllowance && $spouseIncome <= ($personalAllowance + $basicRateBand)) {
            $nonTaxpayer = $user;
            $basicRate = $spouse;
        } elseif ($spouseIncome <= $personalAllowance && $userIncome > $personalAllowance && $userIncome <= ($personalAllowance + $basicRateBand)) {
            $nonTaxpayer = $spouse;
            $basicRate = $user;
        }

        if (! $nonTaxpayer || ! $basicRate) {
            return null;
        }

        $basicRate = $this->getMarginalRate('basic');
        $saving = $transferAmount * $basicRate; // Basic rate tax saving on transferred allowance
        $nonTaxpayerName = $nonTaxpayer->first_name ?? 'the non-taxpayer';
        $basicRateName = $basicRate->first_name ?? 'the basic rate taxpayer';

        return [
            'type' => 'marriage_allowance',
            'description' => "{$nonTaxpayerName} could transfer {$this->formatCurrencyValue($transferAmount)} of their Personal Allowance to {$basicRateName}, saving {$this->formatCurrencyValue($saving)} per year in income tax.",
            'potential_savings' => round($saving, 2),
            'action' => 'Apply for Marriage Allowance transfer via HMRC.',
        ];
    }

    /**
     * Calculate joint assets that pass directly to survivor (joint tenancy).
     */
    private function calculateJointAssetsPassingToSurvivor(User $deceased, User $survivor): float
    {
        $total = 0.0;
        $deceasedId = $deceased->id;
        $survivorId = $survivor->id;

        // Check all joint asset types
        $assetTypes = [
            Property::class,
            SavingsAccount::class,
            InvestmentAccount::class,
            CashAccount::class,
            Chattel::class,
        ];

        foreach ($assetTypes as $modelClass) {
            $jointAssets = $modelClass::where(function ($query) use ($deceasedId, $survivorId) {
                $query->where('user_id', $deceasedId)->where('joint_owner_id', $survivorId);
            })->orWhere(function ($query) use ($deceasedId, $survivorId) {
                $query->where('user_id', $survivorId)->where('joint_owner_id', $deceasedId);
            })->where('ownership_type', 'joint')
                ->get();

            foreach ($jointAssets as $asset) {
                // Deceased's share passes to survivor
                $total += $this->calculateUserShare($asset, $deceasedId);
            }
        }

        return $total;
    }

    /**
     * Calculate DC pension death benefits for deceased.
     *
     * @return array{dc_total: float, details: array}
     */
    private function calculatePensionDeathBenefits(User $deceased): array
    {
        $dcPensions = DCPension::where('user_id', $deceased->id)->get();
        $details = [];
        $total = 0.0;

        foreach ($dcPensions as $pension) {
            $value = (float) $pension->current_fund_value;
            $total += $value;
            $details[] = [
                'scheme' => $pension->scheme_name,
                'value' => round($value, 2),
                'type' => 'Defined Contribution',
            ];
        }

        return [
            'dc_total' => $total,
            'details' => $details,
        ];
    }

    /**
     * Calculate DB pension spouse benefit (typically 50% of scheme pension).
     */
    private function calculateDBPensionSpouseBenefit(User $deceased): float
    {
        $dbPensions = DBPension::where('user_id', $deceased->id)->get();
        $total = 0.0;

        foreach ($dbPensions as $pension) {
            $spousePercent = (float) ($pension->spouse_pension_percent ?? 50);
            $annualPension = (float) ($pension->expected_annual_pension ?? 0);
            $total += $annualPension * ($spousePercent / 100);
        }

        return $total;
    }

    /**
     * Calculate life insurance payouts, split by in-trust vs not-in-trust.
     *
     * @return array{total: float, in_trust: float, not_in_trust: float}
     */
    private function calculateLifeInsurancePayouts(User $deceased): array
    {
        $policies = LifeInsurancePolicy::where('user_id', $deceased->id)->get();
        $inTrust = 0.0;
        $notInTrust = 0.0;

        foreach ($policies as $policy) {
            $sumAssured = (float) $policy->sum_assured;
            if ($policy->in_trust) {
                $inTrust += $sumAssured;
            } else {
                $notInTrust += $sumAssured;
            }
        }

        // Also include critical illness if terminal
        $criticalPolicies = CriticalIllnessPolicy::where('user_id', $deceased->id)->get();
        foreach ($criticalPolicies as $policy) {
            $sumAssured = (float) $policy->sum_assured;
            if ($policy->in_trust ?? false) {
                $inTrust += $sumAssured;
            } else {
                $notInTrust += $sumAssured;
            }
        }

        return [
            'total' => $inTrust + $notInTrust,
            'in_trust' => $inTrust,
            'not_in_trust' => $notInTrust,
        ];
    }

    /**
     * Calculate total DC pension value for a user.
     */
    private function calculateDCPensionValue(User $user): float
    {
        return (float) DCPension::where('user_id', $user->id)->sum('current_fund_value');
    }

    /**
     * Identify protection gaps for the surviving spouse.
     */
    private function identifyProtectionGaps(
        User $deceased,
        User $survivor,
        float $deceasedIncome,
        array $lifeInsurancePayouts
    ): array {
        $gaps = [];

        // Income replacement gap
        $totalPayout = $lifeInsurancePayouts['total'];
        $yearsOfIncomeNeeded = 10; // Typical planning horizon
        $incomeReplacementNeeded = $deceasedIncome * $yearsOfIncomeNeeded;

        if ($totalPayout < $incomeReplacementNeeded && $deceasedIncome > 0) {
            $shortfall = $incomeReplacementNeeded - $totalPayout;
            $gaps[] = [
                'type' => 'income_replacement',
                'description' => "Life insurance covers {$this->formatCurrencyValue($totalPayout)} but {$this->formatCurrencyValue($incomeReplacementNeeded)} may be needed to replace income for {$yearsOfIncomeNeeded} years.",
                'shortfall' => round($shortfall, 2),
            ];
        }

        // Mortgage protection
        $mortgages = Mortgage::forUserOrJoint($deceased->id)
            ->get();
        $totalMortgage = $mortgages->sum('outstanding_balance');

        if ($totalMortgage > 0) {
            $mortgageProtection = LifeInsurancePolicy::where('user_id', $deceased->id)
                ->where('is_mortgage_protection', true)
                ->sum('sum_assured');

            if ($mortgageProtection < $totalMortgage) {
                $gaps[] = [
                    'type' => 'mortgage_protection',
                    'description' => "Outstanding mortgage of {$this->formatCurrencyValue($totalMortgage)} may not be fully covered by dedicated mortgage protection.",
                    'shortfall' => round((float) $totalMortgage - (float) $mortgageProtection, 2),
                ];
            }
        }

        // No life insurance at all
        if ($totalPayout === 0.0 && $deceasedIncome > 0) {
            $gaps[] = [
                'type' => 'no_life_insurance',
                'description' => 'No life insurance in place. The surviving partner would lose access to this income with no insurance payout.',
                'shortfall' => round($deceasedIncome * $yearsOfIncomeNeeded, 2),
            ];
        }

        return $gaps;
    }

    /**
     * Build scenario response for a single user (no spouse).
     */
    private function singlePersonScenario(User $user): array
    {
        $assets = $this->gatherAssetsForUser($user);
        $liabilities = $this->gatherLiabilitiesForUser($user);

        $ihtConfig = $this->taxConfig->getInheritanceTax();
        $nrb = (float) ($ihtConfig['nil_rate_band'] ?? TaxDefaults::NRB);
        $rnrb = (float) ($ihtConfig['residence_nil_rate_band'] ?? TaxDefaults::RNRB);
        $ihtRate = (float) ($ihtConfig['rate'] ?? 0.40);

        $hasMainResidence = $user->properties()->where('property_type', 'main_residence')->exists();
        $hasDirectDescendants = $user->familyMembers()->whereIn('relationship', ['child', 'grandchild', 'step_child'])->exists();
        $qualifiesForRNRB = $hasMainResidence && $hasDirectDescendants;
        $effectiveRNRB = $qualifiesForRNRB ? $rnrb : 0.0;
        $netEstate = $assets['total'] - $liabilities['total'];
        $taperThreshold = (float) ($ihtConfig['rnrb_taper_threshold'] ?? EstateDefaults::RNRB_TAPER_THRESHOLD);
        if ($qualifiesForRNRB && $netEstate > $taperThreshold) {
            $taperReduction = ($netEstate - $taperThreshold) / 2;
            $effectiveRNRB = max(0.0, $effectiveRNRB - $taperReduction);
        }
        $totalAllowances = $nrb + $effectiveRNRB;
        $taxable = max(0, $netEstate - $totalAllowances);
        $iht = $taxable * $ihtRate;

        return [
            'scenario' => 'Individual estate analysis',
            'deceased_name' => $user->first_name ?? 'You',
            'survivor_name' => null,
            'surviving_spouse_assets' => 0.0,
            'surviving_spouse_liabilities' => 0.0,
            'surviving_spouse_net_position' => 0.0,
            'iht_first_death' => round($iht, 2),
            'iht_second_death' => 0.0,
            'nrb_transferred' => 0.0,
            'rnrb_transferred' => 0.0,
            'total_allowances_on_second_death' => 0.0,
            'taxable_estate_on_second_death' => 0.0,
            'joint_assets_passing_to_survivor' => 0.0,
            'pension_death_benefits' => [
                'dc_total' => 0.0,
                'db_spouse_benefit_annual' => 0.0,
                'details' => [],
            ],
            'life_insurance' => [
                'total_payout' => 0.0,
                'in_trust' => 0.0,
                'not_in_trust' => 0.0,
            ],
            'income_impact' => [
                'income_before' => 0.0,
                'income_after' => 0.0,
                'income_lost' => 0.0,
                'db_spouse_benefit' => 0.0,
            ],
            'protection_gaps' => [],
        ];
    }

    /**
     * Format a currency value as a string.
     */
    private function formatCurrencyValue(float $value): string
    {
        if ($value >= 1000) {
            return '£'.number_format($value, 0);
        }

        return '£'.number_format($value, 2);
    }

    /**
     * Return an empty asset breakdown.
     */
    private function emptyBreakdown(): array
    {
        return [
            'properties' => 0.0,
            'savings' => 0.0,
            'investments' => 0.0,
            'pensions' => 0.0,
            'business' => 0.0,
            'cash' => 0.0,
            'chattels' => 0.0,
        ];
    }

    /**
     * Return an empty liabilities breakdown.
     */
    private function emptyLiabilitiesBreakdown(): array
    {
        return [
            'mortgages' => 0.0,
            'other' => 0.0,
        ];
    }
}
