<?php

declare(strict_types=1);

namespace App\Services\Investment\Performance;

use App\Models\Investment\InvestmentAccount;
use Illuminate\Support\Collection;

/**
 * Performance Attribution Analyzer
 * Breaks down portfolio returns by asset class, sector, and geography
 *
 * Attribution Methods:
 * - Asset Class Attribution (Equities, Bonds, Cash, etc.)
 * - Sector Attribution (Technology, Healthcare, Finance, etc.)
 * - Geographic Attribution (UK, US, Europe, Asia, etc.)
 * - Security Selection vs Asset Allocation
 */
class PerformanceAttributionAnalyzer
{
    public function __construct(
        private BenchmarkComparator $benchmarkComparator,
        private AlphaBetaCalculator $alphaBetaCalculator
    ) {}

    /**
     * Perform comprehensive performance attribution analysis
     *
     * @param  int  $userId  User ID
     * @param  string  $period  Period (1y, 3y, 5y)
     * @return array Attribution analysis
     */
    public function analyzePerformance(int $userId, string $period = '1y'): array
    {
        $accounts = InvestmentAccount::where('user_id', $userId)
            ->with('holdings')
            ->get();

        if ($accounts->isEmpty()) {
            return [
                'success' => false,
                'message' => 'No investment accounts found',
            ];
        }

        // Get all holdings
        $allHoldings = collect();
        foreach ($accounts as $account) {
            $allHoldings = $allHoldings->merge($account->holdings);
        }

        if ($allHoldings->isEmpty()) {
            return [
                'success' => false,
                'message' => 'No holdings found',
            ];
        }

        // Calculate total portfolio value
        $totalValue = $allHoldings->sum('current_value');

        // Perform attribution analyses
        $assetClassAttribution = $this->analyzeByAssetClass($allHoldings, $totalValue);
        $sectorAttribution = $this->analyzeBySector($allHoldings, $totalValue);
        $geographicAttribution = $this->analyzeByGeography($allHoldings, $totalValue);

        // Compare with benchmark
        $benchmarkComparison = $this->benchmarkComparator->compareWithBenchmark($userId, 'ftse_all_share', $period);

        // Calculate contribution to return
        $contributionAnalysis = $this->calculateContributionToReturn($allHoldings, $totalValue);

        return [
            'success' => true,
            'period' => $period,
            'total_portfolio_value' => $totalValue,
            'asset_class_attribution' => $assetClassAttribution,
            'sector_attribution' => $sectorAttribution,
            'geographic_attribution' => $geographicAttribution,
            'contribution_to_return' => $contributionAnalysis,
            'benchmark_comparison' => $benchmarkComparison,
            'summary' => $this->generateAttributionSummary(
                $assetClassAttribution,
                $contributionAnalysis,
                $benchmarkComparison
            ),
        ];
    }

    /**
     * Analyze performance by asset class
     *
     * @param  Collection  $holdings  Holdings
     * @param  float  $totalValue  Total portfolio value
     * @return array Asset class breakdown
     */
    private function analyzeByAssetClass(Collection $holdings, float $totalValue): array
    {
        $breakdown = [];

        foreach ($holdings as $holding) {
            if (! $holding->current_value) {
                continue;
            }

            $assetClass = $this->mapToAssetClass($holding->asset_type);

            if (! isset($breakdown[$assetClass])) {
                $breakdown[$assetClass] = [
                    'value' => 0,
                    'holdings_count' => 0,
                    'gain_loss' => 0,
                ];
            }

            $breakdown[$assetClass]['value'] += $holding->current_value;
            $breakdown[$assetClass]['holdings_count']++;

            // Calculate gain/loss if cost basis available
            if ($holding->cost_basis) {
                $breakdown[$assetClass]['gain_loss'] += ($holding->current_value - $holding->cost_basis);
            }
        }

        // Calculate percentages and expected returns
        foreach ($breakdown as $assetClass => &$data) {
            $data['percentage'] = $totalValue > 0 ? ($data['value'] / $totalValue) * 100 : 0;
            $data['return'] = $data['value'] > 0 ? ($data['gain_loss'] / $data['value']) * 100 : 0;
            $data['contribution_to_return'] = $totalValue > 0 ? ($data['value'] / $totalValue) * $data['return'] : 0;
        }

        // Sort by value descending
        uasort($breakdown, fn ($a, $b) => $b['value'] <=> $a['value']);

        return [
            'breakdown' => $breakdown,
            'top_asset_class' => array_key_first($breakdown),
            'most_diversified' => count($breakdown) >= 4,
        ];
    }

