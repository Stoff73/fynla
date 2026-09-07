<?php

declare(strict_types=1);

use App\Models\DCPension;
use App\Models\User;
use App\Services\Tax\IncomeDefinitionsService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * W-0204 — pay given up under salary sacrifice reaches threshold income.
 *
 * FA 2004 s228ZA(3) adds it back precisely so sacrifice cannot be used to duck the
 * tapered Annual Allowance. `IncomeDefinitionsService` never applied it: the runs were
 * **byte-identical with the flag set and unset**, so the branch did not exist at all —
 * the flag was written, validated and read elsewhere, and the arithmetic simply never
 * consulted it. A sacrificing earner was told their Annual Allowance was £60,000 where
 * the statute gives less, and an overstated allowance invites a contribution that
 * triggers an unexpected charge.
 *
 * **What blocked it, and how it is resolved.** Nothing recorded whether
 * `users.annual_employment_income` was the pre- or post-sacrifice figure — the field is
 * labelled "Employment Income" with no guidance — and assuming one moves a user's taper
 * position on a guess. CSJ decided on 2026-08-28 to ask:
 * `users.employment_income_basis`, put to a sacrificing user, `gross` assumed and
 * declared where unanswered.
 *
 * **The assumption changes net income, not threshold income.** The basis is applied
 * before any definition is struck, so the two readings converge on one threshold figure
 * and the taper decision never turns on the guess. That is what the last test here pins.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

/**
 * An earner at the given employment income with one workplace pension.
 *
 * Contributions are stated as percentages of the pension's own `annual_salary`, which is
 * what `getPensionContributions()` reads — deliberately set equal to the employment
 * income so the two are not silently different figures (tests/CLAUDE.md §4, Fixture).
 */
function sacrificeEarner(int $income, bool $sacrifices, ?string $basis = null): User
{
    $user = User::factory()->create([
        'annual_employment_income' => $income,
        'employment_income_basis' => $basis,
        'marital_status' => 'single',
    ]);

    DCPension::factory()->create([
        'user_id' => $user->id,
        'annual_salary' => $income,
        'employee_contribution_percent' => 8,
        'employer_contribution_percent' => 3,
        'monthly_contribution_amount' => 0,
        'salary_sacrifice' => $sacrifices,
    ]);

    return $user->fresh();
}

it('adds the sacrificed pay back to threshold income', function () {
    // The assertion the old implementation fails. £210,000 gross with 8% sacrificed is
    // £16,800 given up. Recorded gross, so the £16,800 comes out to reach real income
    // and goes straight back on at s228ZA(3) — threshold income is the full £210,000.
    $result = app(IncomeDefinitionsService::class)->calculate(sacrificeEarner(210_000, true, 'gross')->id);

    expect($result['deductions']['salary_sacrificed'])->toBe(16800.00)
        ->and($result['threshold_income'])->toBe(210000.00)
        // Never the employee's relief: the pay was given up before it was earned.
        ->and($result['deductions']['employee_pension_contributions'])->toBe(0.00)
        ->and($result['pension_arrangement'])->toBe('salary_sacrifice');
});

it('counts the sacrificed pay as an employer contribution', function () {
    // The second consequence of the same gap. Under sacrifice the contribution is
    // legally the employer's, so it belongs in adjusted income's employer figure —
    // £6,300 of employer contribution plus £16,800 sacrificed.
    $result = app(IncomeDefinitionsService::class)->calculate(sacrificeEarner(210_000, true, 'gross')->id);

    expect($result['deductions']['employer_pension_contributions'])->toBe(23100.00)
        // Adjusted income is real income plus employer contributions: £193,200 + £23,100.
        ->and($result['adjusted_income'])->toBe(216300.00);
});

