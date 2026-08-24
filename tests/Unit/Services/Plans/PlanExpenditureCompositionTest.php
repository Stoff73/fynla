<?php

declare(strict_types=1);

use App\Models\Estate\Liability;
use App\Models\User;
use App\Services\Plans\DisposableIncomeAccessor;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * W-0140 — every plan surface takes its "Annual Expenditure" figure from
 * DisposableIncomeAccessor. The figure is recorded entries PLUS financial
 * commitments, and stays that way: Disposable Income has to subtract commitments
 * to be true. What must not happen is a user who has recorded no expenditure at
 * all being shown a number, under a label about spending, that is entirely
 * financial commitments.
 *
 * These tests use real records through the real service — no mock supplies the
 * figures being asserted.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->accessor = app(DisposableIncomeAccessor::class);
});

/** A £300/month non-mortgage liability: £3,600 a year of financial commitments. */
function commitmentOf300PerMonth(User $user): void
{
    Liability::create([
        'user_id' => $user->id,
        'liability_type' => 'personal_loan',
        'liability_name' => 'Car loan',
        'ownership_type' => 'individual',
        'current_balance' => 12000,
        'monthly_payment' => 300,
    ]);
}

describe('expenditure composition on plan surfaces', function () {
    it('states that no expenditure is recorded rather than presenting commitments as spending', function () {
        // The production shape of a user who has entered nothing: the column
        // defaults to 'category' and every category is null.
        $user = User::factory()->create([
            'monthly_expenditure' => null,
            'annual_expenditure' => null,
        ]);
        commitmentOf300PerMonth($user);

        $result = $this->accessor->getForUser($user);
        $composition = $result['expenditure_composition'];

        // The composed figure keeps its meaning — it is not reduced to zero.
        expect($result['annual_expenditure'])->toBe(3600.0)
            // ...but the plan can now say the whole of it is commitments.
            ->and($composition['has_recorded_expenditure'])->toBeFalse()
            ->and($composition['recorded_annual'])->toBe(0.0)
            ->and($composition['commitments_annual'])->toBe(3600.0)
            ->and($composition['basis'])->toContain('no expenditure recorded');
    });

    it('discloses both components, and they reconcile to the composed total', function () {
        $user = User::factory()->create([
            'monthly_expenditure' => 2450,
            'annual_expenditure' => 29400,
            'expenditure_entry_mode' => 'simple',
        ]);
        commitmentOf300PerMonth($user);

        $result = $this->accessor->getForUser($user);
        $composition = $result['expenditure_composition'];

        expect($composition['has_recorded_expenditure'])->toBeTrue()
            ->and($composition['recorded_annual'])->toBe(29400.0)
            ->and($composition['commitments_annual'])->toBe(3600.0)
            ->and($composition['recorded_annual'] + $composition['commitments_annual'])
            ->toBe($result['annual_expenditure']);
    });

    it('reads the category entries as the recorded component when they are the entry mode', function () {
        $user = User::factory()->create([
            'monthly_expenditure' => null,
            'annual_expenditure' => null,
            'expenditure_entry_mode' => 'category',
            'food_groceries' => 800,
            'transport_fuel' => 450,
        ]);
        commitmentOf300PerMonth($user);

        $result = $this->accessor->getForUser($user);
        $composition = $result['expenditure_composition'];

        // (800 + 450) x 12 recorded, plus the 3,600 of commitments.
        expect($composition['has_recorded_expenditure'])->toBeTrue()
            ->and($composition['recorded_annual'])->toBe(15000.0)
            ->and($composition['commitments_annual'])->toBe(3600.0)
            ->and($result['annual_expenditure'])->toBe(18600.0);
    });
});
