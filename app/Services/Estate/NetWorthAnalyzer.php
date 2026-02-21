<?php

declare(strict_types=1);

namespace App\Services\Estate;

use App\Models\Estate\Asset;
use App\Models\Estate\Liability;
use App\Services\Shared\CrossModuleAssetAggregator;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class NetWorthAnalyzer
{
    public function __construct(
        private CrossModuleAssetAggregator $assetAggregator
    ) {}

    /**
     * Calculate current net worth for a user
     */
    public function calculateNetWorth(int $userId): array
    {
        // Get all manually entered assets (Estate module specific)
        $manualAssets = Asset::where('user_id', $userId)->get();

        // Get cross-module assets using shared aggregator (eliminates duplication)
        $crossModuleAssets = $this->assetAggregator->getAllAssets($userId);

        // Get totals for each cross-module asset type
        $assetTotals = $this->assetAggregator->getAssetTotals($userId);
        $investmentTotalValue = $assetTotals['investment'];
        $propertyTotalValue = $assetTotals['property'];
        $savingsTotalValue = $assetTotals['cash'];

        // Merge all assets (manual + cross-module)
        $allAssets = $manualAssets->concat($crossModuleAssets);
        $totalAssets = $allAssets->sum('current_value');

        // Get all liabilities (manual + mortgages)
        $liabilities = Liability::where('user_id', $userId)->get();
        $mortgagesTotal = $this->assetAggregator->calculateMortgageTotal($userId);

        $manualLiabilitiesTotal = $liabilities->sum('current_balance');
        $totalLiabilities = $manualLiabilitiesTotal + $mortgagesTotal;

        // Calculate net worth
        $netWorth = $totalAssets - $totalLiabilities;

        // Analyze asset composition
        $assetComposition = $this->analyzeAssetComposition($allAssets);

        // Analyze liability composition (include mortgages from aggregator)
        $mortgages = $this->assetAggregator->getMortgages($userId);
        $liabilityComposition = $this->analyzeLiabilityComposition($liabilities, $mortgages);

        // Calculate ratios
        $debtToAssetRatio = $totalAssets > 0 ? ($totalLiabilities / $totalAssets) : 0;
        $netWorthRatio = $totalAssets > 0 ? ($netWorth / $totalAssets) : 0;

        return [
            'total_assets' => round($totalAssets, 2),
            'total_manual_assets' => round($manualAssets->sum('current_value'), 2),
            'total_investment_assets' => round($investmentTotalValue, 2),
            'total_property_assets' => round($propertyTotalValue, 2),
            'total_cash_assets' => round($savingsTotalValue, 2),
            'total_liabilities' => round($totalLiabilities, 2),
            'total_manual_liabilities' => round($manualLiabilitiesTotal, 2),
            'total_mortgage_liabilities' => round($mortgagesTotal, 2),
            'net_worth' => round($netWorth, 2),
            'debt_to_asset_ratio' => round($debtToAssetRatio, 4),
            'net_worth_ratio' => round($netWorthRatio, 4),
            'asset_composition' => $assetComposition,
            'liability_composition' => $liabilityComposition,
            'statement_date' => Carbon::now()->format('Y-m-d'),
        ];
    }

    /**
     * Analyze asset composition by type
     */
    public function analyzeAssetComposition(Collection $assets): array
    {
        $totalValue = $assets->sum('current_value');

        if ($totalValue == 0) {
            return [];
        }

        $byType = $assets->groupBy('asset_type')->map(function ($group, $type) use ($totalValue) {
            $typeValue = $group->sum('current_value');

            return [
                'type' => $type,
                'value' => round($typeValue, 2),
                'percentage' => round(($typeValue / $totalValue) * 100, 2),
                'count' => $group->count(),
            ];
        })->values()->toArray();

        // Sort by value descending
        usort($byType, fn ($a, $b) => $b['value'] <=> $a['value']);

        return $byType;
    }

    /**
     * Analyze liability composition by type
     */
    private function analyzeLiabilityComposition(Collection $liabilities, Collection $mortgages): array
    {
        $manualLiabilitiesTotal = $liabilities->sum('current_balance');
        $mortgagesTotal = $mortgages->sum('outstanding_balance');
        $totalValue = $manualLiabilitiesTotal + $mortgagesTotal;

        if ($totalValue == 0) {
            return [];
        }

        // Group manual liabilities by type
        $byType = $liabilities->groupBy('liability_type')->map(function ($group, $type) use ($totalValue) {
            $typeValue = $group->sum('current_balance');
            $totalMonthlyPayment = $group->sum('monthly_payment');

            return [
                'type' => $type,
                'balance' => round($typeValue, 2),
                'percentage' => round(($typeValue / $totalValue) * 100, 2),
                'count' => $group->count(),
                'monthly_payment' => round($totalMonthlyPayment, 2),
            ];
        })->values()->toArray();

        // Add mortgages as a separate type if they exist
        if ($mortgagesTotal > 0) {
            $totalMonthlyMortgagePayment = $mortgages->sum('monthly_payment');

            $byType[] = [
                'type' => 'mortgage',
                'balance' => round($mortgagesTotal, 2),
                'percentage' => round(($mortgagesTotal / $totalValue) * 100, 2),
                'count' => $mortgages->count(),
                'monthly_payment' => round($totalMonthlyMortgagePayment, 2),
            ];
        }

        // Sort by balance descending
        usort($byType, fn ($a, $b) => $b['balance'] <=> $a['balance']);

        return $byType;
    }

    /**
     * Identify concentration risk in assets
     */
    public function identifyConcentrationRisk(Collection $assets): array
    {
        $totalValue = $assets->sum('current_value');
        $risks = [];

        if ($totalValue == 0) {
            return [
                'has_concentration_risk' => false,
                'risks' => [],
            ];
        }

        // Check for single asset concentration (>50% of total)
        foreach ($assets as $asset) {
            $percentage = ($asset->current_value / $totalValue) * 100;

            if ($percentage > 50) {
                $risks[] = [
                    'type' => 'Single Asset Concentration',
                    'asset_name' => $asset->asset_name,
                    'asset_type' => $asset->asset_type,
                    'value' => round($asset->current_value, 2),
                    'percentage' => round($percentage, 2),
                    'severity' => 'High',
                    'recommendation' => 'Consider diversifying - single asset represents over 50% of total wealth',
                ];
            } elseif ($percentage > 30) {
                $risks[] = [
                    'type' => 'Asset Concentration',
                    'asset_name' => $asset->asset_name,
                    'asset_type' => $asset->asset_type,
                    'value' => round($asset->current_value, 2),
                    'percentage' => round($percentage, 2),
                    'severity' => 'Medium',
                    'recommendation' => 'Monitor concentration - asset represents over 30% of total wealth',
                ];
            }
        }

        // Check for asset type concentration
        $byType = $assets->groupBy('asset_type');
        foreach ($byType as $type => $group) {
            $typeValue = $group->sum('current_value');
            $percentage = ($typeValue / $totalValue) * 100;

            if ($percentage > 70) {
                $risks[] = [
                    'type' => 'Asset Type Concentration',
                    'asset_type' => $type,
                    'value' => round($typeValue, 2),
                    'percentage' => round($percentage, 2),
                    'severity' => 'High',
                    'recommendation' => "Over-concentrated in {$type} - consider diversifying across asset classes",
                ];
            }
        }

        return [
            'has_concentration_risk' => count($risks) > 0,
            'risk_count' => count($risks),
            'risks' => $risks,
        ];
    }

    /**
     * Generate net worth summary
     */
    public function generateSummary(int $userId): array
    {
        $netWorth = $this->calculateNetWorth($userId);
        $assets = Asset::where('user_id', $userId)->get();
        $concentrationRisk = $this->identifyConcentrationRisk($assets);

        // Calculate health score (0-100)
        $healthScore = $this->calculateNetWorthHealthScore($netWorth, $concentrationRisk);

        return [
            'net_worth' => $netWorth,
            'concentration_risk' => $concentrationRisk,
            'health_score' => $healthScore,
        ];
    }

    /**
     * Calculate net worth health score
     */
    private function calculateNetWorthHealthScore(array $netWorth, array $concentrationRisk): array
    {
        $score = 100;
        $factors = [];

        // Debt to asset ratio impact (max -30 points)
        $debtRatio = $netWorth['debt_to_asset_ratio'];
        if ($debtRatio > 0.5) {
            $deduction = min(30, ($debtRatio - 0.5) * 60);
            $score -= $deduction;
            $factors[] = [
                'factor' => 'High Debt Ratio',
                'impact' => -round($deduction, 0),
                'detail' => 'Debt-to-asset ratio of '.round($debtRatio * 100, 1).'% is concerning',
            ];
        }

        // Concentration risk impact (max -40 points)
        if ($concentrationRisk['has_concentration_risk']) {
            $highRisks = count(array_filter($concentrationRisk['risks'], fn ($r) => $r['severity'] === 'High'));
            $mediumRisks = count(array_filter($concentrationRisk['risks'], fn ($r) => $r['severity'] === 'Medium'));

            $deduction = ($highRisks * 20) + ($mediumRisks * 10);
            $score -= $deduction;
            $factors[] = [
                'factor' => 'Concentration Risk',
                'impact' => -$deduction,
                'detail' => "{$highRisks} high and {$mediumRisks} medium concentration risks identified",
            ];
        }

        // Positive net worth bonus (+10 points)
        if ($netWorth['net_worth'] > 0) {
            $score = min(100, $score + 10);
            $factors[] = [
                'factor' => 'Positive Net Worth',
                'impact' => 10,
                'detail' => 'Assets exceed liabilities',
            ];
        }

        $score = max(0, min(100, $score));

        return [
            'score' => round($score, 0),
            'grade' => $this->getHealthGrade($score),
            'factors' => $factors,
        ];
    }

    /**
     * Get health grade based on score
     */
    private function getHealthGrade(float $score): string
    {
        return match (true) {
            $score >= 90 => 'Excellent',
            $score >= 75 => 'Good',
            $score >= 60 => 'Fair',
            $score >= 40 => 'Needs Attention',
            default => 'Poor',
        };
    }
}
