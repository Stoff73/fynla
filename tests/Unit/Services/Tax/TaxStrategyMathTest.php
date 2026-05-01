<?php

declare(strict_types=1);

use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Tax\TaxStrategyMath;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\TaxConfigurationSeeder::class);
    $this->math = app(TaxStrategyMath::class);
});

/**
 * Regression for P0.6 — estimateIsaSubscriptionsThisYear must only count ISAs
 * opened in the current tax year. Pre-fix, the helper summed every ISA
 * balance the user held, so a £25k legacy-ISA holder appeared "fully
 * subscribed" against the £20k annual cap and missed legitimate top-up,
 * Lifetime ISA, and Bed-and-ISA suggestions. Captures the £75k smoke-test
 * defect surfaced on 2026-04-30.
 */
describe('estimateIsaSubscriptionsThisYear', function () {
    it('counts ISAs opened in the current tax year', function () {
        $user = User::factory()->create();
        SavingsAccount::factory()->for($user)->create([
            'is_isa' => true,
            'current_balance' => 5000,
            'created_at' => now(), // current tax year
        ]);

        expect($this->math->estimateIsaSubscriptionsThisYear($user))->toBe(5000.0);
    });

    it('excludes ISAs opened before the current tax year', function () {
        $user = User::factory()->create();
        SavingsAccount::factory()->for($user)->create([
            'is_isa' => true,
            'current_balance' => 25000,
            'created_at' => now()->subYears(2), // legacy ISA
        ]);

        expect($this->math->estimateIsaSubscriptionsThisYear($user))->toBe(0.0);
    });

    it('counts only the current-year subset when the user has a mix', function () {
        $user = User::factory()->create();
        SavingsAccount::factory()->for($user)->create([
            'is_isa' => true,
            'current_balance' => 25000,
            'created_at' => now()->subYears(2), // legacy
        ]);
        SavingsAccount::factory()->for($user)->create([
            'is_isa' => true,
            'current_balance' => 8000,
            'created_at' => now(), // current
        ]);
        // Non-ISA must never be counted regardless of created_at
        SavingsAccount::factory()->for($user)->create([
            'is_isa' => false,
            'current_balance' => 50000,
            'created_at' => now(),
        ]);

        expect($this->math->estimateIsaSubscriptionsThisYear($user))->toBe(8000.0);
    });
});
