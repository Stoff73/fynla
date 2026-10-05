<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\Property;
use App\Models\User;
use App\Services\UserProfile\UserProfileService;

/*
 * One monthly figure for spending entered by category (TODO item 7a, one
 * figure on every surface). The web Expenditure form's total counts every
 * category it shows, charitable donations included, and rent and utilities
 * only for someone without a main residence (a homeowner enters housing costs
 * against the property). The breakdown every reader uses (/m Expenditure, the
 * runway, the risk score) left out rent, utilities and charitable donations,
 * so /m showed £955 where the web and Fyn had saved £2,095.
 */
it('counts rent, utilities and charitable donations for someone without a main residence', function () {
    $user = User::factory()->create([
        'expenditure_entry_mode' => 'category',
        'rent' => 1100, 'utilities' => 150, 'food_groceries' => 500, 'transport_fuel' => 120,
        'subscriptions' => 35, 'childcare' => 300, 'charitable_donations' => 40,
    ]);

    expect(app(UserProfileService::class)->getExpenditureBreakdown($user)['monthly_manual'])->toBe(2245.0);
});

it('leaves out rent and utilities for a homeowner, as the web form does', function () {
    $user = User::factory()->create([
        'expenditure_entry_mode' => 'category',
        'rent' => 1100, 'utilities' => 150, 'food_groceries' => 500, 'charitable_donations' => 40,
    ]);
    Property::factory()->create(['user_id' => $user->id, 'property_type' => 'main_residence']);

    expect(app(UserProfileService::class)->getExpenditureBreakdown($user->fresh())['monthly_manual'])->toBe(540.0);
});

it('saves the same total through Fyn as the breakdown reads back', function () {
    $user = User::factory()->create(['expenditure_entry_mode' => 'category', 'is_admin' => true]);
    Property::factory()->create(['user_id' => $user->id, 'property_type' => 'main_residence']);

    $agent = app(CoordinatingAgent::class);
    $method = new ReflectionMethod($agent, 'handleSetExpenditure');
    $result = $method->invoke($agent, ['rent' => 900, 'food_groceries' => 400, 'charitable_donations' => 25], $user, false);
    $fresh = $user->fresh();

    expect($result['total_monthly'])->toBe(425.0)
        ->and((float) $fresh->monthly_expenditure)->toBe(425.0)
        ->and(app(UserProfileService::class)->getExpenditureBreakdown($fresh)['monthly_manual'])->toBe(425.0);
});

it('lists the rows that make up the total, labelled as the category form labels them', function () {
    $user = User::factory()->create([
        'expenditure_entry_mode' => 'category',
        'rent' => 1100, 'food_groceries' => 500, 'subscriptions' => 35, 'charitable_donations' => 40,
    ]);

    expect(app(UserProfileService::class)->categorySpendingRows($user))->toBe([
        ['key' => 'rent', 'label' => 'Rent', 'amount' => 1100.0],
        ['key' => 'food_groceries', 'label' => 'Food and groceries', 'amount' => 500.0],
        ['key' => 'subscriptions', 'label' => 'Subscriptions', 'amount' => 35.0],
        ['key' => 'charitable_donations', 'label' => 'Charitable donations', 'amount' => 40.0],
    ]);
});

it('lists only childcare and donations under a monthly total, never a stale breakdown', function () {
    $user = User::factory()->create([
        'expenditure_entry_mode' => 'simple', 'monthly_expenditure' => 2400,
        'food_groceries' => 500, 'childcare' => 300,
    ]);

    expect(app(UserProfileService::class)->categorySpendingRows($user))->toBe([
        ['key' => 'childcare', 'label' => 'Childcare', 'amount' => 300.0],
    ]);
});