    /**
     * Analyze performance by sector
     *
     * @param  Collection  $holdings  Holdings
     * @param  float  $totalValue  Total portfolio value
     * @return array Sector breakdown
     */
    private function analyzeBySector(Collection $holdings, float $totalValue): array
    {
        $breakdown = [];

        foreach ($holdings as $holding) {
            if (! $holding->current_value) {
                continue;
            }

            $sector = $holding->sector ?? $this->inferSector($holding);

            if (! isset($breakdown[$sector])) {
                $breakdown[$sector] = [
                    'value' => 0,
                    'holdings_count' => 0,
                    'gain_loss' => 0,
                ];
            }

            $breakdown[$sector]['value'] += $holding->current_value;
            $breakdown[$sector]['holdings_count']++;

            if ($holding->cost_basis) {
                $breakdown[$sector]['gain_loss'] += ($holding->current_value - $holding->cost_basis);
            }
        }

        // Calculate percentages
        foreach ($breakdown as $sector => &$data) {
            $data['percentage'] = $totalValue > 0 ? ($data['value'] / $totalValue) * 100 : 0;
            $data['return'] = $data['value'] > 0 ? ($data['gain_loss'] / $data['value']) * 100 : 0;
        }

        uasort($breakdown, fn ($a, $b) => $b['value'] <=> $a['value']);

        return [
            'breakdown' => $breakdown,
            'top_sector' => array_key_first($breakdown),
            'sector_concentration' => $this->calculateConcentration(array_column($breakdown, 'percentage')),
        ];
    }

    /**
     * Analyze performance by geography
     *
     * @param  Collection  $holdings  Holdings
     * @param  float  $totalValue  Total portfolio value
     * @return array Geographic breakdown
     */
    private function analyzeByGeography(Collection $holdings, float $totalValue): array
    {
        $breakdown = [];

        foreach ($holdings as $holding) {
            if (! $holding->current_value) {
                continue;
            }

            $geography = $holding->geography ?? $this->inferGeography($holding);

            if (! isset($breakdown[$geography])) {
                $breakdown[$geography] = [
                    'value' => 0,
                    'holdings_count' => 0,
                    'gain_loss' => 0,
                ];
            }

            $breakdown[$geography]['value'] += $holding->current_value;
            $breakdown[$geography]['holdings_count']++;

            if ($holding->cost_basis) {
                $breakdown[$geography]['gain_loss'] += ($holding->current_value - $holding->cost_basis);
            }
        }

        // Calculate percentages
        foreach ($breakdown as $geography => &$data) {
            $data['percentage'] = $totalValue > 0 ? ($data['value'] / $totalValue) * 100 : 0;
            $data['return'] = $data['value'] > 0 ? ($data['gain_loss'] / $data['value']) * 100 : 0;
        }

        uasort($breakdown, fn ($a, $b) => $b['value'] <=> $a['value']);

        return [
            'breakdown' => $breakdown,
            'top_geography' => array_key_first($breakdown),
            'geographic_diversification' => count($breakdown) >= 3 ? 'Good' : 'Limited',
        ];
    }

