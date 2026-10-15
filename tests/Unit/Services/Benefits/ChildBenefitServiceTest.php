<?php

declare(strict_types=1);

use App\Models\DCPension;
use App\Models\FamilyMember;
use App\Models\User;
use App\Services\Benefits\ChildBenefitService;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Pins ChildBenefitService HICBC behaviour around Adjusted Net Income.
 *
 * HMRC rule: HICBC applies to the higher earner's Adjusted Net Income, NOT
 * gross income. ANI = total income − gross pension contributions
 * (relief-at-source) − grossed-up Gift Aid − Blind Person's Allowance.
 *
 * Bug fixed: `calculateAdjustedNetIncome()` previously summed gross income
 * only (with an in-code comment acknowledging the simplification). For a
 * £75k earner contributing £20k gross to a pension, true ANI is £55k
 * → no HICBC; pre-fix code calculated ANI £75k → 7.5% clawback applied.
 *
 * Audit finding: review-tax-compliance §10.2 (Medium severity).
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    // W-0532 — ChildBenefitService gates `benefits_child` through TeaserGate, and
    // Adjusted Net Income reaches it, so the tier rows must exist here too.
    $this->seed(TierConfigurationSeeder::class);
    $this->service = app(ChildBenefitService::class);
});

describe('ChildBenefitService — HICBC uses Adjusted Net Income', function () {
    it('does not apply HICBC when pension contributions bring ANI below £60k threshold', function () {
        // £75k employment, £20k gross pension contributions.
        // ANI = £55k → below £60k HICBC threshold → no charge.
        // Pre-fix: ANI = £75k → 75% clawback × £1,406.60 = £1,054.95 wrongly charged.
        $user = User::factory()->create([
            'annual_employment_income' => 75_000,
        ]);
        DCPension::create([
            'user_id' => $user->id,
            'provider' => 'Test Provider',
            'pension_type' => 'occupational',
            'annual_salary' => 100_000,
            'employee_contribution_percent' => 20.0,
            'employer_contribution_percent' => 0.0,
            'current_value' => 0,
        ]);
        FamilyMember::factory()->child()->create([
            'user_id' => $user->id,
            'receives_child_benefit' => true,
            'date_of_birth' => '2020-01-01',
        ]);

        $result = $this->service->calculateChildBenefitPosition($user->fresh());

        expect($result['hicbc']['applies'])->toBeFalse()
            ->and((float) $result['hicbc']['charge'])->toBe(0.0)
            ->and((float) $result['net_annual_benefit'])->toBe(1_406.60);
    });

    it('does not apply HICBC when Gift Aid donations bring ANI below threshold', function () {
        // £65k employment, £4k cash donations under Gift Aid (gross-up = £5k).
        // ANI = £60k → at the threshold → no HICBC (strictly above required).
        $user = User::factory()->create([
            'annual_employment_income' => 65_000,
            'is_gift_aid' => true,
            'annual_charitable_donations' => 4_000,
        ]);
        FamilyMember::factory()->child()->create([
            'user_id' => $user->id,
            'receives_child_benefit' => true,
            'date_of_birth' => '2020-01-01',
        ]);

        $result = $this->service->calculateChildBenefitPosition($user->fresh());

        expect($result['hicbc']['applies'])->toBeFalse()
            ->and((float) $result['hicbc']['charge'])->toBe(0.0);
    });

    it('applies partial HICBC based on ANI in taper band', function () {
        // £80k employment, £10k gross pension. ANI = £70k → £10k over threshold
        // → clawback = £10k / £200 = 50% → £1,406.60 × 0.5 = £703.30, rounded
        // down to whole pounds (ITEPA 2003 s681C(3)) → £703.
        $user = User::factory()->create([
            'annual_employment_income' => 80_000,
        ]);
        DCPension::create([
            'user_id' => $user->id,
            'provider' => 'Test Provider',
            'pension_type' => 'occupational',
            'annual_salary' => 100_000,
            'employee_contribution_percent' => 10.0,
            'employer_contribution_percent' => 0.0,
            'current_value' => 0,
        ]);
        FamilyMember::factory()->child()->create([
            'user_id' => $user->id,
            'receives_child_benefit' => true,
            'date_of_birth' => '2020-01-01',
        ]);

        $result = $this->service->calculateChildBenefitPosition($user->fresh());

        expect($result['hicbc']['applies'])->toBeTrue()
            ->and($result['hicbc']['clawback_percentage'])->toBe(50.0)
            ->and((float) $result['hicbc']['charge'])->toBe(703.0);
    });

    it('falls through to gross income when there are no deductions (backwards compat)', function () {
        // £75k employment, no pension, no Gift Aid. ANI = £75k → £15k over threshold
        // → clawback = 75% → £1,406.60 × 0.75 = £1,054.95, rounded down to
        // whole pounds (ITEPA 2003 s681C(3)) → £1,054.
        $user = User::factory()->create([
            'annual_employment_income' => 75_000,
        ]);
        FamilyMember::factory()->child()->create([
            'user_id' => $user->id,
            'receives_child_benefit' => true,
            'date_of_birth' => '2020-01-01',
        ]);

        $result = $this->service->calculateChildBenefitPosition($user->fresh());

        expect($result['hicbc']['applies'])->toBeTrue()
            ->and($result['hicbc']['clawback_percentage'])->toBe(75.0)
            ->and((float) $result['hicbc']['charge'])->toBe(1_054.0);
    });

    it('rounds the percentage and the charge down, as the legislation does', function () {
        // ITEPA 2003 s681C(2)-(3) (L £60,000 and X £200 from 2024-25, F(No.2)A
        // 2024 s5): £1,050 over is 5.25 steps of £200; a percentage that is not
        // a whole number is rounded down, so 5%, and £1,406.60 × 5% = £70.33
        // is rounded down to £70.
        $user = User::factory()->create(['annual_employment_income' => 61_050]);
        FamilyMember::factory()->child()->create([
            'user_id' => $user->id,
            'receives_child_benefit' => true,
            'date_of_birth' => '2020-01-01',
        ]);

        $result = $this->service->calculateChildBenefitPosition($user->fresh());

        expect($result['hicbc']['clawback_percentage'])->toBe(5.0)
            ->and((float) $result['hicbc']['charge'])->toBe(70.0)
            ->and((float) $result['hicbc']['net_benefit'])->toBe(1_336.60);
    });

    it('rounds the Child Benefit total down before taking the percentage, as the legislation does', function () {
        // ITEPA 2003 s681C(3)(a): the total of the amounts in s681C(1) is
        // rounded down to a whole number of pounds, then the charge is that
        // total at the percentage, itself rounded down. £19,800 over is 99%;
        // £1,406.60 → £1,406, × 99% = £1,391.94 → £1,391. Taking 99% of the
        // unrounded £1,406.60 gives £1,392.53 → £1,392, £1 over the statute.
        $user = User::factory()->create(['annual_employment_income' => 79_800]);
        FamilyMember::factory()->child()->create([
            'user_id' => $user->id,
            'receives_child_benefit' => true,
            'date_of_birth' => '2020-01-01',
        ]);

        $result = $this->service->calculateChildBenefitPosition($user->fresh());

        expect($result['hicbc']['clawback_percentage'])->toBe(99.0)
            ->and((float) $result['hicbc']['charge'])->toBe(1_391.0)
            ->and((float) $result['hicbc']['net_benefit'])->toBe(15.60);
    });

    it('respects override income parameter and skips ANI calculation entirely', function () {
        // When caller passes an explicit ANI override, the service must use it
        // directly without re-computing from user fields.
        $user = User::factory()->create([
            'annual_employment_income' => 200_000,
        ]);
        FamilyMember::factory()->child()->create([
            'user_id' => $user->id,
            'receives_child_benefit' => true,
            'date_of_birth' => '2020-01-01',
        ]);

        $result = $this->service->calculateChildBenefitPosition($user->fresh(), adjustedNetIncome: 55_000);

        expect($result['hicbc']['applies'])->toBeFalse();
    });
});
