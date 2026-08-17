<?php

declare(strict_types=1);

namespace App\Services\Investment;

use App\Constants\InvestmentDefaults;
use App\Models\DCPension;
use App\Models\Investment\InvestmentAccount;
use App\Models\Investment\RiskProfile;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class PortfolioPresentationService
{
    public const CONTRACT_VERSION = 'financial_portfolio_v1';

    public function __construct(
        private readonly PortfolioExposureService $exposureService,
    ) {}

    public function forInvestmentAccount(
        InvestmentAccount $account,
        ?RiskProfile $riskProfile,
        float $relevantPortfolioValue,
    ): array {
        $holdings = $account->relationLoaded('holdings')
            ? $account->holdings
            : $account->holdings()->get();

        $analysis = $this->exposureService->analyse(
            $holdings,
            $this->enteredBaseline($account),
            $this->recommendedAllocation($account, $riskProfile),
        );

        $snapshots = $account->relationLoaded('valueSnapshots')
            ? $account->valueSnapshots
            : $account->valueSnapshots()->get();

        return $this->present(
            $this->investmentWrapperType($account),
            $account->id,
            $account->account_name ?: $account->provider ?: 'Investment account',
            (float) $account->current_value,
            $holdings,
            $analysis,
            $relevantPortfolioValue,
            $snapshots,
            ['current_value'],
        );
    }

    public function forDCPension(
        DCPension $pension,
        ?RiskProfile $riskProfile,
        float $relevantPortfolioValue,
    ): array {
        $holdings = $pension->relationLoaded('holdings')
            ? $pension->holdings
            : $pension->holdings()->get();

        $analysis = $this->exposureService->analyse(
            $holdings,
            $this->enteredBaseline($pension),
            $this->recommendedAllocation($pension, $riskProfile),
        );

        $snapshots = $pension->relationLoaded('valueSnapshots')
            ? $pension->valueSnapshots
            : $pension->valueSnapshots()->get();

        return $this->present(
            'dc_pension',
            $pension->id,
            $pension->scheme_name ?: $pension->provider ?: 'DC pension',
            (float) $pension->current_fund_value,
            $holdings,
            $analysis,
            $relevantPortfolioValue,
            $snapshots,
            ['current_fund_value', 'current_fund_value_gbp'],
        );
    }

    private function present(
        string $wrapperType,
        int $wrapperId,
        string $wrapperName,
        float $recordedValue,
        Collection|EloquentCollection $holdings,
        array $analysis,
        float $relevantPortfolioValue,
        Collection|EloquentCollection $snapshots,
        array $snapshotColumns,
    ): array {
        $analysisHoldings = collect($analysis['holdings'])->keyBy('id');
        $holdingRows = $holdings->map(function ($holding) use ($analysisHoldings, $relevantPortfolioValue) {
            $value = max(0.0, (float) ($holding->current_value ?? 0));
            $costBasis = $holding->cost_basis !== null ? (float) $holding->cost_basis : null;
            $ocfPercent = $holding->ocf_percent !== null ? (float) $holding->ocf_percent : null;
            $exposure = $analysisHoldings->get($holding->id, []);

            return [
                'id' => $holding->id,
                'name' => $holding->security_name,
                'ticker' => $holding->ticker,
                'asset_type' => $holding->asset_type,
                'current_value' => round($value, 2),
                'wrapper_percentage' => $exposure['portfolio_percentage'] ?? 0.0,
                'whole_relevant_portfolio_percentage' => $relevantPortfolioValue > 0
                    ? round(($value / $relevantPortfolioValue) * 100, 2)
                    : 0.0,
                'classified_exposure' => $exposure['exposures'] ?? [],
                'classification' => $exposure['classification'] ?? null,
                'fees' => $ocfPercent === null
                    ? [
                        'available' => false,
                        'unavailable_reason' => 'recorded_holding_charge_unavailable',
                    ]
                    : [
                        'available' => true,
                        'ocf_percent' => round($ocfPercent, 4),
                        'estimated_annual_cost' => round($value * ($ocfPercent / 100), 2),
                        'method' => 'recorded_ocf',
                    ],
                'performance' => $this->holdingPerformance($value, $costBasis),
            ];
        })->values()->all();

        return [
            'contract_version' => self::CONTRACT_VERSION,
            'wrapper_type' => $wrapperType,
            'wrapper_id' => $wrapperId,
            'wrapper_name' => $wrapperName,
            'recorded_wrapper_value' => round($recordedValue, 2),
            'analysis' => $analysis,
            'holdings' => $holdingRows,
            'performance_history' => $this->performanceHistory($snapshots, $snapshotColumns),
        ];
    }

    private function holdingPerformance(float $value, ?float $costBasis): array
    {
        if ($costBasis === null || $costBasis <= 0) {
            return [
                'available' => false,
                'unavailable_reason' => 'recorded_cost_basis_unavailable',
            ];
        }

        $gainLoss = $value - $costBasis;

        return [
            'available' => true,
            'gain_loss' => round($gainLoss, 2),
            'gain_loss_percent' => round(($gainLoss / $costBasis) * 100, 2),
            'method' => 'recorded_cost_basis',
        ];
    }

    private function performanceHistory(Collection|EloquentCollection $snapshots, array $columns): array
    {
        $points = $snapshots
            ->whereIn('column_name', $columns)
            ->sortBy('taken_at')
            ->map(fn ($snapshot) => [
                'date' => $snapshot->taken_at?->toDateString(),
                'value' => round((float) ($snapshot->value_gbp ?? $snapshot->value), 2),
                'currency' => 'GBP',
                'source' => $snapshot->ingest_source,
            ])
            ->values()
            ->all();

        return $points === []
            ? [
                'available' => false,
                'points' => [],
                'unavailable_reason' => 'dated_value_history_unavailable',
            ]
            : [
                'available' => true,
                'points' => $points,
                'method' => 'recorded_value_snapshots',
            ];
    }

    private function enteredBaseline(InvestmentAccount|DCPension $wrapper): ?array
    {
        if (! is_array($wrapper->entered_allocation_baseline) || $wrapper->entered_allocation_baseline === []) {
            return null;
        }

        return [
            'allocation' => $wrapper->entered_allocation_baseline,
            'source' => $wrapper->entered_allocation_source ?: 'user_entered',
            'effective_at' => $wrapper->entered_allocation_effective_at,
        ];
    }

    private function recommendedAllocation(InvestmentAccount|DCPension $wrapper, ?RiskProfile $riskProfile): ?array
    {
        $risk = ($wrapper->has_custom_risk && $wrapper->risk_preference)
            ? $wrapper->risk_preference
            : ($riskProfile?->risk_level ?: $riskProfile?->risk_tolerance);

        if (! $risk) {
            return null;
        }

        return [
            'allocation' => InvestmentDefaults::getTargetAllocation($risk),
            'source' => 'fynla_recommended_asset_allocation',
            'effective_at' => now()->toDateString(),
        ];
    }

    private function investmentWrapperType(InvestmentAccount $account): string
    {
        $accountType = strtolower((string) $account->account_type);
        $isaType = strtolower((string) $account->isa_type);

        if (str_contains($accountType, 'isa') && in_array($isaType, ['stocks_and_shares', 'stocks_shares', 'stocks and shares'], true)) {
            return 'stocks_and_shares_isa';
        }

        return 'investment_account';
    }
}
