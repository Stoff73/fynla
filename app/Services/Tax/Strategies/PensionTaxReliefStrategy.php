<?php

declare(strict_types=1);

namespace App\Services\Tax\Strategies;

use App\DataTransferObjects\StrategyRecommendation;
use App\Enums\StrategyCategory;
use App\Enums\StrategyPriority;
use App\Services\Tax\Strategies\Contract\TaxStrategy;
use App\Services\Tax\TaxStrategyMath;
use App\Services\TaxConfigService;

/**
 * Pension tax relief below the Personal Allowance taper (CSJ ruling
 * 2026-09-25: suggested for every band). Above the taper threshold,
 * IncomeBandStrategy owns the pension items.
 *
 *   - Higher rate: the slice taxed at the higher rate, relieved at that rate.
 *   - Basic rate:  a tenth of relevant earnings less what already goes in,
 *                  relieved at the basic rate and capped at income above the
 *                  Personal Allowance so relief never exceeds tax paid.
 */
final class PensionTaxReliefStrategy implements TaxStrategy
{
    // ponytail: mirrors the /savetax funnel estimate (SaveTaxEstimateService)
    // so the plan keeps the funnel's promise; a user-set target replaces it if
    // one is ever captured.
    private const BASIC_RATE_SHARE_OF_EARNINGS = 0.10;

    public function __construct(
        private readonly TaxStrategyMath $math,
        private readonly TaxConfigService $taxConfig,
    ) {}

    public function generate(TaxStrategyContext $context): array
    {
        $user = $context->user;

        $taperThreshold = (float) ($this->taxConfig->getIncomeTax()['personal_allowance_taper_threshold'] ?? 0);
        if ($this->math->adjustedNetIncomeFor($user) > $taperThreshold) {
            return [];
        }

        $earnings = (float) ($user->annual_employment_income ?? 0) + (float) ($user->annual_self_employment_income ?? 0);
        $age = $this->math->ageOf($user->date_of_birth);
        $availableAA = $this->math->availableAnnualAllowance($user, $context->overrides);
        $taxable = $this->math->taxableIncomeFor($user);
        $aboveAllowance = $taxable - $this->math->personalAllowanceFor($user);
        if ($earnings <= 0 || ($age !== null && $age >= 75) || $availableAA <= 0 || $aboveAllowance <= 0) {
            return [];
        }

        $band = $this->math->bandFromIncomeFor($user, $taxable);
        $contribution = $band === 'higher'
            ? min($taxable - $this->math->bandThresholdsFor($user)['higher'], $availableAA, $earnings)
            : min(
                $earnings * self::BASIC_RATE_SHARE_OF_EARNINGS - $this->math->estimatePensionContributionThisYear($user, $context->overrides),
                $availableAA,
                $aboveAllowance,
            );

        $display = (int) (round($contribution / 100) * 100);
        if ($display < 100) {
            return [];
        }

        $rate = $this->math->bandRateForBand($band);
        $saving = round($display * $rate, 2);
        $ratePct = (int) round($rate * 100);
        $basicPct = (int) round($this->math->bandRateForBand('basic') * 100);

        return [new StrategyRecommendation(
            type: $band === 'higher' ? 'pension_relief_higher_rate' : 'pension_relief_basic_rate',
            category: StrategyCategory::IncomeBand,
            priority: $band === 'higher' ? StrategyPriority::High : StrategyPriority::Medium,
            title: sprintf('Pay £%s more into your pension and save £%s in tax', number_format($display), number_format((int) round($saving))),
            description: $band === 'higher'
                ? sprintf(
                    'Pension contributions get tax relief at your highest rate. £%s of your income is taxed at %d%%, so paying that amount into a pension saves £%s this year. A workplace scheme gives the relief through your pay; for a personal pension the provider adds %d%% and you claim the rest through Self Assessment.',
                    number_format($display), $ratePct, number_format((int) round($saving)), $basicPct,
                )
                : sprintf(
                    'Every £%s you pay into a pension gets %d%% tax relief. Paying in £%s more this year saves £%s of income tax.',
                    number_format(100), $ratePct, number_format($display), number_format((int) round($saving)),
                ),
            estimatedAnnualTaxSaved: $saving,
            extra: [
                'suggested_contribution' => (float) $display,
                'relief_rate' => $rate,
                'tax_band' => $band,
            ],
        )];
    }
}
