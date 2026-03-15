<?php

declare(strict_types=1);

namespace App\Services\Investment\Analytics;

use App\Models\Investment\InvestmentAccount;
use App\Services\Investment\Utilities\StatisticalFunctions;
use App\Services\Risk\RiskPreferenceService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Extract and prepare holdings data for portfolio optimization
 * Converts user holdings into expected returns, labels, and validation
 */
class HoldingsDataExtractor
{
    public function __construct(
        private StatisticalFunctions $stats,
        private readonly RiskPreferenceService $riskPreferenceService
    ) {}

    /**
     * Get default expected return from risk preference service
     */
    private function getDefaultExpectedReturn(): float
    {
        return $this->riskPreferenceService->getReturnParameters('medium')['expected_return_typical'] / 100;
    }

    /**
     * Extract holdings data for a user
     *
     * @param  int  $userId  User ID
     * @param  array|null  $accountIds  Optional specific account IDs to use
     * @return array ['success' => bool, 'message' => string, 'data' => array]
     */
    public function extractForUser(int $userId, ?array $accountIds = null): array
    {
        // Get user's investment accounts
        $query = InvestmentAccount::where('user_id', $userId)->with('holdings');

        if ($accountIds) {
            $query->whereIn('id', $accountIds);
        }

        $accounts = $query->get();

        if ($accounts->isEmpty()) {
            return [
                'success' => false,
                'message' => 'No investment accounts found',
                'data' => null,
            ];
        }

        // Get all holdings from accounts
        $holdings = $accounts->flatMap->holdings;

        if ($holdings->isEmpty()) {
            return [
                'success' => false,
                'message' => 'No holdings found in investment accounts',
                'data' => null,
            ];
        }

        // Minimum holdings requirement for optimization
        if ($holdings->count() < 2) {
            return [
                'success' => false,
                'message' => 'At least 2 holdings required for portfolio optimization',
                'data' => null,
            ];
        }

        // Extract data from holdings
        $extractedData = $this->extractHoldingsData($holdings);

        return [
            'success' => true,
            'message' => 'Holdings data extracted successfully',
            'data' => $extractedData,
        ];
    }

    /**
     * Extract expected returns, labels, and metadata from holdings
     *
     * @param  Collection  $holdings  Holdings collection
     */
    private function extractHoldingsData(Collection $holdings): array
    {
        $expectedReturns = [];
        $labels = [];
        $currentValues = [];
        $metadata = [];

        foreach ($holdings as $holding) {
            // Calculate expected return based on historical performance
            $expectedReturn = $this->calculateExpectedReturn($holding);
            $expectedReturns[] = $expectedReturn;

            // Create label from security name or ticker
            $label = $holding->security_name ?? $holding->ticker ?? "Holding {$holding->id}";
            $labels[] = $label;

            // Store current value
            $currentValues[] = $holding->current_value ?? 0;

            // Store metadata for reference
            $metadata[] = [
                'id' => $holding->id,
                'account_id' => $holding->investment_account_id,
                'asset_type' => $holding->asset_type,
                'ticker' => $holding->ticker,
                'isin' => $holding->isin,
                'current_value' => $holding->current_value,
                'current_price' => $holding->current_price,
                'quantity' => $holding->quantity,
                'dividend_yield' => $holding->dividend_yield,
            ];
        }

        return [
            'expected_returns' => $expectedReturns,
            'labels' => $labels,
            'current_values' => $currentValues,
            'metadata' => $metadata,
            'holdings_count' => count($expectedReturns),
            'total_portfolio_value' => array_sum($currentValues),
        ];
    }

    /**
     * Calculate expected return for a holding
     *
     * Strategy:
     * 1. If historical_returns attribute exists, use mean
     * 2. If capital appreciation available, calculate annualized return
     * 3. Add dividend yield if available
     * 4. Fall back to asset class average
     *
     * @param  object  $holding  Holding model instance
     * @return float Expected annual return (decimal, e.g., 0.08 for 8%)
     */
    private function calculateExpectedReturn($holding): float
    {
        $expectedReturn = 0.0;

        // Method 1: Use historical returns if available
        if (isset($holding->historical_returns) && is_array($holding->historical_returns)) {
            $expectedReturn = $this->stats->mean($holding->historical_returns);
        }
        // Method 2: Calculate from purchase price to current price
        elseif ($holding->purchase_price && $holding->current_price && $holding->purchase_date) {
            $capitalReturn = $this->calculateAnnualizedReturn(
                $holding->purchase_price,
                $holding->current_price,
                $holding->purchase_date
            );
            $expectedReturn = $capitalReturn;
        }
        // Method 3: Use asset class average
        else {
            $expectedReturn = $this->getAssetClassExpectedReturn($holding->asset_type);
        }

        // Add dividend yield if available
        if ($holding->dividend_yield) {
            $expectedReturn += $holding->dividend_yield;
        }

        // Ensure return is reasonable (between -50% and +100%)
        return max(-0.50, min(1.0, $expectedReturn));
    }

