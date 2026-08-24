<?php

declare(strict_types=1);

namespace App\Services\Estate;

use App\Models\Estate\Liability;
use App\Models\User;
use App\Services\Stores\MortgageStore;
use App\Support\HouseholdPooling;
use App\Traits\CalculatesOwnershipShare;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Service for formatting IHT calculation results for API responses.
 *
 * Extracted from IHTController to improve maintainability and testability.
 */
class IHTFormattingService
{
    use CalculatesOwnershipShare;

    public function __construct(
        private readonly MortgageStore $mortgageStore,
        private readonly HouseholdCashFlowProjector $cashFlowProjector,
    ) {}

    /**
     * Format assets breakdown for response.
     *
     * Uses asset-specific projection methods from IHTCalculationService:
     * - Cash: Income/expense surplus model (service provides total)
     * - Investments: Monte Carlo (80% confidence) or custom rate
     * - Properties: Configurable growth rate (default 3%)
     *
     * @param  Collection  $userAssets  User's assets collection
     * @param  Collection|null  $spouseAssets  Spouse's assets collection (if data sharing enabled)
     * @param  bool  $includeSpouse  Whether to include spouse assets
     * @param  User|null  $user  The primary user
     * @param  User|null  $spouse  The spouse (if linked)
     * @param  array  $calculation  IHT calculation results containing projection data
     */
    public function formatAssetsBreakdown(
        Collection $userAssets,
        ?Collection $spouseAssets = null,
        bool $includeSpouse = false,
        ?User $user = null,
        ?User $spouse = null,
        array $calculation = []
    ): array {
        // Get asset-specific projected totals from calculation service
        $projectedCash = $calculation['projected_cash'] ?? 0;
        $projectedInvestments = $calculation['projected_investments'] ?? 0;
        $projectedProperties = $calculation['projected_properties'] ?? 0;

        // Calculate current totals by asset type to determine projection factors
        $currentCashTotal = 0;
        $currentInvestmentTotal = 0;
        $currentPropertyTotal = 0;

        foreach ($userAssets as $asset) {
            if ($asset->is_iht_exempt || $asset->current_value <= 0) {
                continue;
            }
            match ($asset->asset_type) {
                'cash' => $currentCashTotal += $asset->current_value,
                'investment' => $currentInvestmentTotal += $asset->current_value,
                'property' => $currentPropertyTotal += $asset->current_value,
                default => null,
            };
        }

        if ($includeSpouse && $spouseAssets) {
            foreach ($spouseAssets as $asset) {
                if ($asset->is_iht_exempt || $asset->current_value <= 0) {
                    continue;
                }
                match ($asset->asset_type) {
                    'cash' => $currentCashTotal += $asset->current_value,
                    'investment' => $currentInvestmentTotal += $asset->current_value,
                    'property' => $currentPropertyTotal += $asset->current_value,
                    default => null,
                };
            }
        }

        // Calculate projection factors for each asset type
        // Cash uses surplus model - distribute projected total proportionally
        $cashProjectionFactor = $currentCashTotal > 0 ? $projectedCash / $currentCashTotal : 1;
        $investmentProjectionFactor = $currentInvestmentTotal > 0 ? $projectedInvestments / $currentInvestmentTotal : 1;
        $propertyProjectionFactor = $currentPropertyTotal > 0 ? $projectedProperties / $currentPropertyTotal : 1;

        $userAssetsForIHT = $this->initializeAssetCategories();
        $userAssetsTotal = 0;
        $userAssetsProjectedTotal = 0;

        // Process user assets
        foreach ($userAssets as $asset) {
            if ($asset->is_iht_exempt || $asset->current_value <= 0) {
                continue;
            }

            if (in_array($asset->asset_type, ['investment', 'property', 'cash', 'business', 'chattel'])) {
                $isJoint = ($asset->ownership_type ?? 'individual') === 'joint';
                $displayValue = $asset->current_value;

                // Use asset-specific projection factor
                // Chattels and business assets stay at current value (no reliable appreciation)
                $projectedValue = $this->calculateProjectedValue(
                    $asset->asset_type,
                    $displayValue,
                    $cashProjectionFactor,
                    $investmentProjectionFactor,
                    $propertyProjectionFactor
                );

                $userAssetsForIHT[$asset->asset_type][] = [
                    'name' => $asset->asset_name,
                    'value' => $displayValue,
                    'projected_value' => $projectedValue,
                    'is_joint' => $isJoint,
                    'ownership_type' => $asset->ownership_type,
                    'ownership_percentage' => $asset->ownership_percentage ?? 100,
                ];
                $userAssetsTotal += $displayValue;
                $userAssetsProjectedTotal += $projectedValue;
            }
        }

        $userName = $user ? $this->formatUserName($user) : 'User';

        $breakdown = [
            'user' => [
                'name' => $userName,
                'assets' => $userAssetsForIHT,
                'total' => $userAssetsTotal,
                'projected_total' => $userAssetsProjectedTotal,
            ],
            'spouse' => null,
        ];

        // Add spouse assets if applicable
        if ($includeSpouse && $spouseAssets && $spouseAssets->isNotEmpty()) {
            $spouseAssetsForIHT = $this->initializeAssetCategories();
            $spouseAssetsTotal = 0;
            $spouseAssetsProjectedTotal = 0;

            foreach ($spouseAssets as $asset) {
                if ($asset->is_iht_exempt || $asset->current_value <= 0) {
                    continue;
                }

                if (in_array($asset->asset_type, ['investment', 'property', 'cash', 'business', 'chattel'])) {
                    $isJoint = ($asset->ownership_type ?? 'individual') === 'joint';
                    $displayValue = $asset->current_value;

                    $projectedValue = $this->calculateProjectedValue(
                        $asset->asset_type,
                        $displayValue,
                        $cashProjectionFactor,
                        $investmentProjectionFactor,
                        $propertyProjectionFactor
                    );

                    $spouseAssetsForIHT[$asset->asset_type][] = [
                        'name' => $asset->asset_name,
                        'value' => $displayValue,
                        'projected_value' => $projectedValue,
                        'is_joint' => $isJoint,
                        'ownership_type' => $asset->ownership_type,
                        'ownership_percentage' => $asset->ownership_percentage ?? 100,
                    ];
                    $spouseAssetsTotal += $displayValue;
                    $spouseAssetsProjectedTotal += $projectedValue;
                }
            }

            $spouseName = $spouse ? $this->formatUserName($spouse) : 'Spouse';

            $breakdown['spouse'] = [
                'name' => $spouseName,
                'assets' => $spouseAssetsForIHT,
                'total' => $spouseAssetsTotal,
                'projected_total' => $spouseAssetsProjectedTotal,
            ];
        }

        return $breakdown;
    }

