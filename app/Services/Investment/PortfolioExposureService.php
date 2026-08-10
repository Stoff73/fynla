<?php

declare(strict_types=1);

namespace App\Services\Investment;

use App\Constants\InvestmentDefaults;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Builds one evidence-aware portfolio exposure contract for every wrapper.
 *
 * Composite holdings are never assigned an assumed asset mix. They are
 * classified only from an explicitly stored look-through vector; any unknown
 * or missing portion remains visible as unclassified exposure.
 */
class PortfolioExposureService
{
    public const MINIMUM_DRIFT_COVERAGE_PERCENT = 80.0;

    private const ASSET_CLASS_ORDER = ['equities', 'bonds', 'cash', 'alternatives'];

    public function analyse(
        Collection $holdings,
        ?array $enteredBaseline = null,
        ?array $recommendedAllocation = null,
    ): array {
        $totalValue = round((float) $holdings->sum(fn ($holding) => max(0.0, (float) ($holding->current_value ?? 0))), 2);
        $assetValues = array_fill_keys(self::ASSET_CLASS_ORDER, 0.0);
        $unclassifiedValue = 0.0;
        $holdingRows = [];

        foreach ($holdings as $holding) {
            $value = max(0.0, (float) ($holding->current_value ?? 0));
            $classification = $this->classifyHolding($holding);
            $classifiedWeight = 0.0;
            $exposures = [];

            foreach ($classification['weights'] as $assetClass => $weight) {
                $exposureValue = $value * $weight;
                if ($assetClass === 'unclassified') {
                    $unclassifiedValue += $exposureValue;
                } else {
                    $assetValues[$assetClass] += $exposureValue;
                    $classifiedWeight += $weight;
                }

                if ($exposureValue > 0) {
                    $exposures[] = [
                        'asset_class' => $assetClass,
                        'value' => round($exposureValue, 2),
                        'holding_percentage' => round($weight * 100, 2),
                    ];
                }
            }

            $holdingRows[] = [
                'id' => $holding->id,
                'name' => $holding->security_name,
                'value' => round($value, 2),
                'portfolio_percentage' => $totalValue > 0 ? round(($value / $totalValue) * 100, 2) : 0.0,
                'classification_coverage_percent' => round($classifiedWeight * 100, 2),
                'classification' => [
                    'method' => $classification['method'],
                    'source' => $classification['source'],
                    'effective_at' => $classification['effective_at'],
                ],
                'exposures' => $exposures,
            ];
        }

        $classifiedValue = round(array_sum($assetValues), 2);
        $unclassifiedValue = round($unclassifiedValue, 2);
        $coveragePercent = $totalValue > 0
            ? round(($classifiedValue / $totalValue) * 100, 2)
            : 0.0;
        $driftAvailable = $totalValue > 0 && $coveragePercent >= self::MINIMUM_DRIFT_COVERAGE_PERCENT;

        $allocation = [];
        foreach (self::ASSET_CLASS_ORDER as $assetClass) {
            $value = round($assetValues[$assetClass], 2);
            if ($value <= 0) {
                continue;
            }

            $allocation[] = [
                'asset_class' => $assetClass,
                'value' => $value,
                'portfolio_percentage' => $totalValue > 0 ? round(($value / $totalValue) * 100, 2) : 0.0,
                'classified_percentage' => $classifiedValue > 0 ? round(($value / $classifiedValue) * 100, 2) : null,
            ];
        }

        if ($unclassifiedValue > 0) {
            $allocation[] = [
                'asset_class' => 'unclassified',
                'value' => $unclassifiedValue,
                'portfolio_percentage' => $totalValue > 0 ? round(($unclassifiedValue / $totalValue) * 100, 2) : 0.0,
                'classified_percentage' => null,
            ];
        }

        $currentClassifiedAllocation = [];
        foreach ($allocation as $row) {
            if ($row['asset_class'] !== 'unclassified') {
                $currentClassifiedAllocation[$row['asset_class']] = $row['classified_percentage'];
            }
        }

        return [
            'total_value' => $totalValue,
            'classified_value' => $classifiedValue,
            'unclassified_value' => $unclassifiedValue,
            'coverage_percent' => $coveragePercent,
            'coverage_threshold_percent' => self::MINIMUM_DRIFT_COVERAGE_PERCENT,
            'drift_available' => $driftAvailable,
            'allocation' => $allocation,
            'holdings' => $holdingRows,
            'comparisons' => [
                'entered' => $this->buildComparison($enteredBaseline, $currentClassifiedAllocation, $driftAvailable),
                'recommended' => $this->buildComparison($recommendedAllocation, $currentClassifiedAllocation, $driftAvailable),
            ],
        ];
    }

