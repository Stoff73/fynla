<?php

declare(strict_types=1);

namespace App\Services\Investment\AssetLocation;

use App\Models\Investment\Holding;
use App\Models\User;
use App\Services\Stores\InvestmentAccountStore;

/**
 * Account Type Recommender
 * Recommends optimal account placement for holdings based on tax efficiency
 *
 * General rules for UK tax-efficient asset location:
 * 1. ISA: Hold highest-return assets (limited by £20k/year allowance)
 * 2. Pension: Hold tax-inefficient assets (bonds, high-dividend)
 * 3. GIA: Hold tax-efficient assets (growth equities with low dividends)
 *
 * Tax efficiency ranking (most to least tax-efficient in GIA):
 * - Growth equities (low dividend, long hold period)
 * - Index funds (low turnover, low dividends)
 * - High-dividend equities
 * - REITs
 * - Bonds
 * - Cash
 */
class AccountTypeRecommender
{
    public function __construct(
        private TaxDragCalculator $taxDragCalculator,
        private readonly InvestmentAccountStore $investmentAccountStore,
    ) {}

    /**
     * Generate placement recommendations for all holdings
     *
     * @param  int  $userId  User ID
     * @param  array  $userTaxProfile  User tax information
     * @return array Recommendations
     */
    public function generateRecommendations(int $userId, array $userTaxProfile): array
    {
        // Primary-only — matches pre-PR-5a where('user_id') semantics
        $user = User::find($userId);
        if (! $user) {
            return [
                'success' => true,
                'recommendations' => [],
                'message' => 'No holdings found',
            ];
        }
        $accounts = $this->investmentAccountStore->forUserPrimaryOnly($user)->load('holdings');

        $allHoldings = collect();
        foreach ($accounts as $account) {
            $allHoldings = $allHoldings->merge($account->holdings);
        }

        if ($allHoldings->isEmpty()) {
            return [
                'success' => true,
                'recommendations' => [],
                'message' => 'No holdings found',
            ];
        }

        // Analyze each holding
        $recommendations = [];
        foreach ($allHoldings as $holding) {
            if (! $holding->current_value) {
                continue;
            }

            $recommendation = $this->analyzeHoldingPlacement($holding, $userTaxProfile);
            $recommendations[] = $recommendation;
        }

        // Sort by potential savings (highest first)
        usort($recommendations, function ($a, $b) {
            return $b['potential_annual_saving'] <=> $a['potential_annual_saving'];
        });

        // Calculate totals
        $totalPotentialSaving = array_sum(array_column($recommendations, 'potential_annual_saving'));

        return [
            'success' => true,
            'recommendations' => $recommendations,
            'total_potential_annual_saving' => $totalPotentialSaving,
            'total_potential_10_year_saving' => $totalPotentialSaving * 10 * 1.15,
        ];
    }

    /**
     * Analyze a single holding and recommend optimal placement
     *
     * @param  Holding  $holding  Holding to analyze
     * @param  array  $userTaxProfile  User tax information
     * @return array Recommendation
     */
    private function analyzeHoldingPlacement(Holding $holding, array $userTaxProfile): array
    {
        // Use polymorphic relationship
        $currentAccount = $holding->holdable;
        $currentAccountType = $currentAccount->account_type ?? 'sipp';

        $comparison = $this->taxDragCalculator->compareAccountTypes($holding, $userTaxProfile);

        // Determine recommended account type
        $recommendedType = $this->determineOptimalAccountType($holding, $userTaxProfile);

        // Calculate tax efficiency score (0-100)
        $taxEfficiencyScore = $this->calculateTaxEfficiencyScore($holding, $currentAccountType);

        // Generate rationale
        $rationale = $this->generateRationale($holding, $currentAccountType, $recommendedType);

        // Determine priority
        $priority = $this->determinePriority(
            $comparison['potential_annual_saving'],
            $holding->current_value,
            $currentAccount->account_type,
            $recommendedType
        );

        return [
            'holding_id' => $holding->id,
            'security_name' => $holding->security_name ?? $holding->ticker,
            'ticker' => $holding->ticker,
            'asset_type' => $holding->asset_type,
            'current_value' => $holding->current_value,
            'current_account_type' => $currentAccount->account_type,
            'current_account_id' => $currentAccount->id,
            'recommended_account_type' => $recommendedType,
            'tax_efficiency_score' => $taxEfficiencyScore,
            'current_tax_drag' => $comparison['current_tax_drag'],
            'recommended_tax_drag' => $comparison['best_tax_drag'],
            'potential_annual_saving' => $comparison['potential_annual_saving'],
            'potential_10_year_saving' => $comparison['potential_10_year_saving'],
            'priority' => $priority,
            'rationale' => $rationale,
            'action' => $this->generateActionText($currentAccount->account_type, $recommendedType, $holding),
        ];
    }

