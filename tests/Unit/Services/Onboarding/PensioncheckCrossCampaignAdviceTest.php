<?php

declare(strict_types=1);

use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Onboarding\OnboardingChatDirector;
use Database\Seeders\TaxActionDefinitionSeeder;
use Database\Seeders\TaxConfigurationSeeder;

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TaxActionDefinitionSeeder::class);
});

function crossCampaignAdvice(User $user, string $section): ?string
{
    $director = app(OnboardingChatDirector::class);
    $ref = new ReflectionMethod($director, 'buildSectionAdvice');
    $ref->setAccessible(true);

    return $ref->invoke($director, $user, $section);
}

// ── Pensioncheck: non-retirement sections must be silent ──────────────────────

it('pensioncheck spouse section returns null — no cross-campaign tax advice', function () {
    // A high-income married pensioncheck user who WOULD get savetax spouse advice
    // if the tax builders fired. The pension walk has no spouse tax-strategy
    // section — the advice turn must emit nothing so no ISA/PA strings appear
    // in the pension flow and the synthesis does not reference tax strategies.
    $user = User::factory()->create([
        'marital_status' => 'married',
        'annual_employment_income' => 110000,
        'household_calculation_mode' => 'single_earner_couple',
        'onboarding_fyn_selection' => 'pensioncheck',
    ]);
    SavingsAccount::factory()->create([
        'user_id' => $user->id, 'is_isa' => false,
        'current_balance' => 50000, 'interest_rate' => 3.5,
    ]);

    $text = crossCampaignAdvice($user->fresh(), 'spouse');

    expect($text)->toBeNull();
});

it('pensioncheck income section returns null — no cross-campaign tax advice', function () {
    // A 60%-trap earner who WOULD get pa_taper_rescue tax advice in the savetax
    // walk. In the pension walk the income section must stay silent.
    $user = User::factory()->create([
        'marital_status' => 'single',
        'household_calculation_mode' => 'single',
        'date_of_birth' => '1980-01-01',
        'annual_employment_income' => 120000,
        'onboarding_fyn_selection' => 'pensioncheck',
    ]);

    $text = crossCampaignAdvice($user->fresh(), 'income');

    expect($text)->toBeNull();
});

it('pensioncheck savings section returns null — no cross-campaign tax advice', function () {
    $user = User::factory()->create([
        'annual_employment_income' => 60000,
        'onboarding_fyn_selection' => 'pensioncheck',
    ]);
    SavingsAccount::factory()->create([
        'user_id' => $user->id, 'is_isa' => false,
        'current_balance' => 30000, 'interest_rate' => 4.0,
    ]);

    expect(crossCampaignAdvice($user->fresh(), 'savings'))->toBeNull();
});

it('pensioncheck investments section returns null — no cross-campaign tax advice', function () {
    $user = User::factory()->create([
        'annual_employment_income' => 60000,
        'onboarding_fyn_selection' => 'pensioncheck',
    ]);

    expect(crossCampaignAdvice($user->fresh(), 'investments'))->toBeNull();
});

it('pensioncheck giving section returns null — no cross-campaign tax advice', function () {
    $user = User::factory()->create([
        'annual_employment_income' => 60000,
        'onboarding_fyn_selection' => 'pensioncheck',
    ]);

    expect(crossCampaignAdvice($user->fresh(), 'giving'))->toBeNull();
});

// ── Savetax: spouse advice unchanged ─────────────────────────────────────────

it('savetax spouse section still voices tax advice for a single-earner couple', function () {
    // Characterisation guard — savetax spouse behaviour must be byte-identical
    // after the pensioncheck guard is added.
    $user = User::factory()->create([
        'marital_status' => 'married',
        'annual_employment_income' => 110000,
        'household_calculation_mode' => 'single_earner_couple',
        'onboarding_fyn_selection' => 'savetax',
    ]);
    SavingsAccount::factory()->create([
        'user_id' => $user->id, 'is_isa' => false,
        'current_balance' => 50000, 'interest_rate' => 3.5,
    ]);

    $text = crossCampaignAdvice($user->fresh(), 'spouse');

    // The spouse section must voice SOMETHING (savetax logic unchanged).
    expect($text)->not->toBeNull()
        ->and($text)->toContain("spouse's allowances");
});

it('savetax income section still voices tax advice for a 60%-trap earner', function () {
    $user = User::factory()->create([
        'marital_status' => 'single',
        'household_calculation_mode' => 'single',
        'date_of_birth' => '1980-01-01',
        'annual_employment_income' => 120000,
        'onboarding_fyn_selection' => 'savetax',
    ]);

    $text = crossCampaignAdvice($user->fresh(), 'income');

    expect($text)->not->toBeNull()
        ->and($text)->toContain('Personal Allowance');
});
