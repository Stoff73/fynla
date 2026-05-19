<?php

declare(strict_types=1);

use App\Models\Investment\Holding;
use App\Models\Investment\InvestmentAccount;
use App\Models\User;
use App\Services\Investment\AssetLocation\TaxDragCalculator;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

/**
 * Wave 2.6 — REVIEW §4 High #21.
 *
 * Two regressions guarded:
 *   1. estimateInterestRate() reads from TaxConfigService
 *      `investment.asset_class_yields` (was hardcoded 4.5% with a
 *      "2024/25 rates" stale comment that drifted from the canonical
 *      seeder value of 4.0%).
 *   2. calculatePortfolioTaxDrag() uses the `forUserOrJoint` scope so
 *      joint-owned investment accounts are included in the user's
 *      total (was `where('user_id', ?)` only).
 */

it('estimateInterestRate reads cash yield from TaxConfigService', function () {
    $service = app(TaxDragCalculator::class);

    $cashHolding = new Holding;
    $cashHolding->asset_type = 'cash';
    $cashHolding->current_value = 10000;

    // Use Reflection to call the private method.
    $ref = new ReflectionClass(TaxDragCalculator::class);
    $method = $ref->getMethod('estimateInterestRate');
    $rate = $method->invoke($service, $cashHolding);

    // The TaxConfigurationSeeder seeds investment.asset_class_yields.cash.income_yield = 0.04
    // for 2025/26. Pre-fix hardcoded value was 0.045; post-fix should match the seed.
    expect($rate)->toBe(0.04);
});

it('estimateInterestRate falls back to 0.04 when config is missing for the type', function () {
    $service = app(TaxDragCalculator::class);

    $bondHolding = new Holding;
    $bondHolding->asset_type = 'bond';

    $ref = new ReflectionClass(TaxDragCalculator::class);
    $method = $ref->getMethod('estimateInterestRate');
    $rate = $method->invoke($service, $bondHolding);

    expect($rate)->toBe(0.04);
});

it('calculatePortfolioTaxDrag includes joint-owned investment accounts via forUserOrJoint', function () {
    $primary = User::factory()->create();
    $jointPartner = User::factory()->create();

    // Account owned solely by the joint partner (primary should NOT see this).
    InvestmentAccount::factory()->create([
        'user_id' => $jointPartner->id,
        'joint_owner_id' => null,
        'ownership_type' => 'individual',
        'account_type' => 'general_investment',
    ]);

    // Account jointly owned: joint_partner is primary, primary user is joint_owner.
    InvestmentAccount::factory()->create([
        'user_id' => $jointPartner->id,
        'joint_owner_id' => $primary->id,
        'ownership_type' => 'joint',
        'ownership_percentage' => 50,
        'account_type' => 'general_investment',
    ]);

    // Account owned individually by the primary user.
    InvestmentAccount::factory()->create([
        'user_id' => $primary->id,
        'joint_owner_id' => null,
        'ownership_type' => 'individual',
        'account_type' => 'general_investment',
    ]);

    $result = app(TaxDragCalculator::class)->calculatePortfolioTaxDrag($primary->id, []);

    // Both the individual + the joint account where primary is joint_owner
    // should appear; the joint partner's solo account should not.
    expect($result['accounts'])->toHaveCount(2);
});
