<?php

declare(strict_types=1);

use App\Services\Savings\SavingsPosition;

// What every savings screen shows, built once (CSJ 2026-10-01: one figure,
// every surface). Web, /m and iOS render this block as sent.

beforeEach(function () {
    $this->position = new SavingsPosition;
    $this->target = ['target_months' => 6, 'target_amount' => 18000.0, 'rationale' => 'The standard recommendation is 6 months of essential expenditure.'];
    $this->isa = ['total_allowance' => 20000, 'total_used' => 8000, 'remaining' => 12000, 'percentage_used' => 40];
});

it('states the runway from cash savings and how much of the target is covered', function () {
    $p = $this->position->build(9000, 3000, 3.0, $this->target, $this->isa);

    expect($p['total_cash'])->toBe(9000.0)
        ->and($p['emergency_fund'])->toMatchArray([
            'runway_label' => '3 months from cash savings',
            'runway_figure' => '3',
            'runway_hint' => null,
            'covered_percent' => 50,
            'covered_label' => '50% of target',
            'shortfall' => 9000.0,
            'status' => 'part',
        ]);
});

it('asks for spending rather than saying 0 months when none is recorded (W-0495)', function () {
    $fund = $this->position->build(40000, 0, null, ['target_months' => 6, 'target_amount' => 0.0, 'rationale' => ''], $this->isa)['emergency_fund'];

    expect($fund['runway_label'])->toBe('Add your monthly spending')
        ->and($fund['runway_figure'])->toBeNull()
        ->and($fund['runway_hint'])->toBe(SavingsPosition::RUNWAY_UNAVAILABLE_HINT)
        ->and($fund['covered_percent'])->toBeNull();
});

it('rounds to whole months from ten and caps the cover at the target', function () {
    $fund = $this->position->build(37000, 3000, 12.33, $this->target, $this->isa)['emergency_fund'];

    expect($fund['runway_label'])->toBe('12 months from cash savings')
        ->and($fund['covered_percent'])->toBe(100)
        ->and($fund['shortfall'])->toBe(0.0)
        ->and($fund['status'])->toBe('on_track');
});

it('reads the ISA allowance as the tracker sent it, Lifetime ISA included', function () {
    $isa = $this->position->build(0, 0, null, $this->target, [
        'total_allowance' => 20000, 'total_used' => 17000, 'remaining' => 3000, 'percentage_used' => 85,
    ])['isa'];

    expect($isa)->toMatchArray(['remaining' => 3000.0, 'percent_used' => 85.0, 'status' => 'nearly', 'remaining_label' => '£3,000 remaining']);

    $full = $this->position->build(0, 0, null, $this->target, ['total_allowance' => 20000, 'total_used' => 20000, 'remaining' => 0, 'percentage_used' => 100])['isa'];
    expect($full)->toMatchArray(['status' => 'full', 'remaining_label' => 'Fully used']);
});

it('totals the cash page groups at the user\'s share', function () {
    $accounts = [
        (object) ['account_type' => 'current_account', 'is_isa' => false, 'current_balance' => 1500],
        (object) ['account_type' => 'easy_access', 'is_isa' => false, 'current_balance' => 5000],
        (object) ['account_type' => 'cash_isa', 'is_isa' => true, 'current_balance' => 10000],
        (object) ['account_type' => 'premium_bonds', 'is_isa' => false, 'current_balance' => 2000],
    ];

    expect($this->position->build(18500, 0, null, $this->target, $this->isa, $accounts)['group_totals'])
        ->toBe(['current_accounts' => 1500.0, 'savings_accounts' => 5000.0, 'isas' => 10000.0, 'nsi' => 2000.0]);
});
