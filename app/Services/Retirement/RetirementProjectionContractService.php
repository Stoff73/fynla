<?php

declare(strict_types=1);

namespace App\Services\Retirement;

use App\Models\User;
use App\Services\Settings\AssumptionsService;
use App\Services\TaxConfigService;
use Carbon\CarbonImmutable;

final class RetirementProjectionContractService
{
    public function __construct(
        private readonly RetirementProjectionService $projectionService,
        private readonly AssumptionsService $assumptionsService,
        private readonly TaxConfigService $taxConfig,
        // W-0516 — the one home for State Pension age.
        private readonly StatePensionAgeResolver $statePensionAge,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(User $user): array
    {
        $user->loadMissing(['dcPensions', 'dbPensions', 'statePension', 'retirementProfile']);

        $targetRetirementAge = $user->target_retirement_age
            ?? $user->retirementProfile?->target_retirement_age;

        $withdrawalRate = (float) $this->taxConfig->get(
            'retirement.withdrawal_rates.sustainable',
            0.047,
        );
        $projectionEndAge = (int) $this->taxConfig->get('retirement.projection_end_age', 100);
        $pensionAssumptions = $this->assumptionsService->getTypeAssumptions($user, 'pensions');
        $annualReturnPercent = (float) ($pensionAssumptions['return_rate'] ?? 0)
            - (float) ($pensionAssumptions['fees']['total'] ?? 0);
        $compoundPeriods = (int) ($pensionAssumptions['compound_periods'] ?? 12);
        $currentAge = $user->date_of_birth?->age ?? 40;
        $products = [];
        $uncertaintyProducts = [];
        $warnings = [];

        foreach ($user->dcPensions as $pension) {
            $projection = $this->projectionService->projectIndividualDCPension(
                (int) $pension->id,
                (int) $user->id,
            );
            $commencementAge = (int) ($targetRetirementAge
                ?? $pension->retirement_age
                ?? $projection['retirement_age']
                ?? 67);
            // The legacy Monte Carlo service intentionally projects for at least one year.
            // The planning contract must not grow an already-commenced pension, so derive
            // the deterministic horizon directly from this product's own commencement age.
            $yearsToRetirement = max(0, $commencementAge - $currentAge);
            $monthlyContribution = (float) ($projection['monthly_contribution'] ?? 0);
            $planningValue = self::calculatePlanningValue(
                currentValue: (float) ($pension->current_fund_value ?? 0),
                monthlyContribution: $monthlyContribution,
                annualReturnPercent: $annualReturnPercent,
                years: $yearsToRetirement,
                compoundPeriods: $compoundPeriods,
            );

            $products[] = [
                'resource_type' => 'dc_pension',
                'resource_id' => (int) $pension->id,
                'name' => (string) ($pension->scheme_name ?: $pension->provider ?: 'Defined Contribution Pension'),
                'commencement_age' => $commencementAge,
                'current_value' => round((float) ($pension->current_fund_value ?? 0), 2),
                'monthly_contribution' => round($monthlyContribution, 2),
                'projected_value' => round((float) $planningValue, 2),
                'annual_income' => round((float) $planningValue * $withdrawalRate, 2),
                'income_method' => 'sustainable_withdrawal_rate',
            ];
            $uncertaintyProducts[] = [
                'resource_type' => 'dc_pension',
                'resource_id' => (int) $pension->id,
                'percentile_20_at_retirement' => round((float) ($projection['percentile_20_at_retirement'] ?? 0), 2),
                'percentile_50_at_retirement' => round((float) ($projection['median_at_retirement'] ?? 0), 2),
                'volatility_percent' => isset($projection['volatility'])
                    ? round((float) $projection['volatility'], 2)
                    : null,
            ];
        }

        foreach ($user->dbPensions as $pension) {
            $annualIncome = $pension->projected_annual_pension_at_nra_gbp
                ?: $pension->accrued_annual_pension
                ?: 0;
            $products[] = [
                'resource_type' => 'db_pension',
                'resource_id' => (int) $pension->id,
                'name' => (string) ($pension->scheme_name ?: 'Defined Benefit Pension'),
                'commencement_age' => (int) ($pension->normal_retirement_age ?? 65),
                'current_value' => null,
                'projected_value' => null,
                'annual_income' => round((float) $annualIncome, 2),
                'income_method' => 'guaranteed_scheme_income',
            ];
        }

        $statePension = $user->statePension;
        if ($statePension !== null) {
            $products[] = [
                'resource_type' => 'state_pension',
                'resource_id' => (int) $statePension->id,
                'name' => 'State Pension',
                'commencement_age' => $this->statePensionAge->forUser($user),
                'current_value' => null,
                'projected_value' => null,
                'annual_income' => round((float) ($statePension->state_pension_forecast_annual ?? 0), 2),
                'income_method' => 'recorded_state_pension_forecast',
            ];
        }

        usort($products, static function (array $left, array $right): int {
            return [$left['commencement_age'], $left['resource_type'], $left['resource_id']]
                <=> [$right['commencement_age'], $right['resource_type'], $right['resource_id']];
        });

        $firstCommencementAge = $products === []
            ? (int) ($targetRetirementAge ?? 67)
            : min(array_column($products, 'commencement_age'));
        $targetAge = (int) ($targetRetirementAge ?? $firstCommencementAge);
        $planningTotalAtTargetAge = array_sum(array_column(array_filter(
            $products,
            static fn (array $product): bool => $product['commencement_age'] <= $targetAge,
        ), 'annual_income'));

        return [
            'contract_version' => 'retirement_projection_v1',
            'as_of' => CarbonImmutable::now()->toDateString(),
            'target_retirement_age' => $targetAge,
            'projection_end_age' => $projectionEndAge,
            'planning_total_at_target_age' => round($planningTotalAtTargetAge, 2),
            'products' => $products,
            'age_bands' => self::reconcileAgeBands($products, $firstCommencementAge, $projectionEndAge),
            'assumptions' => [
                'sustainable_withdrawal_rate' => [
                    'decimal' => $withdrawalRate,
                    'percent' => round($withdrawalRate * 100, 2),
                    'source' => 'tax_configuration',
                ],
                'growth_rate_percent' => round((float) ($pensionAssumptions['return_rate'] ?? 0), 2),
                'net_growth_rate_percent' => round($annualReturnPercent, 2),
                'inflation_rate_percent' => round((float) ($pensionAssumptions['inflation_rate'] ?? 0), 2),
                'fee_rate_percent' => round((float) ($pensionAssumptions['fees']['total'] ?? 0), 2),
                'compound_periods' => (int) ($pensionAssumptions['compound_periods'] ?? 12),
                'basis' => 'nominal',
                'has_user_overrides' => (bool) ($pensionAssumptions['has_overrides'] ?? false),
            ],
            'uncertainty' => [
                'method' => 'monte_carlo_percentile_bands',
                'primary_projection' => false,
                'products' => $uncertaintyProducts,
            ],
            'warnings' => $warnings,
        ];
    }

    /**
     * @param  array<int, array{
     *     resource_type: string,
     *     resource_id: int,
     *     name: string,
     *     commencement_age: int,
     *     annual_income: float
     * }>  $products
     * @return array<int, array{
     *     start_age: int,
     *     end_age: int,
     *     annual_income: float,
     *     source_ids: array<int, string>
     * }>
     */
    public static function reconcileAgeBands(array $products, int $startAge, int $endAge): array
    {
        if ($endAge < $startAge) {
            return [];
        }

        $eligible = array_values(array_filter(
            $products,
            static fn (array $product): bool => $product['commencement_age'] <= $endAge
        ));

        if ($eligible === []) {
            return [];
        }

        usort($eligible, static function (array $left, array $right): int {
            return [$left['commencement_age'], $left['resource_type'], $left['resource_id']]
                <=> [$right['commencement_age'], $right['resource_type'], $right['resource_id']];
        });

        $breakpoints = [$startAge];
        foreach ($eligible as $product) {
            if ($product['commencement_age'] > $startAge) {
                $breakpoints[] = $product['commencement_age'];
            }
        }

        $breakpoints = array_values(array_unique($breakpoints));
        sort($breakpoints);

        $bands = [];
        foreach ($breakpoints as $index => $bandStart) {
            $active = array_values(array_filter(
                $eligible,
                static fn (array $product): bool => $product['commencement_age'] <= $bandStart
            ));

            if ($active === []) {
                continue;
            }

            $nextBreakpoint = $breakpoints[$index + 1] ?? null;
            $bands[] = [
                'start_age' => $bandStart,
                'end_age' => $nextBreakpoint === null ? $endAge : $nextBreakpoint - 1,
                'annual_income' => round(array_sum(array_column($active, 'annual_income')), 2),
                'source_ids' => array_map(
                    static fn (array $product): string => $product['resource_type'].':'.$product['resource_id'],
                    $active
                ),
            ];
        }

        return $bands;
    }

    public static function calculatePlanningValue(
        float $currentValue,
        float $monthlyContribution,
        float $annualReturnPercent,
        int $years,
        int $compoundPeriods = 12,
    ): float {
        $years = max(0, $years);
        $compoundPeriods = max(1, $compoundPeriods);
        $numberOfPeriods = $years * $compoundPeriods;
        $contributionPerPeriod = ($monthlyContribution * 12) / $compoundPeriods;
        $periodicReturn = ($annualReturnPercent / 100) / $compoundPeriods;

        if ($numberOfPeriods === 0) {
            return round($currentValue, 2);
        }

        if (abs($periodicReturn) < 0.0000001) {
            return round($currentValue + ($contributionPerPeriod * $numberOfPeriods), 2);
        }

        $growthFactor = (1 + $periodicReturn) ** $numberOfPeriods;
        $futureCurrentValue = $currentValue * $growthFactor;
        $futureContributions = $contributionPerPeriod * (($growthFactor - 1) / $periodicReturn);

        return round($futureCurrentValue + $futureContributions, 2);
    }
}
