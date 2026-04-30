<?php

declare(strict_types=1);

namespace App\Services\Tax\Strategies;

use App\DataTransferObjects\StrategyRecommendation;
use App\Enums\StrategyCategory;
use App\Enums\StrategyPriority;
use App\Models\SavingsAccount;
use App\Services\Tax\Strategies\Contract\TaxStrategy;
use App\Services\Tax\TaxStrategyMath;

/**
 * Strategy #15 — Joint Savings Split for Personal Savings Allowance Doubling.
 *
 * Fires when a married user holds sole-name non-ISA savings generating
 * interest above their own Personal Savings Allowance, AND their spouse has
 * at least basic-rate band PSA capacity unused. Skipped for additional-rate
 * taxpayers because PSA = £0 at that band.
 */
final class JointSavingsStrategy implements TaxStrategy
{
    public function __construct(
        private readonly TaxStrategyMath $math,
    ) {}

    public function generate(TaxStrategyContext $context): array
    {
        $user = $context->user;
        $household = $context->household;
        $mode = $context->mode;

        $isMarried = $user->marital_status === 'married'
            || in_array($mode, ['dual_earner', 'single_earner_couple'], true);
        if (! $isMarried) {
            return [];
        }

        $userBand = $this->math->bandFromIncome((float) ($user->annual_employment_income ?? 0));
        if ($userBand === 'additional') {
            return [];
        }

        // Sole-name non-ISA savings — joint accounts (joint_owner_id set) are
        // already split 50/50 by HMRC default and cannot benefit further.
        $soleSavings = SavingsAccount::query()
            ->where('user_id', $user->id)
            ->whereNull('joint_owner_id')
            ->where('is_isa', false)
            ->get(['current_balance', 'interest_rate']);

        if ($soleSavings->isEmpty()) {
            return [];
        }

        $balance = (float) $soleSavings->sum('current_balance');
        // Mixed convention guard: interest_rate is stored as percent (4.0)
        // by onboarding/seeders and as decimal (0.04) by the factory.
        // Normalise then average so the £ figures line up with the rest
        // of the dashboard.
        $normalisedRates = $soleSavings->map(function ($acc) {
            $r = (float) $acc->interest_rate;

            return $r > 1 ? $r / 100 : $r;
        });
        $avgRate = $normalisedRates->count() > 0 ? (float) $normalisedRates->avg() : 0.0;
        $interest = $balance * $avgRate;
        $userPsa = $this->math->psaForBand($userBand);

        if ($interest <= $userPsa || $avgRate <= 0) {
            return [];
        }

        $spouseBand = (string) ($household?->spouse_psa_band ?? 'basic');
        $spousePsa = $this->math->psaForBand($spouseBand);
        if ($spousePsa <= 0) {
            return [];
        }

        // Slice currently taxed in user's name that would slot into spouse's PSA on splitting.
        $taxableSlice = $interest - $userPsa;
        $shelterableSlice = min($taxableSlice, $spousePsa);
        $marginalRate = $this->math->bandRateFor($user);
        $saving = $shelterableSlice * $marginalRate;

        if ($saving < 1) {
            return [];
        }

        return [new StrategyRecommendation(
            type: 'joint_savings_psa_split',
            category: StrategyCategory::Household,
            priority: StrategyPriority::Low,
            title: 'Split your savings between you and your spouse to use both Savings Allowances',
            description: sprintf(
                'Holding £%s of cash in one name uses only your £%s Personal Savings Allowance. Putting half in your spouse\'s name claims their £%s allowance too — saving around £%s a year.',
                number_format((int) $balance),
                number_format((int) $userPsa),
                number_format((int) $spousePsa),
                number_format((int) round($saving)),
            ),
            estimatedAnnualTaxSaved: round($saving, 2),
            extra: [
                'sole_balance' => round($balance, 2),
                'user_psa' => $userPsa,
                'spouse_psa' => $spousePsa,
                'shelterable_interest' => round($shelterableSlice, 2),
            ],
        )];
    }
}