    /**
     * Format liabilities breakdown for response.
     *
     * IMPORTANT: Mortgages are assumed to be paid off by age 70.
     */
    public function formatLiabilitiesBreakdown(
        User $user,
        ?User $spouse = null,
        bool $includeSpouse = false
    ): array {
        // Get mortgages where user is primary owner OR joint owner (joint-aware via MortgageStore)
        $userMortgages = $this->mortgageStore->forUser($user)->load('property');
        $userLiabilities = Liability::where('user_id', $user->id)->get();

        // Calculate user age at death for mortgage projections
        $userAge = $user->date_of_birth ? Carbon::parse($user->date_of_birth)->age : 50;
        $yearsToProjectedDeath = max(0, 85 - $userAge);
        $userAgeAtDeath = $userAge + $yearsToProjectedDeath;

        $userBreakdown = $this->formatUserLiabilities(
            $userMortgages,
            $userLiabilities,
            $user,
            $userAgeAtDeath
        );

        $breakdown = [
            'user' => array_merge(
                ['name' => $this->formatUserName($user)],
                $userBreakdown
            ),
            'spouse' => null,
        ];

        if ($includeSpouse && $spouse) {
            // Get mortgages where spouse is primary owner OR joint owner (joint-aware via MortgageStore)
            $spouseMortgages = $this->mortgageStore->forUser($spouse)->load('property');
            $spouseLiabilities = Liability::where('user_id', $spouse->id)->get();

            // Calculate spouse age at death for mortgage projections
            $spouseAge = $spouse->date_of_birth ? Carbon::parse($spouse->date_of_birth)->age : 50;
            $spouseYearsToProjectedDeath = max(0, 85 - $spouseAge);
            $spouseAgeAtDeath = $spouseAge + $spouseYearsToProjectedDeath;

            $spouseBreakdown = $this->formatUserLiabilities(
                $spouseMortgages,
                $spouseLiabilities,
                $spouse,
                $spouseAgeAtDeath
            );

            $breakdown['spouse'] = array_merge(
                ['name' => $this->formatUserName($spouse)],
                $spouseBreakdown
            );
        }

        return $breakdown;
    }