    /**
     * Determine optimal account type for a holding
     *
     * @param  Holding  $holding  Holding
     * @param  array  $userTaxProfile  User tax profile
     * @return string Recommended account type
     */
    private function determineOptimalAccountType(Holding $holding, array $userTaxProfile): string
    {
        // ISA is always best for tax purposes, but consider practical constraints
        $account = $holding->holdable;
        $currentType = $account->account_type ?? 'sipp';

        // If already in ISA, keep it there
        if (in_array($currentType, ['isa', 'stocks_shares_isa', 'lifetime_isa'])) {
            return $currentType;
        }

        // For highly tax-inefficient assets (bonds, high-dividend), recommend ISA or Pension
        $taxInefficiency = $this->calculateTaxInefficiency($holding);

        if ($taxInefficiency > 70) {
            // Very tax-inefficient - ISA is best
            return 'isa';
        } elseif ($taxInefficiency > 40) {
            // Moderately tax-inefficient - ISA preferred, pension acceptable
            return $userTaxProfile['prefer_pension'] ?? false ? 'sipp' : 'isa';
        } else {
            // Tax-efficient - can stay in GIA
            // But ISA is still better if allowance available
            $isaAllowanceRemaining = $userTaxProfile['isa_allowance_remaining'] ?? 0;

            if ($isaAllowanceRemaining >= $holding->current_value) {
                return 'isa';
            } else {
                // Not enough ISA allowance, GIA is acceptable for tax-efficient assets
                return 'gia';
            }
        }
    }

    /**
     * Calculate tax inefficiency score for a holding (0-100)
     * Higher score = more tax-inefficient = more important to shelter
     *
     * @param  Holding  $holding  Holding
     * @return float Tax inefficiency score
     */
    private function calculateTaxInefficiency(Holding $holding): float
    {
        $score = 0;

        // Asset type scoring
        $assetTypeScore = match ($holding->asset_type) {
            'bond', 'fixed_income' => 90, // Very tax-inefficient
            'cash', 'money_market' => 85,
            'reit' => 70,
            'preferred_stock' => 60,
            'equity', 'stock' => 30, // More tax-efficient
            'etf', 'index_fund' => 25,
            default => 50,
        };

        $score += $assetTypeScore * 0.6; // 60% weight

        // Dividend yield scoring (higher dividend = less efficient)
        $dividendYield = $holding->dividend_yield ?? $this->estimateDividendYield($holding);
        $dividendScore = min(100, $dividendYield * 1000); // 4% yield = 40 score
        $score += $dividendScore * 0.3; // 30% weight

        // Turnover/trading frequency (if available)
        // Higher turnover = more CGT events = less efficient
        // Default to assuming moderate turnover
        $turnoverScore = 20; // Default
        $score += $turnoverScore * 0.1; // 10% weight

        return min(100, $score);
    }

    /**
     * Calculate tax efficiency score (0-100)
     * This is the OPPOSITE of tax inefficiency
     * Higher score = currently well-placed
     *
     * @param  Holding  $holding  Holding
     * @param  string  $currentAccountType  Current account type
     * @return float Tax efficiency score
     */
    private function calculateTaxEfficiencyScore(Holding $holding, string $currentAccountType): float
    {
        $taxInefficiency = $this->calculateTaxInefficiency($holding);

        // If holding is tax-inefficient and in ISA/Pension = good (high score)
        // If holding is tax-inefficient and in GIA = bad (low score)
        // If holding is tax-efficient and in GIA = acceptable (medium score)

        if (in_array($currentAccountType, ['isa', 'stocks_shares_isa', 'lifetime_isa'])) {
            return 100; // Perfect - ISA is always optimal
        }

        if (in_array($currentAccountType, ['sipp', 'personal_pension'])) {
            // Good for tax-inefficient assets
            return 100 - ($taxInefficiency * 0.2); // Small penalty
        }

        // GIA
        // Low inefficiency (tax-efficient asset) = acceptable in GIA = 60-80 score
        // High inefficiency (tax-inefficient asset) = bad in GIA = 0-40 score
        return 100 - $taxInefficiency;
    }

