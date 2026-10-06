<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Tax\TaxStrategyMath;
use App\Services\TaxConfigService;
use Database\Seeders\TaxConfigurationSeeder;

/*
 * TCGA 1992 s1H: gains are taxed at the lower rate only within the basic rate
 * band the user's income leaves unused (tax review F6, item 8, 2026-10-06).
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

it('taxes a gain at the lower rate only within the unused basic rate band', function () {
    $user = User::factory()->create([
        'annual_employment_income' => 40000, 'annual_self_employment_income' => 0, 'annual_dividend_income' => 0,
        'annual_interest_income' => 0, 'annual_rental_income' => 0, 'annual_other_income' => 0,
    ]);
    $config = app(TaxConfigService::class);
    $cgt = $config->getCapitalGainsTax();
    $math = app(TaxStrategyMath::class);

    $pa = (float) $config->getIncomeTax()['personal_allowance'];
    $unused = ($math->bandThresholdsFor($user)['higher'] - $pa) - ($math->taxableIncomeFor($user) - $pa);
    expect($unused)->toBeGreaterThan(0.0)->toBeLessThan(20000.0);

    $result = $math->capitalGainsTaxOn($user, 20000.0);

    expect($result['unused_basic_band'])->toEqualWithDelta($unused, 0.01)
        ->and($result['tax'])->toEqualWithDelta($unused * $cgt['basic_rate'] + (20000 - $unused) * $cgt['higher_rate'], 0.01)
        ->and($result['marginal_rate'])->toBe((float) $cgt['higher_rate'])
        ->and($math->capitalGainsTaxOn($user, 1.0)['marginal_rate'])->toBe((float) $cgt['basic_rate']);
});
