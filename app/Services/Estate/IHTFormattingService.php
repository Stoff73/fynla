<?php

declare(strict_types=1);

namespace App\Services\Estate;

use App\Models\Estate\Liability;
use App\Models\Mortgage;
use App\Models\User;
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

    /** Fallback expenditure ratio when no expenditure profile exists (assume 70% spent, 30% saved) */
    private const EXPENDITURE_FALLBACK_RATIO = 0.70;

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
        // Get mortgages where user is primary owner OR joint owner
        $userMortgages = Mortgage::where(function ($query) use ($user) {
            $query->where('user_id', $user->id)
                ->orWhere('joint_owner_id', $user->id);
        })->with('property')->get();
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
            // Get mortgages where spouse is primary owner OR joint owner
            $spouseMortgages = Mortgage::where(function ($query) use ($spouse) {
                $query->where('user_id', $spouse->id)
                    ->orWhere('joint_owner_id', $spouse->id);
            })->with('property')->get();
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
     * Generate year-by-year cash projection breakdown.
     */
    public function generateCashProjectionBreakdown(
        User $user,
        ?User $spouse,
        bool $dataSharingEnabled,
        array $calculation
    ): array {
        $currentAge = $user->date_of_birth
            ? Carbon::parse($user->date_of_birth)->age
            : 50;
        $retirementAge = $calculation['retirement_age'] ?? 68;
        $deathAge = $calculation['estimated_age_at_death'] ?? 85;

        // Get current cash
        $currentCash = (float) $user->savingsAccounts()->sum('current_balance');
        if ($dataSharingEnabled && $spouse) {
            $currentCash += (float) $spouse->savingsAccounts()->sum('current_balance');
        }

        // Calculate income values
        $preRetIncome = $this->calculatePreRetirementIncome($user);
        if ($dataSharingEnabled && $spouse) {
            $preRetIncome += $this->calculatePreRetirementIncome($spouse);
        }

        // Calculate expenses (70% fallback if no profile)
        $preRetExpenses = $this->calculatePreRetirementExpenses($user, $preRetIncome);
        if ($dataSharingEnabled && $spouse) {
            $userExpProfile = $user->expenditureProfile;
            $spouseExpProfile = $spouse->expenditureProfile;
            if ($spouseExpProfile?->total_monthly_expenditure) {
                $preRetExpenses += (float) $spouseExpProfile->total_monthly_expenditure * 12;
            } elseif ($userExpProfile?->total_monthly_expenditure) {
                $spouseIncome = (float) ($spouse->annual_employment_income ?? 0)
                    + (float) ($spouse->annual_self_employment_income ?? 0);
                $preRetExpenses += $spouseIncome * self::EXPENDITURE_FALLBACK_RATIO;
            }
        }

        // Retirement income
        $retirementIncome = (float) ($user->retirementProfile?->target_retirement_income ?? 0);
        $userStatePension = (float) ($user->statePension?->estimated_annual_amount ?? 0);

        if ($dataSharingEnabled && $spouse) {
            $retirementIncome += (float) ($spouse->retirementProfile?->target_retirement_income ?? 0);
        }

        // Retirement expenses
        $retirementExpenses = $this->calculateRetirementExpenses($user);
        if ($dataSharingEnabled && $spouse) {
            $retirementExpenses += $this->calculateRetirementExpenses($spouse);
        }

        // State pension ages
        $statePensionAge = $user->state_pension_age ?? 67;
        $spouseStatePensionAge = $spouse?->state_pension_age ?? 67;
        $spouseStatePension = $dataSharingEnabled && $spouse
            ? (float) ($spouse->statePension?->estimated_annual_amount ?? 0)
            : 0;

        // Generate year-by-year breakdown
        $years = $this->generateYearlyBreakdown(
            $currentAge,
            $deathAge,
            $retirementAge,
            $statePensionAge,
            $spouseStatePensionAge,
            $currentCash,
            $preRetIncome,
            $preRetExpenses,
            $retirementIncome,
            $retirementExpenses,
            $userStatePension,
            $spouseStatePension,
            $dataSharingEnabled,
            $spouse
        );

        $finalCash = end($years)['running_total'] ?? $currentCash;

        return [
            'starting_cash' => round($currentCash, 0),
            'pre_retirement_income' => round($preRetIncome, 0),
            'pre_retirement_expenses' => round($preRetExpenses, 0),
            'retirement_income' => round($retirementIncome, 0),
            'retirement_expenses' => round($retirementExpenses, 0),
            'state_pension_user' => round($userStatePension, 0),
            'state_pension_spouse' => round($spouseStatePension, 0),
            'retirement_age' => $retirementAge,
            'state_pension_age' => $statePensionAge,
            'death_age' => $deathAge,
            'final_cash_raw' => round($finalCash, 0),
            'final_cash_capped' => round(max(0, $finalCash), 0),
            'years' => $years,
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
                $liabilitiesFormatted[] = [
                    'type' => ucwords(str_replace('_', ' ', $liability->liability_type)),
                    'institution' => $liability->liability_name ?? ucwords(str_replace('_', ' ', $liability->liability_type)),
                    'current_balance' => $liability->current_balance,
                    'projected_balance' => $liability->current_balance,
                    'is_joint' => ($liability->ownership_type ?? 'individual') === 'joint',
                ];
                $liabilitiesTotal += $liability->current_balance;
                $liabilitiesProjectedTotal += $liability->current_balance;
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

    /**
     * Calculate pre-retirement income for a user.
     */
    private function calculatePreRetirementIncome(User $user): float
    {
        return (float) ($user->annual_employment_income ?? 0)
            + (float) ($user->annual_self_employment_income ?? 0)
            + (float) ($user->annual_rental_income ?? 0)
            + (float) ($user->annual_dividend_income ?? 0)
            + (float) ($user->annual_interest_income ?? 0)
            + (float) ($user->annual_other_income ?? 0);
    }

    /**
     * Calculate pre-retirement expenses for a user.
     */
    private function calculatePreRetirementExpenses(User $user, float $income): float
    {
        $expProfile = $user->expenditureProfile;

        return $expProfile?->total_monthly_expenditure
            ? (float) $expProfile->total_monthly_expenditure * 12
            : $income * self::EXPENDITURE_FALLBACK_RATIO;
    }

    /**
     * Calculate retirement expenses for a user.
     */
    private function calculateRetirementExpenses(User $user): float
    {
        $retExp = (float) ($user->retirementProfile?->essential_expenditure ?? 0)
            + (float) ($user->retirementProfile?->lifestyle_expenditure ?? 0);

        if ($retExp <= 0 && $user->retirementProfile?->target_retirement_income > 0) {
            $retExp = (float) $user->retirementProfile->target_retirement_income;
        } elseif ($retExp <= 0) {
            $userIncome = (float) ($user->annual_employment_income ?? 0);
            $retExp = $userIncome * 0.50;
        }

        return $retExp;
    }

    /**
     * Generate year-by-year cash projection.
     */
    private function generateYearlyBreakdown(
        int $currentAge,
        int $deathAge,
        int $retirementAge,
        int $statePensionAge,
        int $spouseStatePensionAge,
        float $currentCash,
        float $preRetIncome,
        float $preRetExpenses,
        float $retirementIncome,
        float $retirementExpenses,
        float $userStatePension,
        float $spouseStatePension,
        bool $dataSharingEnabled,
        ?User $spouse
    ): array {
        $years = [];
        $runningTotal = $currentCash;

        for ($age = $currentAge; $age < $deathAge; $age++) {
            $year = $age - $currentAge + 1;

            if ($age < $retirementAge) {
                $phase = 'Pre-Retirement';
                $income = $preRetIncome;
                $expenses = $preRetExpenses;
            } else {
                $phase = 'Retired';
                $income = $retirementIncome;

                // Add state pension when applicable
                if ($age >= $statePensionAge) {
                    $income += $userStatePension;
                }
                if ($dataSharingEnabled && $spouse) {
                    $spouseAge = $spouse->date_of_birth
                        ? Carbon::parse($spouse->date_of_birth)->age + ($age - $currentAge)
                        : $age;
                    if ($spouseAge >= $spouseStatePensionAge) {
                        $income += $spouseStatePension;
                    }
                }

                $expenses = $retirementExpenses;
            }

            $surplus = $income - $expenses;
            $runningTotal += $surplus;

            $years[] = [
                'year' => $year,
                'age' => $age,
                'phase' => $phase,
                'income' => round($income, 0),
                'expenses' => round($expenses, 0),
                'surplus' => round($surplus, 0),
                'running_total' => round($runningTotal, 0),
            ];
        }

        return $years;
    }
}
