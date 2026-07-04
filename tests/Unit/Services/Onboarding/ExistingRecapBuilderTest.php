<?php

declare(strict_types=1);

use App\Models\DBPension;
use App\Models\DCPension;
use App\Models\User;
use App\Services\Onboarding\OnboardingStateMachine as SM;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Task C6 — unit tests for SM::buildExistingRecapPrompt.
 *
 * Validates the live-DB-driven point-form recap emitted at campaign2_existing_recap.
 * The builder must surface income, per-pension lines, and spouse status from real
 * column values — it must never fabricate or summarise.
 */

// ── Helpers ──────────────────────────────────────────────────────────────────

function recapUser(array $attrs = []): User
{
    return User::factory()->create(array_merge([
        'first_name' => 'Emma',
        'marital_status' => 'single',
        'annual_employment_income' => null,
        'annual_self_employment_income' => null,
        'onboarding_fyn_path' => 'campaign',
        'onboarding_fyn_selection' => 'pensioncheck',
    ], $attrs));
}

// ── Lead-in invariants ────────────────────────────────────────────────────────

it('opens with the canonical welcome-back lead-in', function (): void {
    $user = recapUser();
    $text = SM::buildExistingRecapPrompt('', $user);

    expect($text)->toStartWith("Welcome back, Emma. Let's take a proper look at your pension. Here's what I already have from you:");
});

it('closes with the bold confirmation question', function (): void {
    $user = recapUser();
    $text = SM::buildExistingRecapPrompt('', $user);

    expect($text)->toContain('**Is that all still right?**');
});

it('uses "there" when first_name is blank', function (): void {
    $user = recapUser(['first_name' => '']);
    $text = SM::buildExistingRecapPrompt('', $user);

    expect($text)->toStartWith('Welcome back, there.');
});

// ── Income line ───────────────────────────────────────────────────────────────

it('includes an employment income line when annual_employment_income is set', function (): void {
    $user = recapUser(['annual_employment_income' => 52000]);
    $text = SM::buildExistingRecapPrompt('', $user);

    expect($text)->toContain('£52,000');
    expect($text)->toContain('Annual income');
});

it('includes a self-employment income line when only annual_self_employment_income is set', function (): void {
    $user = recapUser([
        'annual_employment_income' => null,
        'annual_self_employment_income' => 38000,
    ]);
    $text = SM::buildExistingRecapPrompt('', $user);

    expect($text)->toContain('£38,000');
});

it('omits income bullet when both income fields are null or zero', function (): void {
    $user = recapUser([
        'annual_employment_income' => null,
        'annual_self_employment_income' => null,
    ]);
    $text = SM::buildExistingRecapPrompt('', $user);

    expect($text)->not->toContain('Annual income');
    expect($text)->not->toContain('self-employment income');
});

// ── DC pension lines ──────────────────────────────────────────────────────────

it('lists each DC pension by scheme name with pot value when known', function (): void {
    $user = recapUser(['annual_employment_income' => 60000]);

    DCPension::factory()->create([
        'user_id' => $user->id,
        'scheme_name' => 'Aviva Workplace Pension',
        'current_fund_value' => 34500,
        'employee_contribution_percent' => 5.00,
    ]);
    DCPension::factory()->create([
        'user_id' => $user->id,
        'scheme_name' => 'Old Employer Pension',
        'current_fund_value' => 12000,
        'employee_contribution_percent' => null,
    ]);

    $text = SM::buildExistingRecapPrompt('', $user);

    expect($text)->toContain('Aviva Workplace Pension');
    expect($text)->toContain('£34,500');
    expect($text)->toContain('Old Employer Pension');
    expect($text)->toContain('£12,000');
});

it('lists DC pension contribution percentage when known', function (): void {
    $user = recapUser(['annual_employment_income' => 45000]);

    DCPension::factory()->create([
        'user_id' => $user->id,
        'scheme_name' => 'NEST',
        'current_fund_value' => 0,
        'employee_contribution_percent' => 3.00,
    ]);

    $text = SM::buildExistingRecapPrompt('', $user);

    expect($text)->toContain('NEST');
    expect($text)->toContain('3%');
});

