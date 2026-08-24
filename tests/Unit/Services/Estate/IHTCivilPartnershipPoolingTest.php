<?php

declare(strict_types=1);

use App\Models\Estate\Liability;
use App\Models\ExpenditureProfile;
use App\Models\Investment\InvestmentAccount;
use App\Models\Property;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Estate\IHTCalculationService;
use App\Services\Investment\InvestmentProjectionService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * W-0474 — a civil partnership is a marriage for Inheritance Tax, and this service
 * was the one place that did not say so.
 *
 * `calculate()` decided WHOSE records it covered in two different ways: the headline
 * on `in_array($user->marital_status, ['married']) && $spouse`, every projection
 * branch on `$dataSharingEnabled && $spouse` alone. Neither `liveSpouse()` nor
 * `hasAcceptedSpousePermission()` consults `marital_status`, so for a civil
 * partnership the first was false and the second true: the projection pooled two
 * partners' assets, properties, investments, liabilities and business relief and
 * assessed them against ONE person's £325,000 + £175,000, with the taper base struck
 * on the doubled estate. It OVERSTATED tax.
 *
 * Statute: IHTA 1984 s18, s8A and s8G, extended to civil partners by Civil
 * Partnership Act 2004 s.246 and SI 2005/3229.
 *
 * The same predicate ran the other way for an unmarried couple who had linked
 * accounts and accepted sharing — a headline taxing one estate, a projection pooling
 * two, against a single nil rate band and a spouse exemption they are not entitled
 * to (W-0340). Both directions are pinned below, because one predicate now answers
 * both and a future edit that fixes one by reverting the other must fail here.
 *
 * ## Why the fixtures look like this
 *
 * **Both members hold assets, and the amounts differ (£400,000 / £150,000).** If
 * only the primary held anything, or the two held the same amount, pooling and not
 * pooling would produce figures a test could not tell apart (`tests/CLAUDE.md` §4).
 *
 * **The simulation is stubbed to force the compounded fallback.** Not the Mock
 * variant: it supplies no ownership or growth figure and nothing here asserts on
 * one. It removes Monte Carlo variance so two households built identically can be
 * compared exactly. Every assertion compares households differing ONLY in marital
 * status, so the growth assumptions cancel.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);

    $stub = Mockery::mock(InvestmentProjectionService::class);
    $stub->shouldReceive('getPortfolioProjections')
        ->andReturn(['portfolio' => null, 'accounts' => [], 'message' => 'No investment accounts found']);
    app()->instance(InvestmentProjectionService::class, $stub);
});

afterEach(function () {
    Mockery::close();
});

/**
 * A two-person household of the given marital status, each member holding assets,
 * both the same age so the projection horizon is identical between households.
 *
 * @return array{0: User, 1: User}
 */
function partnershipHousehold(string $maritalStatus): array
{
    $primary = User::factory()->create([
        'marital_status' => $maritalStatus,
        'date_of_birth' => '1970-01-01',
        'gender' => 'male',
        'monthly_expenditure' => 2_000,
        'annual_employment_income' => 60_000,
    ]);
    $partner = User::factory()->create([
        'marital_status' => $maritalStatus,
        'date_of_birth' => '1970-01-01',
        'gender' => 'female',
        'spouse_id' => $primary->id,
        'monthly_expenditure' => 2_000,
        'annual_employment_income' => 30_000,
    ]);
    $primary->update(['spouse_id' => $partner->id]);

    Property::factory()->create([
        'user_id' => $primary->id,
        'joint_owner_id' => null,
        'property_type' => 'main_residence',
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
        'current_value' => 400_000,
    ]);
    InvestmentAccount::factory()->create([
        'user_id' => $partner->id,
        'joint_owner_id' => null,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
        'current_value' => 150_000,
    ]);

    // W-0474 F2 (tax-compliance-reviewer, 2026-08-24). Without the rows below this
    // was the FIXTURE variant from `tests/CLAUDE.md` §4 — the branch is never entered,
    // and nothing says so. Every cash input was zero for both members, so
    // `projected_cash` came out identical whether the partner was pooled or not, and
    // the unmarried-couple case passed with the cash branch still running the pre-fix
    // predicate. The liability and the two incomes exist so the projected liabilities
    // and projected cash branches produce a DIFFERENT number when a partner is pooled;
    // the amounts are asymmetric for the reason the docblock gives above.
    foreach ([[$primary, 40_000], [$partner, 15_000]] as [$member, $balance]) {
        // Every field the projection reads is pinned. The factories randomise
        // `interest_rate`, `account_type`, `liability_type`, `monthly_payment` and
        // `maturity_date`, and this test compares TWO households that must differ in
        // nothing but marital status — so an unpinned field gives them different
        // projected figures and the comparison fails perhaps one run in several. It
        // did exactly that once before these were pinned.
        SavingsAccount::factory()->create([
            'user_id' => $member->id,
            'joint_owner_id' => null,
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
            'account_type' => 'easy_access',
            'interest_rate' => 0.02,
            'current_balance' => $balance,
        ]);

        // `recordedAnnualExpenditure()` reads `expenditureProfile.total_monthly_expenditure`,
        // NOT `users.monthly_expenditure` — setting only the latter left expenditure
        // falling back to a share of an income of zero.
        ExpenditureProfile::factory()->create([
            'user_id' => $member->id,
            'total_monthly_expenditure' => 1_500,
        ]);
    }

    Liability::factory()->create([
        'user_id' => $partner->id,
        'liability_type' => 'personal_loan',
        'current_balance' => 25_000,
        'monthly_payment' => 400,
        'interest_rate' => 0.05,
        'maturity_date' => null,
    ]);

    return [$primary->fresh(), $partner->fresh()];
}

