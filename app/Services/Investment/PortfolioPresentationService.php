<?php

declare(strict_types=1);

namespace App\Services\Investment;

use App\Constants\InvestmentDefaults;
use App\Models\DCPension;
use App\Models\Investment\InvestmentAccount;
use App\Models\Investment\RiskProfile;
use App\Services\Risk\RiskPreferenceService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class PortfolioPresentationService
{
    /**
     * Additive changes keep the version: no surface gates on its value (web, /m
     * CanonicalPortfolio.vue and native CanonicalPortfolioModels.swift only carry
     * it), and the wrapper-level `fees` block decodes as optional where an older
     * payload lacks it.
     */
    public const CONTRACT_VERSION = 'financial_portfolio_v1';

    /**
     * The growth assumed for the ten-year charges illustration, before charges.
     *
     * Not a forecast and not a recommendation: an illustrative rate, stated on
     * screen beside the figures it produces. Every web panel that showed a
     * ten-year charges figure assumed 5% on the client (AccountFeesPanel,
     * AccountHoldingsPanel, PensionDetailInline, FeeBreakdown), each with its own
     * arithmetic. It now lives here once and is published with the figures as
     * `assumed_growth_percent`, so the caption cannot disagree with the sum.
     */
    public const ILLUSTRATIVE_GROWTH_PERCENT = 5.0;

    public const FEE_IMPACT_YEARS = 10;

    /**
     * Charging periods per year for a fixed platform fee. Mirrors the
     * `platform_fee_frequency` enum on both wrappers (migrations
     * 2026_01_15_111814 and 2026_03_25_164053).
     */
    private const FIXED_FEE_PERIODS_PER_YEAR = [
        'monthly' => 12,
        'quarterly' => 4,
        'annually' => 1,
    ];

    public function __construct(
        private readonly PortfolioExposureService $exposureService,
        private readonly RiskPreferenceService $riskService,
        private readonly ContributionEstimatorService $contributionEstimator,
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
            $account,
            // The one contribution figure an investment account carries, the one
            // its projection uses (ContributionEstimatorService).
            $this->contributionEstimator->estimateMonthlyContribution($account),
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
            $pension,
            // What reaches the pot each month (DCPension `monthly_contribution` accessor).
            (float) ($pension->monthly_contribution ?? 0),
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
        InvestmentAccount|DCPension $wrapper,
        float $monthlyContribution,
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
                // W-0442 acceptance 3. Units, purchase price, current price and purchase
                // date are captured, validated and stored (W-0039), and the portfolio
                // contract never carried them — so `/m` could not show them however its
                // template was written, while web's tables show all four.
                //
                // Nullable rather than defaulted: a holding recorded without a purchase
                // price has NOT been bought for nothing, and `HoldingResource:30-35`
                // serves them null for the same reason. The surface prints an em dash.
                'quantity' => $holding->quantity !== null ? (float) $holding->quantity : null,
                'purchase_price' => $holding->purchase_price !== null ? (float) $holding->purchase_price : null,
                'current_price' => $holding->current_price !== null ? (float) $holding->current_price : null,
                'purchase_date' => $holding->purchase_date?->toDateString(),
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
            'fees' => $this->wrapperFees($wrapper, $recordedValue, $holdingRows, $monthlyContribution),
            'performance_history' => $this->performanceHistory($snapshots, $snapshotColumns),
        ];
    }

    /**
     * Every charge on the wrapper, priced once for every surface (CSJ 2026-10-01,
     * item 7a: web priced these in five components; /m and native had none).
     *
     * Only what is recorded is priced. A platform or adviser fee that was never
     * entered is `recorded: false` with no figure rather than 0%, and a holding
     * with no recorded charge is counted in `holdings_without_recorded_ocf`
     * rather than given an estimate. Fee columns hold percentages
     * (0.45 = 0.45%), per the `decimal:4` casts on InvestmentAccount and DCPension.
     *
     * @param  array<int, array<string, mixed>>  $holdingRows
     */
    private function wrapperFees(
        InvestmentAccount|DCPension $wrapper,
        float $recordedValue,
        array $holdingRows,
        float $monthlyContribution,
    ): array {
        $platform = $this->platformFee($wrapper, $recordedValue);

        $advisorPercent = $wrapper->advisor_fee_percent !== null ? (float) $wrapper->advisor_fee_percent : null;
        $advisorCost = $advisorPercent !== null ? $recordedValue * ($advisorPercent / 100) : null;

        $chargedValue = 0.0;
        $weightedCharge = 0.0;
        $withoutCharge = 0;
        foreach ($holdingRows as $row) {
            if (! ($row['fees']['available'] ?? false)) {
                $withoutCharge++;

                continue;
            }
            $chargedValue += (float) $row['current_value'];
            $weightedCharge += (float) $row['current_value'] * (float) $row['fees']['ocf_percent'];
        }
        // Σ value × OCF% / 100 — the per-holding `estimated_annual_cost` summed unrounded.
        $fundCost = $weightedCharge / 100;

        $totalCost = ($platform['annual_cost'] ?? 0.0) + ($advisorCost ?? 0.0) + $fundCost;
        $totalPercent = $recordedValue > 0 ? ($totalCost / $recordedValue) * 100 : null;

        return [
            'basis_value' => round($recordedValue, 2),
            'platform' => $platform,
            'advisor' => [
                'recorded' => $advisorPercent !== null,
                'percent' => $advisorPercent !== null ? round($advisorPercent, 4) : null,
                'annual_cost' => $advisorCost !== null ? round($advisorCost, 2) : null,
            ],
            'fund_charges' => [
                // Weighted over the holdings whose charge is recorded; the count
                // beside it says how many holdings that leaves out.
                'weighted_ocf_percent' => $chargedValue > 0 ? round($weightedCharge / $chargedValue, 4) : null,
                'annual_cost' => round($fundCost, 2),
                'holdings_count' => count($holdingRows),
                'holdings_without_recorded_ocf' => $withoutCharge,
            ],
            'total_annual_cost' => round($totalCost, 2),
            'total_percent' => $totalPercent !== null ? round($totalPercent, 4) : null,
            'ten_year_impact' => $this->feeImpact($recordedValue, $monthlyContribution * 12, $totalPercent ?? 0.0),
        ];
    }

    /**
     * The platform fee as recorded: a percentage of the wrapper, or a fixed amount
     * per charging period, annualised. Its effective percentage is published for
     * both, so a fixed fee and a percentage fee total together.
     */
    private function platformFee(InvestmentAccount|DCPension $wrapper, float $recordedValue): array
    {
        if ($wrapper->platform_fee_type === 'fixed') {
            if ($wrapper->platform_fee_amount === null) {
                return ['recorded' => false, 'type' => 'fixed', 'percent' => null, 'fixed_amount' => null, 'frequency' => null, 'annual_cost' => null];
            }

            $frequency = $wrapper->platform_fee_frequency ?: 'annually';
            $annualCost = (float) $wrapper->platform_fee_amount * (self::FIXED_FEE_PERIODS_PER_YEAR[$frequency] ?? 1);

            return [
                'recorded' => true,
                'type' => 'fixed',
                'percent' => $recordedValue > 0 ? round(($annualCost / $recordedValue) * 100, 4) : null,
                'fixed_amount' => round((float) $wrapper->platform_fee_amount, 2),
                'frequency' => $frequency,
                'annual_cost' => round($annualCost, 2),
            ];
        }

        if ($wrapper->platform_fee_percent === null) {
            return ['recorded' => false, 'type' => 'percentage', 'percent' => null, 'fixed_amount' => null, 'frequency' => null, 'annual_cost' => null];
        }

        $percent = (float) $wrapper->platform_fee_percent;

        return [
            'recorded' => true,
            'type' => 'percentage',
            'percent' => round($percent, 4),
            'fixed_amount' => null,
            'frequency' => null,
            'annual_cost' => round($recordedValue * ($percent / 100), 2),
        ];
    }

    /**
     * What charges cost over ten years at the illustrative growth rate.
     *
     * Each year the contribution goes in, the year's charges are taken from the
     * value at the total charge rate, and the rest grows. The same pot grown with
     * no charges gives `value_without_fees`; the gap between the two is
     * `total_impact`, of which `total_fees` was paid and `lost_growth` is what
     * those payments would have earned. Public so the cross-account summary runs
     * this arithmetic rather than a copy of it.
     *
     * @return array{years: int, assumed_growth_percent: float, annual_contribution: float, total_fees: float, value_without_fees: float, value_with_fees: float, lost_growth: float, total_impact: float}
     */
    public function feeImpact(float $startValue, float $annualContribution, float $totalFeePercent): array
    {
        $growth = self::ILLUSTRATIVE_GROWTH_PERCENT / 100;
        $feeRate = max(0.0, $totalFeePercent) / 100;

        $withFees = $startValue;
        $withoutFees = $startValue;
        $feesPaid = 0.0;
        for ($year = 0; $year < self::FEE_IMPACT_YEARS; $year++) {
            $withFees += $annualContribution;
            $feesPaid += $withFees * $feeRate;
            $withFees *= (1 + $growth - $feeRate);

            $withoutFees = ($withoutFees + $annualContribution) * (1 + $growth);
        }

        $totalImpact = max(0.0, $withoutFees - $withFees);

        return [
            'years' => self::FEE_IMPACT_YEARS,
            'assumed_growth_percent' => self::ILLUSTRATIVE_GROWTH_PERCENT,
            'annual_contribution' => round($annualContribution, 2),
            'total_fees' => round($feesPaid, 2),
            'value_without_fees' => round($withoutFees, 2),
            'value_with_fees' => round($withFees, 2),
            'lost_growth' => round(max(0.0, $totalImpact - $feesPaid), 2),
            'total_impact' => round($totalImpact, 2),
        ];
    }

    /**
     * Charges across several wrappers, summed from each wrapper's own `fees`
     * block so the summary cannot disagree with the accounts it lists.
     *
     * @param  array<int, array<string, mixed>>  $portfolios  forInvestmentAccount() / forDCPension() outputs
     */
    public function portfolioFeesSummary(array $portfolios): array
    {
        $value = 0.0;
        $platform = 0.0;
        $advisor = 0.0;
        $fund = 0.0;
        $contribution = 0.0;
        foreach ($portfolios as $portfolio) {
            $fees = $portfolio['fees'];
            $value += (float) $fees['basis_value'];
            $platform += (float) ($fees['platform']['annual_cost'] ?? 0);
            $advisor += (float) ($fees['advisor']['annual_cost'] ?? 0);
            $fund += (float) $fees['fund_charges']['annual_cost'];
            $contribution += (float) $fees['ten_year_impact']['annual_contribution'];
        }

        $total = $platform + $advisor + $fund;
        $percentOf = fn (float $cost): ?float => $value > 0 ? round(($cost / $value) * 100, 4) : null;

        return [
            'basis_value' => round($value, 2),
            'platform_annual_cost' => round($platform, 2),
            'platform_percent' => $percentOf($platform),
            'advisor_annual_cost' => round($advisor, 2),
            'advisor_percent' => $percentOf($advisor),
            'fund_annual_cost' => round($fund, 2),
            'fund_percent' => $percentOf($fund),
            'total_annual_cost' => round($total, 2),
            'total_percent' => $percentOf($total),
            'ten_year_impact' => $this->feeImpact($value, $contribution, $value > 0 ? ($total / $value) * 100 : 0.0),
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
        // Reads the preference itself, not the `has_custom_risk` flag beside it — no
        // client writes that flag on an investment account, so gating on it discarded
        // every override a user had set. See RiskPreferenceService::getProductRiskOverride.
        $risk = $this->riskService->getProductRiskOverride($wrapper)
            ?: ($riskProfile?->risk_level ?: $riskProfile?->risk_tolerance);

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
