<?php

declare(strict_types=1);

use App\Services\Actions\ActionHowTo;

/*
 * The branch grammar (CSJ 2026-09-28): the user's records pick the steps, their
 * figures fill them, and a step with no figure is left out rather than shown blank.
 */
$steps = [
    ['when' => 'has_salary_sacrifice', 'text' => 'Ask payroll to sacrifice {contribution} more.'],
    ['when' => 'has_personal_pension and not has_workplace_pension', 'text' => 'Pay {net_payment} into {personal_pension}.'],
    ['when' => 'above_basic and not has_salary_sacrifice', 'text' => 'Claim the other {extra_relief}.'],
    ['when' => 'band is higher or additional', 'text' => 'You pay tax at the {band_rate} rate.'],
    ['when' => null, 'text' => 'Pay it in by {tax_year_end}.'],
];

it('shows a personal pension payer their own route and figures', function () use ($steps) {
    $out = ActionHowTo::render(
        $steps,
        ['has_personal_pension' => true, 'has_workplace_pension' => false, 'has_salary_sacrifice' => false, 'above_basic' => true, 'band' => 'higher'],
        ['net_payment' => '£3,440', 'personal_pension' => 'Vanguard SIPP', 'extra_relief' => '£860', 'tax_year_end' => '5 April 2027'],
    );

    // The band step names {band_rate}, which has no value here, so it is left out.
    expect($out)->toBe([
        'Pay £3,440 into Vanguard SIPP.',
        'Claim the other £860.',
        'Pay it in by 5 April 2027.',
    ]);
});

it('shows a salary sacrifice member only the payroll route', function () use ($steps) {
    $out = ActionHowTo::render(
        $steps,
        ['has_salary_sacrifice' => true, 'has_workplace_pension' => true, 'has_personal_pension' => false, 'above_basic' => true, 'band' => 'basic'],
        ['contribution' => '£4,300', 'tax_year_end' => '5 April 2027'],
    );

    expect($out)->toBe(['Ask payroll to sacrifice £4,300 more.', 'Pay it in by 5 April 2027.']);
});

it('treats a stored plain string as a step everyone sees', function () {
    expect(ActionHowTo::render(['Decide how much.'], [], []))->toBe(['Decide how much.']);
});

it('reads zero as false and a positive figure as true', function () {
    expect(ActionHowTo::holds('employer_ni_rebate_pct', ['employer_ni_rebate_pct' => 0.0]))->toBeFalse()
        ->and(ActionHowTo::holds('not employer_ni_rebate_pct', ['employer_ni_rebate_pct' => 0.0]))->toBeTrue()
        ->and(ActionHowTo::holds('employer_ni_rebate_pct', ['employer_ni_rebate_pct' => 0.5]))->toBeTrue()
        ->and(ActionHowTo::holds('transfer_direction is to_user', ['transfer_direction' => 'to_spouse']))->toBeFalse()
        ->and(ActionHowTo::holds('children_under_18 is 1', ['children_under_18' => 1.0]))->toBeTrue()
        ->and(ActionHowTo::holds('children_under_18 is not 1', ['children_under_18' => 1.0]))->toBeFalse()
        ->and(ActionHowTo::holds('children_under_18 is not 1', ['children_under_18' => 3.0]))->toBeTrue();
});