function partnershipFigures(User $viewer, ?User $partner): array
{
    return app(IHTCalculationService::class)->calculate($viewer, $partner, true);
}

describe('a civil partnership', function () {
    it('is assessed exactly as a marriage with the same holdings', function () {
        [$marriedPrimary, $marriedPartner] = partnershipHousehold('married');
        [$civilPrimary, $civilPartner] = partnershipHousehold('civil_partnership');

        $married = partnershipFigures($marriedPrimary, $marriedPartner);
        $civil = partnershipFigures($civilPrimary, $civilPartner);

        // The current column already agreed — it read `['married']` and simply did
        // not pool. Pinned so a fix that widens only the projection is caught.
        expect($civil['total_gross_assets'])->toEqualWithDelta($married['total_gross_assets'], 0.01);
        expect($civil['iht_liability'])->toEqualWithDelta($married['iht_liability'], 0.01);

        // The projection is where the defect lived. Under it, the civil partnership
        // pooled £550,000 of assets against a single £325,000 + £175,000.
        expect($civil['projected_gross_assets'])->toEqualWithDelta($married['projected_gross_assets'], 0.01);
        expect($civil['projected_nrb_available'])->toEqualWithDelta($married['projected_nrb_available'], 0.01);
        expect($civil['projected_rnrb_available'])->toEqualWithDelta($married['projected_rnrb_available'], 0.01);
        expect($civil['projected_iht_liability'])->toEqualWithDelta($married['projected_iht_liability'], 0.01);
    });

    it('pools both partners rather than taxing one of them alone', function () {
        [$primary, $partner] = partnershipHousehold('civil_partnership');

        $pooled = partnershipFigures($primary, $partner);
        $alone = partnershipFigures($primary->fresh(), null);

        // The partner's £150,000 is in the household estate on both columns, and the
        // allowances double to meet it. A civil partnership pooling on one column
        // only is the defect in either direction.
        expect($pooled['total_gross_assets'])->toBeGreaterThan($alone['total_gross_assets']);
        expect($pooled['projected_gross_assets'])->toBeGreaterThan($alone['projected_gross_assets']);
        expect($pooled['projected_nrb_available'])->toBeGreaterThan($alone['projected_nrb_available']);
    });
});

describe('an unmarried couple who have linked accounts and accepted sharing', function () {
    it('does not have its projected estate pooled against one set of allowances', function () {
        [$primary, $partner] = partnershipHousehold('single');

        $linked = partnershipFigures($primary, $partner);
        $alone = partnershipFigures($primary->fresh(), null);

        // W-0340. Sharing is on and the accounts are linked, but unmarried partners
        // get no spouse exemption and no transferable band — so the partner's
        // £150,000 must not appear in either column of this person's estate.
        expect($linked['projected_gross_assets'])->toEqualWithDelta($alone['projected_gross_assets'], 0.01);
        expect($linked['total_gross_assets'])->toEqualWithDelta($alone['total_gross_assets'], 0.01);
        expect($linked['projected_nrb_available'])->toEqualWithDelta($alone['projected_nrb_available'], 0.01);
    });
});