    private function classifyHolding(mixed $holding): array
    {
        $lookThrough = $holding->look_through_allocation ?? null;
        if (is_array($lookThrough) && $lookThrough !== []) {
            $weights = [];
            $recordedTotal = 0.0;

            foreach ($lookThrough as $assetClass => $percentage) {
                if (! is_numeric($percentage) || (float) $percentage <= 0) {
                    continue;
                }

                $weight = (float) $percentage / 100;
                $recordedTotal += $weight;
                $normalisedClass = $this->normaliseAssetClass((string) $assetClass);
                $weights[$normalisedClass] = ($weights[$normalisedClass] ?? 0.0) + $weight;
            }

            if ($recordedTotal <= 1.0001) {
                $remainder = max(0.0, 1.0 - $recordedTotal);
                if ($remainder > 0.0001) {
                    $weights['unclassified'] = ($weights['unclassified'] ?? 0.0) + $remainder;
                }

                return [
                    'weights' => $weights,
                    'method' => 'recorded_look_through',
                    'source' => $holding->look_through_source,
                    'effective_at' => $this->normaliseDate($holding->look_through_effective_at),
                ];
            }

            return $this->unclassified('invalid_look_through');
        }

        $assetType = (string) ($holding->asset_type ?? 'unknown');
        $subType = $holding->sub_type ? (string) $holding->sub_type : null;
        $resolvedClass = InvestmentDefaults::resolveAssetClass($assetType, $subType);

        if (in_array($resolvedClass, self::ASSET_CLASS_ORDER, true)) {
            return [
                'weights' => [$resolvedClass => 1.0],
                'method' => $subType ? 'recorded_asset_sub_type' : 'recorded_asset_type',
                'source' => 'holding_record',
                'effective_at' => null,
            ];
        }

        return $this->unclassified('insufficient_classification_data');
    }

    private function unclassified(string $method): array
    {
        return [
            'weights' => ['unclassified' => 1.0],
            'method' => $method,
            'source' => null,
            'effective_at' => null,
        ];
    }

    private function normaliseAssetClass(string $assetClass): string
    {
        $assetClass = strtolower(trim($assetClass));

        return match ($assetClass) {
            'equity', 'equities', 'stock', 'stocks' => 'equities',
            'bond', 'bonds', 'fixed_income' => 'bonds',
            'cash', 'money_market' => 'cash',
            'alternative', 'alternatives', 'property', 'real_estate' => 'alternatives',
            default => 'unclassified',
        };
    }

    private function buildComparison(?array $comparison, array $current, bool $driftAvailable): ?array
    {
        if ($comparison === null) {
            return null;
        }

        $allocation = $this->normaliseComparisonAllocation($comparison['allocation'] ?? []);
        $canCompare = $driftAvailable && $allocation !== [];

        return [
            'source' => $comparison['source'] ?? null,
            'effective_at' => $this->normaliseDate($comparison['effective_at'] ?? null),
            'allocation' => $allocation,
            'drift_percentage_points' => $canCompare ? $this->calculateDrift($current, $allocation) : null,
            'unavailable_reason' => $canCompare
                ? null
                : ($driftAvailable ? 'comparison_allocation_unavailable' : 'classification_coverage_below_threshold'),
        ];
    }

    private function normaliseComparisonAllocation(array $allocation): array
    {
        $normalised = array_fill_keys(self::ASSET_CLASS_ORDER, 0.0);
        foreach ($allocation as $assetClass => $percentage) {
            if (! is_numeric($percentage) || (float) $percentage < 0) {
                continue;
            }

            $class = $this->normaliseAssetClass((string) $assetClass);
            if ($class !== 'unclassified') {
                $normalised[$class] += (float) $percentage;
            }
        }

        $total = array_sum($normalised);
        if ($total <= 0) {
            return [];
        }

        $result = [];
        foreach (self::ASSET_CLASS_ORDER as $assetClass) {
            if ($normalised[$assetClass] > 0) {
                $result[$assetClass] = round(($normalised[$assetClass] / $total) * 100, 2);
            }
        }

        return $result;
    }

    private function calculateDrift(array $current, array $comparison): array
    {
        $drift = [];
        foreach (self::ASSET_CLASS_ORDER as $assetClass) {
            $currentPercentage = (float) ($current[$assetClass] ?? 0);
            $comparisonPercentage = (float) ($comparison[$assetClass] ?? 0);
            if ($currentPercentage !== 0.0 || $comparisonPercentage !== 0.0) {
                $drift[$assetClass] = round($currentPercentage - $comparisonPercentage, 2);
            }
        }

        return $drift;
    }

    private function normaliseDate(mixed $value): ?string
    {
        if ($value instanceof CarbonInterface) {
            return $value->toDateString();
        }

        if (is_string($value) && $value !== '') {
            return substr($value, 0, 10);
        }

        return null;
    }
}