    /**
     * The year-by-year cash projection shown beneath the estate headline.
     *
     * **Rule 20.** This method used to be a second, independent implementation of the
     * projection that produced `projected_cash` — and it disagreed with it on every
     * point that mattered: no inflation, no life events, no floor, its own hardcoded
     * ages of 68 / 67 / 85, an income list missing trust income, a hardcoded 0.50
     * retirement ratio, and two columns that have never existed on any table
     * (`state_pensions.estimated_annual_amount`, `users.state_pension_age`) each
     * quietly resolving to zero.
     *
     * The consequence was worse than either defect alone: the table whose entire
     * purpose is to explain the headline was arithmetically incapable of adding up to
     * it. Both now read `HouseholdCashFlowProjector`, so there is nothing left to keep in
     * step.
     *
     * The payload keeps its shape for the screens that read it, with one change:
     * `final_cash_raw` — which was the negative balance, and was labelled a shortfall
     * while being printed as a minus number — is replaced by `shortfall`, a positive
     * amount of unmet expenditure. `final_cash_capped` is retained and is now simply
     * the projected cash, because the projection no longer produces anything that
     * needs capping.
     */
    public function generateCashProjectionBreakdown(
        User $user,
        ?User $spouse,
        bool $dataSharingEnabled,
        array $calculation
    ): array {
        $projection = $this->cashFlowProjector->project(
            $user,
            $spouse,
            // W-0474 F1 — the same decision as the headline, by the same rule. This
            // table exists to explain that headline; pooling it differently is how
            // the two came apart the first time.
            HouseholdPooling::poolsSpouse($user, $spouse, $dataSharingEnabled),
            (int) ($calculation['years_to_death'] ?? 0),
            (float) ($calculation['inflation_rate'] ?? 0.02)
        );

        return [
            'starting_cash' => round($projection['starting_cash'], 0),
            'pre_retirement_income' => round($projection['pre_retirement_income'], 0),
            'pre_retirement_expenses' => round($projection['pre_retirement_expenses'], 0),
            'retirement_income' => round($projection['retirement_income'], 0),
            'retirement_expenses' => round($projection['retirement_expenses'], 0),
            'state_pension_income' => round($projection['state_pension_income'], 0),
            'retirement_age' => $projection['retirement_age'],
            'state_pension_age' => $projection['state_pension_age'],
            'death_age' => $projection['death_age'],
            'final_cash' => round($projection['final_cash'], 0),
            'final_cash_capped' => round($projection['final_cash'], 0),
            'shortfall' => round($projection['shortfall'], 0),
            'assumptions' => $projection['assumptions'],
            'years' => $projection['years'],
        ];
    }

    /**
     * Initialize empty asset categories array.
     */
    private function initializeAssetCategories(): array
    {
        return [
            'investment' => [],
            'property' => [],
            'cash' => [],
            'business' => [],
            'chattel' => [],
        ];
    }

