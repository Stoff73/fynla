<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Onboarding\OnboardingService;
use App\Support\SharedExpenditure;

// The separate-entry branch used to hand-write a shorter column list than the
// joint branch and silently drop five categories. Both now derive from
// SharedExpenditure::SHARED_FIELDS, so a figure entered in either mode lands.
it('stores every expenditure category from a separate-mode onboarding payload', function () {
    $user = User::factory()->create(['life_stage' => 'accumulation']);

    app(OnboardingService::class)->saveStepProgress($user->id, 'expenditure', [
        'userData' => ['school_lunches' => 80, 'gifts_charity' => 25, 'regular_savings' => 300, 'charitable_donations' => 40],
        'spouseData' => [],
    ]);

    $user->refresh();
    expect((float) $user->school_lunches)->toBe(80.0)
        ->and((float) $user->gifts_charity)->toBe(25.0)
        ->and((float) $user->regular_savings)->toBe(300.0)
        ->and((float) $user->charitable_donations)->toBe(40.0);
});

it('halves the shared categories but not charitable donations for a linked household', function () {
    $spouse = User::factory()->create(['life_stage' => 'accumulation']);
    $user = User::factory()->create(['life_stage' => 'accumulation', 'spouse_id' => $spouse->id]);
    $spouse->update(['spouse_id' => $user->id]);

    app(OnboardingService::class)->saveStepProgress($user->id, 'expenditure', [
        'food_groceries' => 600,
        'charitable_donations' => 40,
    ]);

    $user->refresh();
    expect((float) $user->food_groceries)->toBe(600 * SharedExpenditure::JOINT_SHARE)
        ->and((float) $user->charitable_donations)->toBe(40.0);
});

// The wizard prefills from the user's columns when no step progress exists.
// A Save Tax user has childcare and donations but no total, and the old
// "any exists" test (total or two categories) returned nothing for them.
it('returns the stored tax categories as step data when only those are set', function () {
    $user = User::factory()->create(['life_stage' => 'accumulation', 'childcare' => 600, 'charitable_donations' => 40, 'is_gift_aid' => true, 'monthly_expenditure' => null]);

    $data = app(OnboardingService::class)->getStepData($user->id, 'expenditure');

    expect($data)->not->toBeNull()
        ->and($data['childcare'])->toBe(600.0)
        ->and($data['charitable_donations'])->toBe(40.0)
        ->and($data['is_gift_aid'])->toBeTrue()
        ->and(app(OnboardingService::class)->getStepData(User::factory()->create(['life_stage' => 'accumulation'])->id, 'expenditure'))->toBeNull();
});
