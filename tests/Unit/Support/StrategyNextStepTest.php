<?php

declare(strict_types=1);

use App\Support\StrategyNextStep;

/**
 * Every type a tax strategy emits, read from the strategy sources (named
 * `type:` arguments and `'type' =>` keys), so a new strategy without a step
 * fails here rather than showing a card with no link.
 *
 * @return list<string>
 */
function emittedStrategyTypes(): array
{
    $types = [];
    foreach (glob(dirname(__DIR__, 3).'/app/Services/Tax/Strategies/*.php') as $file) {
        preg_match_all("/(?:type: |'type' => )'([a-z_]+)'/", (string) file_get_contents($file), $matches);
        array_push($types, ...$matches[1]);
    }

    return array_values(array_unique($types));
}

it('gives every type a tax strategy emits a next step', function () {
    // Regression walk 2026-10-09, R6 retest: the main pension relief card
    // ("Pay £21,200 more into your pension…", type pension_tax_relief) had no
    // link while the salary sacrifice card beside it had one.
    $types = emittedStrategyTypes();

    expect($types)->toContain('pension_tax_relief');

    foreach ($types as $type) {
        expect(StrategyNextStep::for($type))->not->toBeNull("{$type} has no next step");
    }
});

it('maps no type that no strategy emits', function () {
    // The first map carried joint_savings_split, asset_shifting_savings,
    // asset_shifting_isa and cross_spouse_dividends from the old client maps;
    // no strategy emits any of them.
    $keys = array_keys((new ReflectionClassConstant(StrategyNextStep::class, 'MAP'))->getValue());

    expect(array_diff($keys, emittedStrategyTypes()))->toBe([]);
});

it('sends pension, savings, investment and income items to their screens', function () {
    expect(StrategyNextStep::for('pension_tax_relief'))->toMatchArray(['label' => 'Open pensions'])
        ->and(StrategyNextStep::for('pension_tax_relief')['destination']['screen'])->toBe('retirement')
        ->and(StrategyNextStep::for('isa_coordination')['destination']['screen'])->toBe('savings')
        ->and(StrategyNextStep::for('gia_to_spouse')['destination']['screen'])->toBe('investment')
        ->and(StrategyNextStep::for('marriage_allowance_transfer')['destination']['screen'])->toBe('income')
        ->and(StrategyNextStep::for('not_a_strategy'))->toBeNull();
});
