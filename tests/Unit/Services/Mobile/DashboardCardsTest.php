<?php

declare(strict_types=1);

use App\Services\Mobile\DashboardCards;

// The five dashboard cards are built once, here, and rendered as sent by web,
// /m and iOS (CSJ 2026-10-01: one figure, every surface).

beforeEach(function () {
    $this->cards = new DashboardCards;
    $this->netWorth = ['total' => 480000, 'breakdown' => ['total_assets' => 700000]];
});

it('fills the net worth ring with equity and prints the same number', function () {
    $card = $this->cards->build([], $this->netWorth)['net_worth'];

    expect($card)->toMatchArray(['value' => 480000.0, 'caption' => '£700,000 assets'])
        ->and($card['visual'])->toBe(['type' => 'donut', 'progress' => 69, 'number' => '69%', 'label' => 'Equity']);
});

it('reads the emergency fund target the server sent, not six typed in', function () {
    $card = $this->cards->build(['savings' => [
        'total_savings' => 12000, 'emergency_fund_months' => 4.5, 'emergency_fund_target_months' => 3,
    ]], $this->netWorth)['savings'];

    expect($card['caption'])->toBe('Emergency fund on track')
        ->and($card['visual'])->toBe(['type' => 'bar', 'progress' => 100, 'number' => '4.5', 'label' => '/ 3 months']);
});

it('shows a full or empty protection ring, never a partial one', function () {
    $cards = fn (float $cover) => $this->cards->build(['protection' => ['total_coverage' => $cover]], $this->netWorth)['protection'];

    expect($cards(250000)['visual']['progress'])->toBe(100)
        ->and($cards(250000)['caption'])->toBe('Cover in place')
        ->and($cards(0)['visual']['progress'])->toBe(0)
        ->and($cards(0)['caption'])->toBe('Add your cover');
});

it('gives a saver their progress against the target', function () {
    $card = $this->cards->build(['retirement' => [
        'headline_kind' => 'projected', 'card_value' => 150000, 'card_value_is_income' => false,
        'target_income' => 30000, 'progress_percent' => 79,
    ]], $this->netWorth)['retirement'];

    expect($card)->toMatchArray(['value' => 150000.0, 'value_is_income' => false, 'caption' => 'Towards your target'])
        ->and($card['visual'])->toMatchArray(['progress' => 79, 'number' => '79%', 'label' => 'of target']);
});

it('says "Target not set" rather than 0% when there is no target', function () {
    $card = $this->cards->build(['retirement' => [
        'headline_kind' => 'projected', 'card_value' => 150000, 'card_value_is_income' => false,
        'target_income' => 0, 'progress_percent' => null,
    ]], $this->netWorth)['retirement'];

    expect($card['caption'])->toBe('Your pension pot')
        ->and($card['visual'])->toMatchArray(['progress' => 0, 'number' => 'Target not set', 'label' => '']);
});

it('shows someone drawing the Retirement page\'s own income, not a saver projection', function () {
    $card = $this->cards->build(['retirement' => [
        'headline_kind' => 'drawing', 'card_value' => 26514, 'card_value_is_income' => true,
        'target_income' => 40000, 'progress_percent' => 18,
        'drawing_per_year' => 30000, 'drawing_lasts_to_age' => 76, 'drawing_lasts_label' => 'runs out by about age 76',
    ]], $this->netWorth)['retirement'];

    expect($card)->toMatchArray(['value' => 26514.0, 'value_is_income' => true, 'caption' => 'Your income this year'])
        ->and($card['visual'])->toMatchArray(['progress' => 0, 'number' => 'runs out by about age 76']);
});

it('says the pot lasts beyond the projection when it does not run out', function () {
    $card = $this->cards->build(['retirement' => [
        'headline_kind' => 'drawing', 'card_value' => 20000, 'card_value_is_income' => true,
        'drawing_per_year' => 5000, 'drawing_lasts_to_age' => null, 'drawing_lasts_label' => 'lasts beyond 95',
    ]], $this->netWorth)['retirement'];

    expect($card['visual']['number'])->toBe('lasts beyond 95');
});

it('fills the investment ring with its share of assets', function () {
    $card = $this->cards->build(['investment' => [
        'portfolio_value' => 70000, 'accounts_count' => 1, 'holdings_count' => 3,
    ]], $this->netWorth)['investment'];

    expect($card['caption'])->toBe('3 holdings')
        ->and($card['visual'])->toBe(['type' => 'donut', 'progress' => 10, 'number' => '1', 'label' => 'Account']);
});

it('prints the runway as the Savings page does: whole months from ten', function () {
    $card = $this->cards->build(['savings' => [
        'total_savings' => 74750, 'emergency_fund_months' => 14.4, 'emergency_fund_target_months' => 6,
    ]], $this->netWorth)['savings'];

    expect($card['visual']['number'])->toBe('14');
});