    /**
     * Calculate annualized return from purchase to current
     *
     * @param  float  $purchasePrice  Original purchase price
     * @param  float  $currentPrice  Current price
     * @param  \DateTime|string  $purchaseDate  Purchase date
     * @return float Annualized return
     */
    private function calculateAnnualizedReturn(
        float $purchasePrice,
        float $currentPrice,
        $purchaseDate
    ): float {
        if ($purchasePrice <= 0) {
            return 0.0;
        }

        $totalReturn = ($currentPrice - $purchasePrice) / $purchasePrice;

        // Calculate years held
        $purchaseDateObj = $purchaseDate instanceof \DateTime
            ? $purchaseDate
            : new \DateTime($purchaseDate);
        $now = new \DateTime;
        $interval = $purchaseDateObj->diff($now);
        $yearsHeld = $interval->y + ($interval->m / 12) + ($interval->d / 365);

        // Avoid division by zero
        if ($yearsHeld < 0.1) {
            return $totalReturn; // Too short for annualization
        }

        // Annualize: (1 + total_return)^(1/years) - 1
        $annualizedReturn = pow(1 + $totalReturn, 1 / $yearsHeld) - 1;

        return $annualizedReturn;
    }

    /**
     * Get expected return for asset class (fallback)
     *
     * Based on historical UK market data (approximate long-term averages)
     *
     * @param  string|null  $assetType  Asset type
     * @return float Expected annual return
     */
    private function getAssetClassExpectedReturn(?string $assetType): float
    {
        // Historical UK market averages (nominal, pre-inflation)
        $assetClassReturns = [
            'equity' => 0.08,           // UK equities ~8% long-term
            'uk_equity' => 0.08,
            'international_equity' => 0.09,
            'emerging_markets' => 0.10,
            'bond' => 0.04,             // UK gilts ~4%
            'corporate_bond' => 0.05,
            'government_bond' => 0.04,
            'property' => 0.06,         // UK property ~6%
            'cash' => 0.02,             // Cash/money market ~2%
            'commodity' => 0.05,
            'alternative' => 0.07,
            'mixed' => 0.06,
        ];

        $normalizedType = strtolower($assetType ?? '');

        // Try exact match first
        if (isset($assetClassReturns[$normalizedType])) {
            return $assetClassReturns[$normalizedType];
        }

        // Try partial match
        foreach ($assetClassReturns as $key => $return) {
            if (str_contains($normalizedType, $key) || str_contains($key, $normalizedType)) {
                return $return;
            }
        }

        // Default to balanced portfolio return from risk profile
        $defaultReturn = $this->getDefaultExpectedReturn();
        Log::warning('Unknown asset type for expected return', [
            'asset_type' => $assetType,
            'defaulting_to' => $defaultReturn,
        ]);

        return $defaultReturn;
    }

    /**
     * Validate extracted holdings data
     *
     * @param  array  $data  Extracted holdings data
     * @return array ['valid' => bool, 'errors' => array]
     */
    public function validateExtractedData(array $data): array
    {
        $errors = [];

        if (empty($data['expected_returns'])) {
            $errors[] = 'No expected returns calculated';
        }

        if (count($data['expected_returns']) < 2) {
            $errors[] = 'At least 2 holdings required for optimization';
        }

        if (count($data['expected_returns']) !== count($data['labels'])) {
            $errors[] = 'Mismatch between returns and labels count';
        }

        if ($data['total_portfolio_value'] <= 0) {
            $errors[] = 'Total portfolio value must be greater than zero';
        }

        // Check for invalid returns
        foreach ($data['expected_returns'] as $index => $return) {
            if (! is_numeric($return) || $return < -0.50 || $return > 1.0) {
                $errors[] = "Invalid expected return for holding {$index}: {$return}";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
}
