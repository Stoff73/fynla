<?php

declare(strict_types=1);

namespace App\Services\Tax\Strategies;

use App\DataTransferObjects\StrategyRecommendation;
use App\Enums\StrategyCategory;
use App\Enums\StrategyPriority;
use App\Models\DCPension;
use App\Services\Tax\Strategies\Contract\TaxStrategy;
use App\Services\TaxConfigService;

/**
 * Strategy #4 — Salary Sacrifice for National Insurance Relief.
 *
 * Fires for an employed user who has a workplace DC pension where
 * salary_sacrifice is null or false. Saving = annual_contribution × the
 * employee's marginal NI rate, plus (if the employer rebates a share of
 * their NI saving) annual_contribution × employer_NI_rate × rebate_pct.
 * NI rates and the upper-earnings-limit are read from TaxConfigService.
 */
final class SalarySacrificeNiStrategy implements TaxStrategy
{
    public function __construct(
        private readonly TaxConfigService $taxConfig,
    ) {}

    public function generate(TaxStrategyContext $context): array
    {
        $user = $context->user;

        if ((string) ($user->employment_status ?? '') !== 'employed') {
            return [];
        }

        $eligiblePensions = DCPension::query()
            ->where('user_id', $user->id)
            ->where(function ($q) {
                $q->whereNull('salary_sacrifice')->orWhere('salary_sacrifice', false);
            })
            ->where('monthly_contribution_amount', '>', 0)
            ->get(['id', 'monthly_contribution_amount', 'employer_ni_rebate_pct']);

        if ($eligiblePensions->isEmpty()) {
            return [];
        }

        $annualContribution = (float) $eligiblePensions->sum(fn ($p) => (float) $p->monthly_contribution_amount * 12);
        if ($annualContribution <= 0) {
            return [];
        }

        $ni = $this->taxConfig->getNationalInsurance();
        $employee = $ni['class_1']['employee'] ?? [];
        $employer = $ni['class_1']['employer'] ?? [];
        $uel = (float) ($employee['upper_earnings_limit'] ?? 50270);
        $mainRate = (float) ($employee['main_rate'] ?? 0.08);
        $additionalRate = (float) ($employee['additional_rate'] ?? 0.02);
        $employerRate = (float) ($employer['rate'] ?? 0.15);

        $income = (float) ($user->annual_employment_income ?? 0);
        $afterSacrifice = $income - $annualContribution;

        // NI saving applies on the slice between (income − contribution) and income.
        if ($income <= $uel) {
            $employeeSaving = $annualContribution * $mainRate;
        } elseif ($afterSacrifice >= $uel) {
            $employeeSaving = $annualContribution * $additionalRate;
        } else {
            $belowUelSlice = $uel - $afterSacrifice;
            $aboveUelSlice = $income - $uel;
            $employeeSaving = $belowUelSlice * $mainRate + $aboveUelSlice * $additionalRate;
        }

        $rebatePct = (float) $eligiblePensions->max('employer_ni_rebate_pct');
        $employerSaving = 0.0;
        if ($rebatePct > 0) {
            $employerSaving = $annualContribution * $employerRate * $rebatePct;
        }

        $totalSaving = $employeeSaving + $employerSaving;
        if ($totalSaving < 1) {
            return [];
        }

        $description = $rebatePct > 0
            ? sprintf(
                'Switching your £%s annual workplace pension contribution to salary sacrifice saves £%s in National Insurance every year. Your employer rebates %d%% of their NI saving back into the pot on top.',
                number_format((int) $annualContribution),
                number_format((int) round($totalSaving)),
                (int) round($rebatePct * 100),
            )
            : sprintf(
                'Switching your £%s annual workplace pension contribution to salary sacrifice saves £%s in National Insurance every year, with no change to your take-home pay.',
                number_format((int) $annualContribution),
                number_format((int) round($totalSaving)),
            );

        return [new StrategyRecommendation(
            type: 'salary_sacrifice_ni',
            category: StrategyCategory::Allowance,
            priority: StrategyPriority::Medium,
            title: sprintf(
                'Save around £%s a year by moving your pension contributions to salary sacrifice',
                number_format((int) round($totalSaving)),
            ),
            description: $description,
            estimatedAnnualTaxSaved: round($totalSaving, 2),
            extra: [
                'annual_contribution' => round($annualContribution, 2),
                'employee_ni_saving' => round($employeeSaving, 2),
                'employer_ni_rebate_pct' => $rebatePct,
                'employer_ni_rebate_saving' => round($employerSaving, 2),
            ],
        )];
    }
}