it('tapers the Annual Allowance where sacrifice used to hide the threshold', function () {
    // Acceptance 4 — the taper DECISION exercised, not just the figure. Both statutory
    // gates have to be crossed: threshold income over £200,000 and adjusted income over
    // £260,000. This household crosses both, and did so before the sacrifice too — what
    // changed is that threshold income no longer falls below the gate on the strength of
    // pay the statute says to add back.
    $user = User::factory()->create([
        'annual_employment_income' => 300_000,
        'employment_income_basis' => 'gross',
        'marital_status' => 'single',
    ]);
    DCPension::factory()->create([
        'user_id' => $user->id,
        'annual_salary' => 300_000,
        'employee_contribution_percent' => 20,
        'employer_contribution_percent' => 5,
        'monthly_contribution_amount' => 0,
        'salary_sacrifice' => true,
    ]);

    $result = app(IncomeDefinitionsService::class)->calculate($user->fresh()->id);

    // £60,000 sacrificed. Threshold income is the full £300,000 — without the add-back
    // it would read £240,000, still over the gate here, but the £60,000 is exactly what
    // a sacrificing earner near £200,000 uses to drop under it.
    expect($result['threshold_income'])->toBe(300000.00)
        ->and($result['adjusted_allowances']['pension_aa_tapered'])->toBeTrue()
        ->and($result['adjusted_allowances']['pension_annual_allowance'])->toBeLessThan(60000.00);
});

it('leaves a net-pay contributor exactly where they were', function () {
    // The control. If this moves, the change did more than route sacrificed pay.
    $result = app(IncomeDefinitionsService::class)->calculate(sacrificeEarner(210_000, false)->id);

    expect($result['deductions']['salary_sacrificed'])->toBe(0.00)
        ->and($result['deductions']['employee_pension_contributions'])->toBe(16800.00)
        // Total less the net-pay contribution, with nothing added back.
        ->and($result['threshold_income'])->toBe(193200.00)
        ->and($result['pension_arrangement'])->toBe('net_pay')
        ->and($result['employment_income_basis'])->toBeNull();
});

it('reaches the same threshold income whichever basis the user recorded', function () {
    // Why the ambiguity was survivable, and the single most important assertion here.
    // The basis is applied BEFORE any definition is struck, so it moves net income and
    // leaves the taper gate alone — the decision never turns on the guess.
    //
    // One person, described two ways: £210,000 gross with £16,800 given up, or the same
    // person reporting the £193,200 that reaches their payslip. The pension's own
    // `annual_salary` is held at the gross figure in both, because the sacrifice is a
    // percentage of contractual salary and does not change when the user reports a
    // different number on a different page (tests/CLAUDE.md §4, Fixture).
    $build = function (int $reported, string $basis): int {
        $user = User::factory()->create([
            'annual_employment_income' => $reported,
            'employment_income_basis' => $basis,
            'marital_status' => 'single',
        ]);

        DCPension::factory()->create([
            'user_id' => $user->id,
            'annual_salary' => 210_000,
            'employee_contribution_percent' => 8,
            'employer_contribution_percent' => 3,
            'monthly_contribution_amount' => 0,
            'salary_sacrifice' => true,
        ]);

        return $user->id;
    };

    $service = app(IncomeDefinitionsService::class);
    $gross = $service->calculate($build(210_000, 'gross'));
    $post = $service->calculate($build(193_200, 'post_sacrifice'));

    expect($post['threshold_income'])->toEqualWithDelta($gross['threshold_income'], 0.01)
        ->and($post['total_income'])->toEqualWithDelta($gross['total_income'], 0.01)
        ->and($post['adjusted_income'])->toEqualWithDelta($gross['adjusted_income'], 0.01)
        // And the figure they converge on is the real one, not either report.
        ->and($gross['threshold_income'])->toBe(210000.00);
});

it('says when it assumed the basis rather than being told', function () {
    // An assumption named is a different thing from an assumption applied silently.
    // The panel keys its wording off this.
    $assumed = app(IncomeDefinitionsService::class)->calculate(sacrificeEarner(210_000, true)->id);
    $told = app(IncomeDefinitionsService::class)->calculate(sacrificeEarner(210_000, true, 'gross')->id);

    expect($assumed['employment_income_basis'])->toBe('assumed_gross')
        ->and($told['employment_income_basis'])->toBe('gross')
        // And the assumption IS gross, so the two land on the same figures.
        ->and($assumed['threshold_income'])->toBe($told['threshold_income']);
});
