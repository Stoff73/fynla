<?php

declare(strict_types=1);

use App\Models\SavingsAccount;

it('savings:backfill-derived populates derived columns on legacy rows', function () {
    // Factory writes interest_rate in the DECIMAL convention; set it explicitly
    // so the normalised projection is deterministic. 0.03 <= 1 => no /100 =>
    // 5000 * 0.03 = 150.00; balance_gbp == current_balance == 5000.00.
    SavingsAccount::factory()->create([
        'current_balance' => 5000,
        'interest_rate' => 0.03,
        'is_isa' => false,
    ]);

    $this->artisan('savings:backfill-derived')->assertSuccessful();

    $account = SavingsAccount::first();
    expect((float) $account->balance_gbp)->toBe(5000.00);
    expect((float) $account->annual_interest_projected_gbp)->toBe(150.00);
    expect($account->balance_gbp_calculated_at)->not->toBeNull();
});
