<?php

declare(strict_types=1);

namespace App\Services\Tax\Strategies;

use App\DataTransferObjects\StrategyRecommendation;
use App\Enums\StrategyCategory;
use App\Enums\StrategyPriority;
use App\Services\Retirement\PensionContributionRule;
use App\Services\Retirement\SalarySacrificeAnalyzer;
use App\Services\Stores\PensionStore;
use App\Services\Tax\Strategies\Contract\TaxStrategy;

/**
 * Strategy #4 — Salary Sacrifice for National Insurance Relief.
 *
 * Fires for an employed user who has a workplace DC pension where
 * salary_sacrifice is null or false. Saving = the employee National
 * Insurance the calculator charges on today's pay less what it charges once
 * the contribution is sacrificed, plus (if the employer rebates a share of
 * their NI saving) the employer NI on the contribution × rebate_pct. Both
 * are priced by SalarySacrificeAnalyzer, the one place that does (Rule 20).
 */
final class SalarySacrificeNiStrategy implements TaxStrategy
{
    public function __construct(
        private readonly SalarySacrificeAnalyzer $analyzer,
    ) {}

    public function generate(TaxStrategyContext $context): array
    {
        $user = $context->user;

        // Two writers exist for employment_status: the savetax bubble maps
        // "Full-time" to 'employed' while the profile form writes 'full_time'.
        // Salary sacrifice needs an employer, so accept the whole employed
        // family; self_employed/retired/unemployed have no employer to
        // sacrifice with.
        if (! in_array((string) ($user->employment_status ?? ''), ['employed', 'full_time', 'part_time'], true)) {
            return [];
        }

        $salary = $this->analyzer->payBeforeSacrifice($user);

        // Workplace schemes only: a SIPP or personal pension is paid from
        // money already received and cannot be salary-sacrificed (B1).
        $eligiblePensions = app(PensionStore::class)
            ->forUserByType($user, 'dc')
            ->filter(fn ($p) => empty($p->salary_sacrifice) && PensionContributionRule::isWorkplace($p));

        $annualContribution = (float) $eligiblePensions->sum(
            fn ($p) => PensionContributionRule::monthlyEmployee($p, $salary) * 12
        );
        if ($annualContribution <= 0) {
            return [];
        }

        // National Insurance is charged on pay after the sacrifice already in
        // place; the saving is what the calculator stops charging once this
        // contribution joins it.
        $employeeSaving = $this->analyzer->employeeNiSaving($this->analyzer->payAfterSacrifice($user), $annualContribution);

        $rebatePct = (float) $eligiblePensions->max('employer_ni_rebate_pct');
        $employerSaving = $rebatePct > 0
            ? $this->analyzer->employerNiSaving($annualContribution) * $rebatePct
            : 0.0;

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
