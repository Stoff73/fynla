<?php

declare(strict_types=1);

use App\Models\TaxStrategyHouseholdInput;
use App\Models\User;
use App\Services\UserProfile\UserProfileService;

/**
 * The /m Income screen's spouse-verify view reads income_summary.spouse. A
 * SaveTax campaign user has no linked spouse account (linking is surfaced later
 * via the dashboard), so the spouse's income lives on the household input —
 * surface it so the verify screen shows the amount the user entered (CSJ).
 */
it('surfaces the campaign-captured spouse income when there is no linked spouse account', function () {
    $user = User::factory()->create(['marital_status' => 'married']);
    TaxStrategyHouseholdInput::create([
        'user_id' => $user->id,
        'household_calculation_mode' => 'dual_earner',
        'spouse_annual_income' => 48000,
    ]);

    $profile = app(UserProfileService::class)->getCompleteProfile($user->fresh());

    expect($profile['income_summary']['spouse'])->not->toBeNull()
        ->and($profile['income_summary']['spouse']['employment'])->toBe(48000.0)
        ->and($profile['income_summary']['spouse']['total'])->toBe(48000.0);
});

it('returns null spouse income when neither a linked spouse nor household income exists', function () {
    $user = User::factory()->create(['marital_status' => 'single']);

    $profile = app(UserProfileService::class)->getCompleteProfile($user);

    expect($profile['income_summary']['spouse'])->toBeNull();
});
