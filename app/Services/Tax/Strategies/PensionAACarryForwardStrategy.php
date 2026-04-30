<?php

declare(strict_types=1);

namespace App\Services\Tax\Strategies;

use App\DataTransferObjects\StrategyRecommendation;
use App\Enums\StrategyCategory;
use App\Enums\StrategyPriority;
use App\Models\PensionInputHistory;
use App\Services\Tax\Strategies\Contract\TaxStrategy;
use App\Services\Tax\TaxStrategyMath;
use App\Services\TaxConfigService;

/**
 * Strategy #3 — Pension Annual Allowance Carry-Forward.
 *
 * Fires when the user is in the higher or additional band, has not maxed
 * the current year's AA, AND has unused AA from the previous three tax years.
 * Saving = unused_carry_forward × user_marginal_rate.
 *
 * Carry-forward window: HMRC allows looking back 3 tax years. We sum
 * max(0, AA_for_year - input_for_year) over the most recent 3 entries in
 * pension_input_history. AA is held at the current value across the window
 * — a conservative simplification (AA was the same £40k/£60k over the
 * relevant period); refine if HMRC changes mid-window.
 */
final class PensionAACarryForwardStrategy implements TaxStrategy
{
    private const LOOKBACK_YEARS = 3;

    public function __construct(
        private readonly TaxStrategyMath $math,
        private readonly TaxConfigService $taxConfig,
    ) {}

    public function generate(TaxStrategyContext $context): array
    {
        $user = $context->user;

        $band = $this->math->bandFromIncome($this->math->taxableIncomeFor($user));
        if (! in_array($band, ['higher', 'additional'], true)) {
            return [];
        }

        $aa = (float) ($this->taxConfig->getPensionAllowances()['annual_allowance'] ?? 60000);
        $currentInput = $this->math->estimatePensionContributionThisYear($user, $context->overrides);
        if ($currentInput >= $aa) {
            return [];
        }

        $history = PensionInputHistory::query()
            ->where('user_id', $user->id)
            ->orderByDesc('tax_year')
            ->limit(self::LOOKBACK_YEARS)
            ->get(['tax_year', 'pension_input_amount']);

        if ($history->isEmpty()) {
            return [];
        }

        $unused = 0.0;
        foreach ($history as $row) {
            $unused += max(0.0, $aa - (float) $row->pension_input_amount);
        }

        if ($unused <= 0) {
            return [];
        }

        $marginalRate = $this->math->bandRateFor($user);
        $saving = $unused * $marginalRate;

        if ($saving < 1) {
            return [];
        }

        return [new StrategyRecommendation(
            type: 'pension_aa_carry_forward',
            category: StrategyCategory::Allowance,
            priority: StrategyPriority::Medium,
            title: sprintf(
                'Carry forward up to £%s of unused Pension Allowance',
                number_format((int) round($unused / 1000) * 1000),
            ),
            description: sprintf(
                'You contributed below the £%s Pension Annual Allowance in each of the last 3 tax years, leaving around £%s of headroom you can still use. At your marginal rate that\'s a potential £%s of income-tax relief if you have surplus income to contribute.',
                number_format((int) $aa),
                number_format((int) round($unused / 1000) * 1000),
                number_format((int) round($saving)),
            ),
            estimatedAnnualTaxSaved: round($saving, 2),
            extra: [
                'unused_carry_forward' => round($unused, 2),
                'marginal_rate' => $marginalRate,
                'lookback_years' => self::LOOKBACK_YEARS,
                'current_year_input' => round($currentInput, 2),
                'annual_allowance' => $aa,
            ],
        )];
    }
}