it('falls back to "Unnamed pension" for a DC pension with a blank scheme name', function (): void {
    $user = recapUser();
    DCPension::factory()->create([
        'user_id' => $user->id,
        'scheme_name' => '',
        'current_fund_value' => 5000,
    ]);

    $text = SM::buildExistingRecapPrompt('', $user);

    expect($text)->toContain('Unnamed pension');
});

// ── DB pension lines ──────────────────────────────────────────────────────────

it('includes a DB pension line with accrued annual pension when set', function (): void {
    $user = recapUser(['annual_employment_income' => 55000]);

    DBPension::factory()->create([
        'user_id' => $user->id,
        'scheme_name' => 'NHS Pension',
        'accrued_annual_pension' => 8000,
    ]);

    $text = SM::buildExistingRecapPrompt('', $user);

    expect($text)->toContain('NHS Pension');
    expect($text)->toContain('£8,000');
});

it('falls back to "Defined Benefit pension" when DB pension scheme_name is blank', function (): void {
    $user = recapUser();
    DBPension::factory()->create([
        'user_id' => $user->id,
        'scheme_name' => '',
        'accrued_annual_pension' => 4000,
    ]);

    $text = SM::buildExistingRecapPrompt('', $user);

    expect($text)->toContain('Defined Benefit pension');
});

// ── Spouse status line ────────────────────────────────────────────────────────

it('includes a married spouse line when marital_status is married and no linked spouse', function (): void {
    $user = recapUser(['marital_status' => 'married', 'spouse_id' => null]);
    $text = SM::buildExistingRecapPrompt('', $user);

    expect($text)->toContain('Married');
});

it('omits spouse line for a single user', function (): void {
    $user = recapUser(['marital_status' => 'single']);
    $text = SM::buildExistingRecapPrompt('', $user);

    expect($text)->not->toContain('Married');
    expect($text)->not->toContain('married');
});

// ── Full recap: user with 2 pensions + spouse ─────────────────────────────────

it('builds a complete recap for a user with two pensions and a spouse', function (): void {
    $user = recapUser([
        'first_name' => 'James',
        'annual_employment_income' => 75000,
        'marital_status' => 'married',
        'spouse_id' => null,
    ]);

    DCPension::factory()->create([
        'user_id' => $user->id,
        'scheme_name' => 'HSBC Pension',
        'current_fund_value' => 47000,
        'employee_contribution_percent' => 6.00,
    ]);
    DBPension::factory()->create([
        'user_id' => $user->id,
        'scheme_name' => 'Teachers Pension',
        'accrued_annual_pension' => 12000,
    ]);

    $text = SM::buildExistingRecapPrompt('', $user);

    // Lead-in
    expect($text)->toContain('Welcome back, James.');

    // Income line
    expect($text)->toContain('£75,000');

    // DC pension line
    expect($text)->toContain('HSBC Pension');
    expect($text)->toContain('£47,000');

    // DB pension line
    expect($text)->toContain('Teachers Pension');
    expect($text)->toContain('£12,000');

    // Spouse line
    expect($text)->toContain('Married');

    // Closing question
    expect($text)->toContain('**Is that all still right?**');
});

// ── Graceful minimal user ─────────────────────────────────────────────────────

it('produces a valid minimal recap when a user has no income or pensions yet', function (): void {
    $user = recapUser([
        'first_name' => 'Sam',
        'annual_employment_income' => null,
        'marital_status' => 'single',
    ]);

    $text = SM::buildExistingRecapPrompt('', $user);

    // Must still have lead-in and closing question.
    expect($text)->toContain('Welcome back, Sam.');
    expect($text)->toContain('**Is that all still right?**');

    // No income or pension bullets.
    expect($text)->not->toContain('Annual income');
    expect($text)->not->toContain('Aviva');
});
