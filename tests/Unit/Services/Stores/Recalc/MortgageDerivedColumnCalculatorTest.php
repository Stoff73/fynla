<?php

declare(strict_types=1);

use App\Models\Mortgage;
use App\Models\Property;
use App\Models\User;
use App\Services\Stores\Recalc\MortgageDerivedColumnCalculator;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->property = Property::factory()->create(['user_id' => $this->user->id, 'current_value' => 500000]);
    $this->calculator = app(MortgageDerivedColumnCalculator::class);
});

it('recomputes outstanding_balance_gbp + LTV when outstanding_balance changes', function () {
    $mortgage = Mortgage::factory()->create([
        'user_id' => $this->user->id,
        'property_id' => $this->property->id,
        'outstanding_balance' => 250000,
        'monthly_payment' => 1500,
    ]);

    $changes = $this->calculator->recalculate($mortgage);

    expect($changes)->toHaveKey('outstanding_balance_gbp');
    expect($changes['outstanding_balance_gbp'])->toBe(250000.0);
    expect($changes)->toHaveKey('current_ltv_pct');
    expect($changes['current_ltv_pct'])->toBe(50.0);  // 250k / 500k * 100 = 50%
    expect($mortgage->fresh()->outstanding_balance_gbp_calculated_at)->not->toBeNull();
});

it('handles property.current_value=0 gracefully (LTV is not set)', function () {
    $this->property->update(['current_value' => 0]);
    $mortgage = Mortgage::factory()->create([
        'user_id' => $this->user->id,
        'property_id' => $this->property->id,
        'outstanding_balance' => 250000,
        'monthly_payment' => 1500,
    ]);

    $changes = $this->calculator->recalculate($mortgage);

    expect($changes)->not->toHaveKey('current_ltv_pct');
    expect($mortgage->fresh()->current_ltv_pct)->toBeNull();
});

it('skips fields that did not change', function () {
    $mortgage = Mortgage::factory()->create([
        'user_id' => $this->user->id,
        'property_id' => $this->property->id,
        'outstanding_balance' => 250000,
        'outstanding_balance_gbp' => 250000,
        'monthly_payment' => 1500,
        'monthly_payment_gbp' => 1500,
    ]);

    $changes = $this->calculator->recalculate($mortgage);

    expect($changes)->not->toHaveKey('outstanding_balance_gbp');
    expect($changes)->not->toHaveKey('monthly_payment_gbp');
});
