<?php

declare(strict_types=1);

use App\Models\SavingsAccount;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Laravel\Sanctum\Sanctum;

/*
 * Found walking csjones (CSJ 2026-10-01, account 460): a user whose savings
 * analysis is blocked by its readiness gate (no income recorded) got no
 * `position`, so /m showed "Target (0 months) £0" and an ISA allowance of £0.
 * The savings screens' figures need no income; they are always sent.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
});

it('sends the savings figures to a user the full analysis is blocked for', function () {
    $user = User::factory()->create(['is_preview_user' => false, 'employment_status' => 'retired', 'monthly_expenditure' => 2000]);
    SavingsAccount::factory()->create(['user_id' => $user->id, 'ownership_type' => 'individual', 'ownership_percentage' => 100, 'current_balance' => 12000, 'is_isa' => false]);
    Sanctum::actingAs($user);

    $data = $this->getJson('/api/savings')->assertOk()->json('data');

    expect($data['position'])->not->toBeNull()
        ->and((float) $data['position']['total_cash'])->toBe(12000.0)
        ->and($data['position']['emergency_fund']['target_months'])->toBeGreaterThan(0)
        ->and((float) $data['position']['isa']['total_allowance'])->toBeGreaterThan(0.0);
});
