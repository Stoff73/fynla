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

it('renders the outcome lines on their own, and never among the steps', function () {
    $steps = [
        ['when' => null, 'text' => 'Pay it in.'],
        ['when' => null, 'text' => 'Your Income Tax falls from {tax_now} to {tax_after}.', 'part' => 'outcome'],
    ];
    $text = ['tax_now' => '£17,432', 'tax_after' => '£7,552'];

    expect(ActionHowTo::render($steps, [], $text))->toBe(['Pay it in.'])
        ->and(ActionHowTo::render($steps, [], $text, 'outcome'))->toBe(['Your Income Tax falls from £17,432 to £7,552.']);
});

it('gives every key a heading names the same entry, each filled with its own figures', function () {
    $entries = ActionHowTo::parse("## rate_below_market, rate_poor\nstatus: approved\nwhy:\n1. {account_name} pays {account_rate}%.\nalways:\n1. Compare easy access rates.\n## other\n1. Separate.\n");

    expect(array_keys($entries))->toBe(['rate_below_market', 'rate_poor', 'other'])
        ->and($entries['rate_below_market'])->toBe($entries['rate_poor'])
        ->and($entries['rate_poor']['status'])->toBe('approved')
        ->and($entries['other']['status'])->toBe('draft')
        ->and(ActionHowTo::render($entries['rate_poor']['steps'], [], ['account_name' => 'Marcus', 'account_rate' => '1.10'], 'why'))
        ->toBe(['Marcus pays 1.10%.']);
});
