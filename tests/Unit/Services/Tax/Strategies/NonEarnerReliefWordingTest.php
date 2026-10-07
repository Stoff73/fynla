<?php

declare(strict_types=1);

use App\Models\TaxStrategyHouseholdInput;
use App\Models\User;
use App\Services\Investment\Recommendation\SpouseOptimisationService;
use App\Services\Tax\TaxStrategyCalculator;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * CSJ 2026-09-29 (#975): a non-earner's £720 is money HMRC adds through the
 * pension provider (FA 2004 s192), never "free money" or "tax saved". The
 * user's own card was reworded; fynla.org 2026-10-07 still told a couple
 * "Top up your spouse's pension by £2,880 — instant £720 of free money".
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(TaxConfigurationSeeder::class);
});

it('words the non-earning partner top-up as money HMRC adds', function (): void {
    $user = User::factory()->create([
        'is_preview_user' => false, 'marital_status' => 'married', 'employment_status' => 'full_time',
        'annual_employment_income' => 45000, 'household_calculation_mode' => 'single_earner_couple',
        'expenditure_entry_mode' => 'simple', 'monthly_expenditure' => 2000,
    ]);
    TaxStrategyHouseholdInput::create(['user_id' => $user->id]);

    $rec = collect(app(TaxStrategyCalculator::class)->calculate($user->fresh())->recommendations)->firstWhere('type', 'non_earner_spouse_pension');

    expect($rec['title'])->toBe("Pay £2,880 into your spouse's personal pension and HMRC adds £720")
        ->and($rec['description'])->toContain('HMRC adds £720 through their pension provider, making £3,600')
        ->and($rec['title'].$rec['description'])->not->toContain('free money')->not->toContain('free uplift');
});

it('words the investment plan partner top-up the same way, from tax config', function (): void {
    $method = new ReflectionMethod(SpouseOptimisationService::class, 'strategyNonEarningPension');
    $result = $method->invoke(
        app(SpouseOptimisationService::class),
        ['financial' => ['gross_income' => 45000.0, 'employment_status' => 'full_time']],
        ['gross_income' => 0.0, 'employment_status' => 'unemployed', 'name' => 'Sam'],
    );

    expect($result['explanation'])->toContain('Pay in £2,880 and HMRC adds £720 through the pension provider, making £3,600')
        ->and(json_encode($result))->not->toContain('free money');
});
