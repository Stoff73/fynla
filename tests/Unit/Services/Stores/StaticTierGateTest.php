<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Stores\StaticTierGate;

/**
 * StaticTierGate is the interim spec §13 tier-cap implementation. These tests
 * exercise it in isolation — no DB needed beyond an in-memory User instance.
 * Tier resolution is `$user->tier ?? 'free'`; Eloquent permits arbitrary
 * in-memory attributes even with no `tier` column, so we can simulate tier1+
 * by setting the attribute directly without persisting.
 */
beforeEach(function () {
    $this->gate = new StaticTierGate;
});

it('resolves a user with no tier attribute to the free tier (cap 3)', function () {
    $user = new User; // no `tier` attribute → resolves 'free'

    expect($this->gate->hardLimit($user, 'savings_account'))->toBe(3);
    expect($this->gate->softLimit($user, 'savings_account'))->toBe(3);
});

it('allows a free-tier user to create up to but not including the hard limit', function () {
    $user = new User;

    expect($this->gate->canCreate($user, 'savings_account', 0))->toBeTrue();
    expect($this->gate->canCreate($user, 'savings_account', 1))->toBeTrue();
    expect($this->gate->canCreate($user, 'savings_account', 2))->toBeTrue();
});

it('refuses a free-tier user once the count reaches or exceeds the hard limit', function () {
    $user = new User;

    expect($this->gate->canCreate($user, 'savings_account', 3))->toBeFalse();
    expect($this->gate->canCreate($user, 'savings_account', 4))->toBeFalse();
    expect($this->gate->canCreate($user, 'savings_account', 99))->toBeFalse();
});

it('treats tier1 as unlimited for savings accounts', function () {
    $user = new User;
    $user->tier = 'tier1';

    expect($this->gate->hardLimit($user, 'savings_account'))->toBeNull();
    expect($this->gate->softLimit($user, 'savings_account'))->toBeNull();
    expect($this->gate->canCreate($user, 'savings_account', 0))->toBeTrue();
    expect($this->gate->canCreate($user, 'savings_account', 3))->toBeTrue();
    expect($this->gate->canCreate($user, 'savings_account', 1000))->toBeTrue();
});

it('treats tier2 as unlimited for savings accounts', function () {
    $user = new User;
    $user->tier = 'tier2';

    expect($this->gate->hardLimit($user, 'savings_account'))->toBeNull();
    expect($this->gate->canCreate($user, 'savings_account', 500))->toBeTrue();
});

it('treats tier3 as unlimited for savings accounts', function () {
    $user = new User;
    $user->tier = 'tier3';

    expect($this->gate->hardLimit($user, 'savings_account'))->toBeNull();
    expect($this->gate->canCreate($user, 'savings_account', 500))->toBeTrue();
});

it('treats an unknown entity key as unlimited regardless of tier', function () {
    $freeUser = new User;
    $tier1User = new User;
    $tier1User->tier = 'tier1';

    expect($this->gate->hardLimit($freeUser, 'investment_account'))->toBeNull();
    expect($this->gate->softLimit($freeUser, 'investment_account'))->toBeNull();
    expect($this->gate->canCreate($freeUser, 'investment_account', 0))->toBeTrue();
    expect($this->gate->canCreate($freeUser, 'investment_account', 9999))->toBeTrue();
    expect($this->gate->canCreate($tier1User, 'pension', 9999))->toBeTrue();
});

it('treats an unknown tier as unlimited (falls through the limits map)', function () {
    $user = new User;
    $user->tier = 'enterprise'; // not in the LIMITS map

    expect($this->gate->hardLimit($user, 'savings_account'))->toBeNull();
    expect($this->gate->canCreate($user, 'savings_account', 9999))->toBeTrue();
});
