<?php

declare(strict_types=1);

use App\Support\HoldingValuation;

/**
 * W-0039 — the decision: units are the fact, value is derived from them.
 *
 * `current_value` and `quantity` can never disagree, because only one of them is
 * ever authoritative at a time and the server owns which.
 */
it('derives the value from units x price when both are given', function () {
    // 351 Fundsmith at £7.42 — the persona's first holding, which had no way in.
    $result = HoldingValuation::reconcile([
        'quantity' => 351,
        'current_price' => 7.42,
    ]);

    expect($result['current_value'])->toBe(2604.42);
});

it('lets units override a value the caller also sent', function () {
    // The form still sends an allocation-derived current_value. Units win, so the
    // two cannot diverge in the stored row.
    $result = HoldingValuation::reconcile([
        'quantity' => 351,
        'current_price' => 7.42,
        'current_value' => 9999.99,
    ]);

    expect($result['current_value'])->toBe(2604.42);
});

it('still back-calculates units from value and price when no units are given', function () {
    // The legacy direction, kept as a fallback so every holding that works today
    // keeps working.
    $result = HoldingValuation::reconcile([
        'current_value' => 2604.42,
        'current_price' => 7.42,
    ]);

    expect($result['quantity'])->toBe(351.0)
        ->and($result)->not->toHaveKey('cost_basis');
});

it('derives cost basis from units and the purchase price', function () {
    $result = HoldingValuation::reconcile([
        'quantity' => 333,
        'current_price' => 255.00,
        'purchase_price' => 225.00,
    ]);

    expect($result['cost_basis'])->toBe(74925.00)
        ->and($result['current_value'])->toBe(84915.00);
});

it('leaves a payload alone when there is nothing to derive from', function () {
    $result = HoldingValuation::reconcile(['security_name' => 'Fundsmith Equity']);

    expect($result)->toBe(['security_name' => 'Fundsmith Equity']);
});

it('resolves a partial update against the stored holding', function () {
    // Editing only the price must revalue against the units already on record,
    // not drop them.
    $stored = (object) [
        'quantity' => 351.0,
        'current_price' => 7.42,
        'purchase_price' => 6.00,
        'current_value' => 2604.42,
    ];

    $result = HoldingValuation::reconcile(['current_price' => 8.00], $stored);

    expect($result['current_value'])->toBe(2808.00)
        ->and($result['cost_basis'])->toBe(2106.00);
});

it('honours units sent as an explicit null on an update', function () {
    $stored = (object) ['quantity' => 351.0, 'current_price' => 7.42, 'current_value' => 2604.42];

    // Clearing the units falls back to back-calculating them from the value.
    $result = HoldingValuation::reconcile(['quantity' => null, 'current_value' => 2604.42], $stored);

    expect($result['quantity'])->toBe(351.0);
});

it('does not divide by a zero or missing price', function () {
    expect(HoldingValuation::reconcile(['current_value' => 1000, 'current_price' => 0]))
        ->not->toHaveKey('quantity');

    expect(HoldingValuation::reconcile(['current_value' => 1000]))
        ->not->toHaveKey('quantity');
});

it('keeps units even when no price is available to value them', function () {
    // 50,000 units of L&G UK Property with no price is still a fact worth storing.
    $result = HoldingValuation::reconcile(['quantity' => 50000, 'current_value' => 12500]);

    expect($result['quantity'])->toBe(50000)
        ->and($result['current_value'])->toBe(12500);
});

it('never overwrites a typed value with the stored unit count', function () {
    // W-0121, the exact regression: an update that typed £450 and £45,000 was
    // revalued against the 19.955704 units already on record — 19.955704 x 450
    // — so a figure the user typed, had validated and was 200'd on came back as
    // £8,980.07. Supplied beats inherited: the typed value stands and the units
    // follow from it.
    $stored = (object) [
        'quantity' => 19.955704,
        'current_price' => 400.0,
        'current_value' => 40000.0,
        'purchase_price' => 350.523,
    ];

    $result = HoldingValuation::reconcile([
        'current_price' => 450,
        'current_value' => 45000,
    ], $stored);

    expect($result['current_value'])->toBe(45000)
        ->and($result['quantity'])->toBe(100.0)
        ->and($result['cost_basis'])->toBe(35052.30);
});

it('lets units win over a value supplied in the same payload on an update too', function () {
    // The deliberate half of the rule: sending both in one payload is the caller
    // choosing which field is authoritative, and units are the fact. Only an
    // INHERITED unit count is powerless against a typed value.
    $stored = (object) ['quantity' => 100.0, 'current_price' => 450.0, 'current_value' => 45000.0];

    $result = HoldingValuation::reconcile([
        'quantity' => 120,
        'current_value' => 45000,
    ], $stored);

    expect($result['current_value'])->toBe(54000.0);
});

it('keeps a typed value untouched when no price can relate it to the stored units', function () {
    // Nothing can reconcile units and a value without a price, so the typed
    // value is stored as given rather than silently revalued.
    $stored = (object) ['quantity' => 351.0, 'current_price' => null, 'current_value' => 2604.42];

    $result = HoldingValuation::reconcile(['current_value' => 3000], $stored);

    expect($result['current_value'])->toBe(3000)
        ->and($result)->not->toHaveKey('quantity');
});