    /**
     * Calculate projected value based on asset type.
     */
    private function calculateProjectedValue(
        string $assetType,
        float $currentValue,
        float $cashFactor,
        float $investmentFactor,
        float $propertyFactor
    ): float {
        return match ($assetType) {
            'cash' => $currentValue * $cashFactor,
            'investment' => $currentValue * $investmentFactor,
            'property' => $currentValue * $propertyFactor,
            'chattel', 'business' => $currentValue, // No growth
            default => $currentValue,
        };
    }

    /**
     * Format user's full name.
     */
    private function formatUserName(User $user): string
    {
        return trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: $user->name;
    }

    /**
     * Format liabilities for a single user.
     */
    private function formatUserLiabilities(
        $mortgages,
        $liabilities,
        User $user,
        int $ageAtDeath
    ): array {
        $mortgagesFormatted = [];
        $liabilitiesFormatted = [];
        $mortgagesTotal = 0;
        $liabilitiesTotal = 0;
        $mortgagesProjectedTotal = 0;
        $liabilitiesProjectedTotal = 0;

        foreach ($mortgages as $mortgage) {
            if ($mortgage->outstanding_balance > 0) {
                $propertyName = $mortgage->property ? $mortgage->property->address_line_1 : 'Unknown Property';
                $isJoint = ($mortgage->ownership_type ?? 'individual') === 'joint';

                // Calculate user's share of the mortgage
                $userShare = $this->calculateUserMortgageShare($mortgage, $user->id);

                if ($userShare <= 0) {
                    continue;
                }

                // Mortgages are assumed to be paid off by age 70
                $projectedBalance = ($ageAtDeath >= 70) ? 0 : $userShare;

                $mortgagesFormatted[] = [
                    'property_address' => $propertyName,
                    'outstanding_balance' => $userShare,
                    'full_balance' => (float) $mortgage->outstanding_balance,
                    'projected_balance' => $projectedBalance,
                    'mortgage_type' => $mortgage->mortgage_type ?? 'repayment',
                    'is_joint' => $isJoint,
                    'ownership_percentage' => $isJoint
                        ? ($mortgage->user_id === $user->id ? $mortgage->ownership_percentage : (100 - $mortgage->ownership_percentage))
                        : 100,
                ];
                $mortgagesTotal += $userShare;
                $mortgagesProjectedTotal += $projectedBalance;
            }
        }

        foreach ($liabilities as $liability) {
            if ($liability->current_balance > 0) {
                $userShare = $this->calculateUserShare($liability, $user->id);
                if ($userShare <= 0) {
                    continue;
                }
                $isShared = in_array($liability->ownership_type, ['joint', 'tenants_in_common'], true);
                $liabilitiesFormatted[] = [
                    'type' => ucwords(str_replace('_', ' ', $liability->liability_type)),
                    'institution' => $liability->liability_name ?? ucwords(str_replace('_', ' ', $liability->liability_type)),
                    'current_balance' => $userShare,
                    'full_balance' => (float) $liability->current_balance,
                    'projected_balance' => $userShare,
                    'is_joint' => $isShared,
                    'ownership_percentage' => $isShared
                        ? ($liability->user_id === $user->id
                            ? (float) $liability->ownership_percentage
                            : 100 - (float) $liability->ownership_percentage)
                        : 100,
                ];
                $liabilitiesTotal += $userShare;
                $liabilitiesProjectedTotal += $userShare;
            }
        }

        return [
            'liabilities' => [
                'mortgages' => $mortgagesFormatted,
                'other_liabilities' => $liabilitiesFormatted,
            ],
            'mortgages_total' => $mortgagesTotal,
            'liabilities_total' => $liabilitiesTotal,
            'total' => $mortgagesTotal + $liabilitiesTotal,
            'projected_total' => $mortgagesProjectedTotal + $liabilitiesProjectedTotal,
        ];
    }
}
