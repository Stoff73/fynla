<?php

declare(strict_types=1);

namespace App\Support;

use App\Constants\GateRoutes;

/**
 * Where a tax plan item's "next step" link goes, and what it says: one map for
 * web, /m and iOS. Each client opens the named screen through its own
 * destination map (resources/js/utils/semanticDestinations.js,
 * resources/mobile/navigation/semanticDestinations.js).
 *
 * The web and /m Tax Strategy pages each kept their own copy, and they
 * disagreed: web linked pension items to "/pension" and investment items to
 * "/investments", neither of which is a web page (both fell through to the
 * dashboard), and "See income & tax" opened Personal details; /m named the same
 * links "Open retirement" and covered seven types (regression walk 2026-10-09, R6).
 */
final class StrategyNextStep
{
    private const PENSIONS = ['label' => 'Open pensions', 'screen' => GateRoutes::RETIREMENT];

    private const SAVINGS = ['label' => 'Open savings & ISAs', 'screen' => GateRoutes::SAVINGS];

    private const INVESTMENTS = ['label' => 'Open investments', 'screen' => GateRoutes::INVESTMENT];

    private const INCOME = ['label' => 'See income & tax', 'screen' => GateRoutes::INCOME];

    /**
     * Keyed on every type a tax strategy emits (app/Services/Tax/Strategies);
     * StrategyNextStepTest fails on a type with no step or a key no strategy
     * emits (the first map carried four names nothing emits and missed six that
     * are, among them the main pension relief item).
     *
     * @var array<string, array{label: string, screen: string}>
     */
    private const MAP = [
        'pension_tax_relief' => self::PENSIONS,
        'pa_taper_rescue' => self::PENSIONS,
        'additional_rate_avoidance' => self::PENSIONS,
        'pension_aa_carry_forward' => self::PENSIONS,
        'salary_sacrifice_ni' => self::PENSIONS,
        'non_earner_spouse_pension' => self::PENSIONS,
        'junior_pension' => self::PENSIONS,
        'tapered_annual_allowance' => self::PENSIONS,
        'isa_topup_vs_psa' => self::SAVINGS,
        'joint_savings_psa_split' => self::SAVINGS,
        'savings_to_spouse' => self::SAVINGS,
        'isa_topup_spouse' => self::SAVINGS,
        'isa_coordination' => self::SAVINGS,
        'lifetime_isa' => self::SAVINGS,
        'junior_isa' => self::SAVINGS,
        'bed_and_isa' => self::INVESTMENTS,
        'dividend_allowance_harvest' => self::INVESTMENTS,
        'gia_to_spouse' => self::INVESTMENTS,
        'gia_rebalance' => self::INVESTMENTS,
        'gift_aid_higher_rate_relief' => self::INCOME,
        'marriage_allowance_transfer' => self::INCOME,
    ];

    /**
     * @return array{label: string, destination: array{screen: string, params: object, fallback: string}}|null
     */
    public static function for(string $strategyType): ?array
    {
        $step = self::MAP[$strategyType] ?? null;

        return $step === null ? null : [
            'label' => $step['label'],
            'destination' => GateRoutes::destination($step['screen']),
        ];
    }
}
