<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\UserProfile\UserProfileService;
use Database\Seeders\RolesPermissionsSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * W-0424 — a contribution recorded as a percentage reaches the spending side.
 *
 * Two mechanisms answered "what does this person pay into their pension each
 * month", and neither reached the other's records. The tax side read
 * `employee_contribution_percent × annual_salary`; `getFinancialCommitments()`
 * read `monthly_contribution_amount` and gated on it being above zero.
 *
 * So a member recording **8% of £145,000 with a null monthly amount** was counted
 * by neither. £11,600 a year left their pay and nothing in the application
 * deducted it from what they had available to spend.
 *
 * Measured on the real profile rather than the helper, because the defect was
 * that the figure never ARRIVED anywhere a user could see it.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TierConfigurationSeeder::class);
    $this->seed(RolesPermissionsSeeder::class);
    $this->service = app(UserProfileService::class);
});

it('counts a percentage-only workplace contribution as money leaving the pay packet', function () {
    $user = User::factory()->create([
        'tier' => 'premium',
        'annual_employment_income' => 145_000,
        'monthly_expenditure' => 1_000,
    ]);

    $user->dcPensions()->create([
        'scheme_name' => 'Workplace Pension',
        'annual_salary' => 145_000,
        'employee_contribution_percent' => 8,
        'monthly_contribution_amount' => null,
        'current_fund_value' => 180_000,
    ]);

    $income = $this->service->getCompleteProfile($user->fresh())['income_occupation'];

    // 145,000 × 8% = 11,600.
    expect((float) $income['annual_pension_contributions'])->toBe(11_600.0);
});

it('does not count a SIPP, which is funded from money already received', function () {
    $user = User::factory()->create(['tier' => 'premium', 'annual_employment_income' => 145_000]);

    $user->dcPensions()->create([
        'scheme_name' => 'Personal SIPP',
        'scheme_type' => 'sipp',
        'annual_salary' => 145_000,
        'employee_contribution_percent' => 8,
        'current_fund_value' => 90_000,
    ]);

    expect((float) $this->service->getCompleteProfile($user->fresh())['income_occupation']['annual_pension_contributions'])
        ->toBe(0.0);
});

it('prefers a stated monthly amount over the percentage', function () {
    // The explicit figure is what the member actually told us; the percentage is
    // the fallback, not the other way round.
    $user = User::factory()->create(['tier' => 'premium', 'annual_employment_income' => 145_000]);

    $user->dcPensions()->create([
        'scheme_name' => 'Workplace Pension',
        'scheme_type' => 'workplace',
        'annual_salary' => 145_000,
        'employee_contribution_percent' => 8,
        'monthly_contribution_amount' => 500,
        'current_fund_value' => 180_000,
    ]);

    // 500 × 12 = 6,000, not 11,600.
    expect((float) $this->service->getCompleteProfile($user->fresh())['income_occupation']['annual_pension_contributions'])
        ->toBe(6_000.0);
});

it('is not defeated by a null scheme_type, which the live data carries', function () {
    // The old allowlist was ['workplace','occupational','auto_enrolment'] against
    // an enum of ('workplace','sipp','personal') — two values that could never
    // match, and NULL excluded. This is the shape that returned £0 on the persona.
    $user = User::factory()->create(['tier' => 'premium', 'annual_employment_income' => 145_000]);

    $user->dcPensions()->create([
        'scheme_name' => 'Employer Scheme',
        'scheme_type' => null,
        'annual_salary' => 145_000,
        'employee_contribution_percent' => 8,
        'current_fund_value' => 180_000,
    ]);

    expect((float) $this->service->getCompleteProfile($user->fresh())['income_occupation']['annual_pension_contributions'])
        ->toBe(11_600.0);
});
