<?php

declare(strict_types=1);

use App\Services\Onboarding\OnboardingChatDirector;

/**
 * Walk R23 (2026-10-09, Sam): the recap listed "Pay £1,100 more into your
 * pension and save £220 in tax" and "Open a Lifetime ISA for a £1,000
 * government bonus every year", then said "Together these are worth roughly
 * £220 a year". The total is tax saved; the bonus is not, so "together"
 * misstated the list.
 */
function planRecapTotalLine(array $items, float $total): ?string
{
    $director = app(OnboardingChatDirector::class);
    $ref = new ReflectionMethod($director, 'planTotalLine');
    $ref->setAccessible(true);

    return $ref->invoke($director, $items, $total);
}

it('does not call the tax saved the worth of a list with an item that saves no tax', function () {
    $line = planRecapTotalLine([
        ['title' => 'Pay £1,100 more into your pension and save £220 in tax', 'estimated_annual_tax_saved' => 220],
        ['title' => 'Open a Lifetime ISA for a £1,000 government bonus every year', 'estimated_annual_tax_saved' => 0],
    ], 220.0);

    expect($line)->toBe('The tax saved comes to roughly £220 a year.');
});

it('keeps "together" when every item saves tax', function () {
    $line = planRecapTotalLine([
        ['title' => 'Pay £21,500 more into your pension and save £8,600 in tax', 'estimated_annual_tax_saved' => 8600],
        ['title' => 'Switch your pension to salary sacrifice', 'estimated_annual_tax_saved' => 80],
    ], 8680.0);

    expect($line)->toBe('Together these are worth roughly £8,680 a year.');
});

it('says nothing when the plan saves no tax', function () {
    expect(planRecapTotalLine([
        ['title' => 'Open a Lifetime ISA for a £1,000 government bonus every year', 'estimated_annual_tax_saved' => 0],
    ], 0.0))->toBeNull();
});
