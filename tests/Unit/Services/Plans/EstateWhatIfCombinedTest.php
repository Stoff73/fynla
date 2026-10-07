<?php

declare(strict_types=1);

use App\Services\Plans\EstatePlanService;
use Database\Seeders\TaxConfigurationSeeder;

/**
 * Item 9 (CSJ 2026-10-07): the Estate plan's "With actions" total added each
 * action's saving, but the larger gifts and the charity gift interact (a gift
 * lowers the estate the 10% test measures). Worked here on the Mitchell
 * household's own figures, from the engine.
 */
beforeEach(fn () => $this->seed(TaxConfigurationSeeder::class));

function mitchellWhatIf(array $enabled): array
{
    $data = [
        'summary' => ['iht_liability' => 349112.0, 'net_estate' => 964500.0, 'gross_estate' => 1135000.0],
        'iht_calculation' => ['total_net_estate' => 1728780.0, 'total_gross_assets' => 2021780.0, 'taxable_estate' => 872780.0, 'iht_rate' => 0.4],
        'charitable_analysis' => ['status' => 'below', 'baseline' => 1222780.0, 'charitable_total' => 0.0],
    ];
    $actions = [
        ['id' => 'estate_action_1', 'category' => 'charitable_bequest', 'impact_parameters' => []],
        ['id' => 'estate_action_4', 'category' => 'pet_gifting', 'impact_parameters' => ['gift' => 181000.0]],
    ];
    $method = new ReflectionMethod(EstatePlanService::class, 'buildWhatIfData');

    return $method->invoke(app(EstatePlanService::class), $data, array_values(array_filter($actions, fn ($a) => in_array($a['id'], $enabled, true))) ?: $actions);
}

it('works out the two actions together, not their sum', function () {
    $whatIf = mitchellWhatIf(['estate_action_1', 'estate_action_4']);

    // Gift out: taxable £691,780; charity 10% of (£1,222,780 − £181,000) = £104,178;
    // (£691,780 − £104,178) × 36% = £211,537 — saving £137,575, not £78,931 + £72,400.
    expect($whatIf['projected_scenario']['iht_liability'])->toBe(211536.72)
        ->and($whatIf['projected_scenario']['total_mitigation_savings'])->toBe(137575.28)
        ->and($whatIf['projected_by_enabled']['estate_action_1']['total_mitigation_savings'])->toBe(78931.28)
        ->and($whatIf['projected_by_enabled']['estate_action_4']['total_mitigation_savings'])->toBe(72400.0)
        ->and($whatIf['projected_by_enabled']['']['iht_liability'])->toBe(349112.0);
});

it('reports the household figures the tax is worked on', function () {
    $whatIf = mitchellWhatIf(['estate_action_1', 'estate_action_4']);

    // £1,728,780 − £349,112, not David's own £964,500 − £349,112.
    expect($whatIf['current_scenario']['estate_to_beneficiaries'])->toBe(1379668.0)
        ->and($whatIf['current_scenario']['effective_tax_rate'])->toBe(17.3)
        ->and($whatIf)->not->toHaveKey('frontend_calc_params');
});