    /**
     * Calculate contribution to return for each holding
     *
     * @param  Collection  $holdings  Holdings
     * @param  float  $totalValue  Total portfolio value
     * @return array Top contributors and detractors
     */
    private function calculateContributionToReturn(Collection $holdings, float $totalValue): array
    {
        $contributions = [];

        foreach ($holdings as $holding) {
            if (! $holding->current_value || ! $holding->cost_basis) {
                continue;
            }

            $gainLoss = $holding->current_value - $holding->cost_basis;
            $return = $holding->cost_basis > 0 ? ($gainLoss / $holding->cost_basis) * 100 : 0;
            $weight = $totalValue > 0 ? ($holding->current_value / $totalValue) * 100 : 0;
            $contribution = $totalValue > 0 ? ($gainLoss / $totalValue) * 100 : 0;

            $contributions[] = [
                'holding_id' => $holding->id,
                'security_name' => $holding->security_name ?? $holding->ticker,
                'ticker' => $holding->ticker,
                'value' => $holding->current_value,
                'weight' => $weight,
                'return' => $return,
                'contribution' => $contribution,
            ];
        }

        // Sort by contribution
        usort($contributions, fn ($a, $b) => $b['contribution'] <=> $a['contribution']);

        return [
            'all_contributions' => $contributions,
            'top_contributors' => array_slice($contributions, 0, 5),
            'top_detractors' => array_slice(array_reverse($contributions), 0, 5),
        ];
    }

    /**
     * Map asset type to standard asset class
     *
     * @param  string  $assetType  Asset type
     * @return string Asset class
     */
    private function mapToAssetClass(string $assetType): string
    {
        return match ($assetType) {
            'equity', 'stock' => 'Equities',
            'bond', 'fixed_income' => 'Bonds',
            'cash', 'money_market' => 'Cash',
            'reit' => 'Real Estate',
            'commodity' => 'Commodities',
            'alternative' => 'Alternatives',
            default => 'Other',
        };
    }

    /**
     * Infer sector from holding data
     *
     * @param  $holding  Holding
     * @return string Sector
     */
    private function inferSector($holding): string
    {
        // Simple inference - in production would use external data
        if ($holding->asset_type === 'bond') {
            return 'Fixed Income';
        }

        return 'Diversified';
    }

    /**
     * Infer geography from holding data
     *
     * @param  $holding  Holding
     * @return string Geography
     */
    private function inferGeography($holding): string
    {
        // Simple inference based on ticker/ISIN
        if (str_ends_with($holding->ticker ?? '', '.L')) {
            return 'UK';
        }

        return 'Global';
    }

    /**
     * Calculate concentration (Herfindahl index)
     *
     * @param  array  $percentages  Percentages
     * @return string Concentration level
     */
    private function calculateConcentration(array $percentages): string
    {
        $herfindahl = 0;
        foreach ($percentages as $pct) {
            $herfindahl += pow($pct / 100, 2);
        }

        if ($herfindahl > 0.25) {
            return 'High';
        } elseif ($herfindahl > 0.15) {
            return 'Moderate';
        } else {
            return 'Low';
        }
    }

    /**
     * Generate attribution summary
     *
     * @param  array  $assetClassAttribution  Asset class attribution
     * @param  array  $contributionAnalysis  Contribution analysis
     * @param  array  $benchmarkComparison  Benchmark comparison
     * @return array Summary
     */
    private function generateAttributionSummary(
        array $assetClassAttribution,
        array $contributionAnalysis,
        array $benchmarkComparison
    ): array {
        $topAssetClass = $assetClassAttribution['top_asset_class'] ?? 'Unknown';
        $topContributor = $contributionAnalysis['top_contributors'][0] ?? null;
        $topDetractor = $contributionAnalysis['top_detractors'][0] ?? null;

        $outperformance = $benchmarkComparison['success']
            ? $benchmarkComparison['outperformance']['absolute_percent']
            : null;

        $summary = [
            'dominant_asset_class' => $topAssetClass,
            'top_performing_holding' => $topContributor['security_name'] ?? 'N/A',
            'worst_performing_holding' => $topDetractor['security_name'] ?? 'N/A',
        ];

        if ($outperformance !== null) {
            $summary['vs_benchmark'] = $outperformance > 0
                ? sprintf('Outperforming by %.1f%%', $outperformance)
                : sprintf('Underperforming by %.1f%%', abs($outperformance));
        }

        return $summary;
    }
}
