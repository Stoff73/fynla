<?php

declare(strict_types=1);

use App\Models\Property;
use App\Models\User;
use App\Services\Stores\Recalc\PropertyDerivedColumnCalculator;

it('materialises current_value_gbp + equity_gbp + loan_to_value_pct', function () {
    $user = User::factory()->create();
    $property = Property::factory()->create([
        'user_id' => $user->id,
        'current_value' => 500000,
        'outstanding_mortgage' => 200000,
    ]);

    $derived = (new PropertyDerivedColumnCalculator)->calculate($property, $user);

    expect($derived['current_value_gbp'])->toBe(500000.00);
    expect($derived['equity_gbp'])->toBe(300000.00);
    expect($derived['loan_to_value_pct'])->toBe(40.00);
});

it('returns null current_value_gbp + equity_gbp when current_value is null', function () {
    $user = User::factory()->create();
    $property = Property::factory()->create([
        'user_id' => $user->id,
        'current_value' => null,
        'outstanding_mortgage' => 100000,
    ]);

    $derived = (new PropertyDerivedColumnCalculator)->calculate($property, $user);

    expect($derived['current_value_gbp'])->toBeNull();
    expect($derived['equity_gbp'])->toBeNull();
    expect($derived['loan_to_value_pct'])->toBeNull();
});

it('treats null outstanding_mortgage as zero (equity = current_value)', function () {
    $user = User::factory()->create();
    $property = Property::factory()->create([
        'user_id' => $user->id,
        'current_value' => 350000,
        'outstanding_mortgage' => null,
    ]);

    $derived = (new PropertyDerivedColumnCalculator)->calculate($property, $user);

    expect($derived['equity_gbp'])->toBe(350000.00);
    expect($derived['loan_to_value_pct'])->toBe(0.00);
});

it('handles current_value = 0 by setting loan_to_value_pct = null', function () {
    $user = User::factory()->create();
    $property = Property::factory()->create([
        'user_id' => $user->id,
        'current_value' => 0,
        'outstanding_mortgage' => 100000,
    ]);

    $derived = (new PropertyDerivedColumnCalculator)->calculate($property, $user);

    expect($derived['loan_to_value_pct'])->toBeNull();
});