    /**
     * Generate rationale for recommendation
     *
     * @param  Holding  $holding  Holding
     * @param  string  $currentType  Current account type
     * @param  string  $recommendedType  Recommended account type
     * @return string Rationale
     */
    private function generateRationale(Holding $holding, string $currentType, string $recommendedType): string
    {
        if ($currentType === $recommendedType) {
            return 'Optimal placement - no action needed';
        }

        $assetDescription = $this->getAssetDescription($holding);
        $currentTypeLabel = $this->getAccountTypeLabel($currentType);
        $recommendedTypeLabel = $this->getAccountTypeLabel($recommendedType);

        return sprintf(
            '%s are tax-inefficient. Moving from %s to %s would eliminate tax drag on dividends and capital gains.',
            $assetDescription,
            $currentTypeLabel,
            $recommendedTypeLabel
        );
    }

    /**
     * Generate action text for recommendation
     *
     * @param  string  $currentType  Current account type
     * @param  string  $recommendedType  Recommended account type
     * @param  Holding  $holding  Holding
     * @return string Action text
     */
    private function generateActionText(string $currentType, string $recommendedType, Holding $holding): string
    {
        if ($currentType === $recommendedType) {
            return 'No action required - optimal placement';
        }

        if ($recommendedType === 'isa') {
            return sprintf(
                'Transfer %s (£%s) to ISA via Bed and ISA',
                $holding->security_name ?? $holding->ticker,
                number_format($holding->current_value, 0)
            );
        }

        if ($recommendedType === 'sipp') {
            return sprintf(
                'Consider pension contribution to buy %s in SIPP',
                $holding->security_name ?? $holding->ticker
            );
        }

        return sprintf(
            'Move %s from %s to %s',
            $holding->security_name ?? $holding->ticker,
            $this->getAccountTypeLabel($currentType),
            $this->getAccountTypeLabel($recommendedType)
        );
    }

    /**
     * Determine priority for recommendation
     *
     * @param  float  $potentialSaving  Potential annual saving
     * @param  float  $holdingValue  Holding value
     * @param  string  $currentType  Current account type
     * @param  string  $recommendedType  Recommended account type
     * @return string Priority (high, medium, low)
     */
    private function determinePriority(
        float $potentialSaving,
        float $holdingValue,
        string $currentType,
        string $recommendedType
    ): string {
        // If already optimal, low priority
        if ($currentType === $recommendedType) {
            return 'low';
        }

        // High priority if large absolute saving or high percentage saving
        $savingPercent = $holdingValue > 0 ? ($potentialSaving / $holdingValue) * 100 : 0;

        if ($potentialSaving > 1000 || $savingPercent > 2) {
            return 'high';
        }

        if ($potentialSaving > 500 || $savingPercent > 1) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Get human-readable asset description
     *
     * @param  Holding  $holding  Holding
     * @return string Asset description
     */
    private function getAssetDescription(Holding $holding): string
    {
        return match ($holding->asset_type) {
            'bond', 'fixed_income' => 'Bonds',
            'reit' => 'REITs',
            'cash', 'money_market' => 'Cash holdings',
            'preferred_stock' => 'Preferred stocks',
            'equity', 'stock' => 'Equities',
            default => ucfirst($holding->asset_type),
        };
    }

    /**
     * Get human-readable account type label
     *
     * @param  string  $accountType  Account type
     * @return string Label
     */
    private function getAccountTypeLabel(string $accountType): string
    {
        return match ($accountType) {
            'isa', 'stocks_shares_isa' => 'ISA',
            'lifetime_isa' => 'Lifetime ISA',
            'cash_isa' => 'Cash ISA',
            'sipp' => 'SIPP',
            'personal_pension' => 'Personal Pension',
            'gia', 'general_investment_account' => 'GIA',
            default => strtoupper($accountType),
        };
    }

    /**
     * Estimate dividend yield
     *
     * @param  Holding  $holding  Holding
     * @return float Dividend yield
     */
    private function estimateDividendYield(Holding $holding): float
    {
        return match ($holding->asset_type) {
            'equity', 'stock' => 0.02,
            'bond', 'fixed_income' => 0.04,
            'reit' => 0.04,
            'preferred_stock' => 0.05,
            default => 0.015,
        };
    }
}
