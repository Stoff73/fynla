<?php

declare(strict_types=1);

use App\Models\Estate\Bequest;
use App\Models\Estate\Gift;
use App\Models\Estate\Will;
use App\Models\FamilyMember;
use App\Models\Property;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Estate\IHTCalculationService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * W-0154 — F1, F2 and F3. One mechanism, not three fixes.
 *
 * The defect: every per-person input was read from the **logged-in user only**
 * (gifts, transferred allowances, the charitable planning percentage, and the will)
 * while every asset and liability was **pooled** across the household. So the same
 * married household was quoted two different inheritance tax bills depending on who
 * logged in — £149,712 against £89,712, a £60,000 gap that was one spouse's
 * £150,000 chargeable transfer reducing the band in his view and by nothing in hers.
 *
 * **Everything here is built from fixtures.** The persona household (users 16 and 17)
 * was being driven in a live browser when this landed and its figures move.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->service = app(IHTCalculationService::class);
});

/**
 * The audit's household, reproduced exactly: net estate £1,234,280, one £150,000
 * chargeable lifetime transfer inside the seven-year window, and a £10,000 charitable
 * legacy in each spouse's will to a different charity.
 *
 * @return array{0: User, 1: User}
 */
function ihtHousehold(): array
{
    $david = User::factory()->create([
        'marital_status' => 'married',
        'date_of_birth' => '1968-03-04',
        'gender' => 'male',
    ]);
    $sarah = User::factory()->create([
        'marital_status' => 'married',
        'date_of_birth' => '1970-07-19',
        'gender' => 'female',
        'spouse_id' => $david->id,
    ]);
    $david->update(['spouse_id' => $sarah->id]);

    // A main residence worth more than the combined £350,000 residence allowance, so
    // the s8E(2) cap does not bite and the allowance is the full £350,000.
    Property::factory()->create([
        'user_id' => $david->id,
        'property_type' => 'main_residence',
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
        'current_value' => 400_000,
    ]);
    SavingsAccount::factory()->create([
        'user_id' => $david->id,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
        'current_balance' => 834_280,
    ]);

    // The residence allowance needs a direct descendant.
    FamilyMember::factory()->create([
        'user_id' => $david->id,
        'relationship' => 'child',
    ]);

    // David's chargeable lifetime transfer. Inside seven years, so it reduces the band.
    Gift::factory()->create([
        'user_id' => $david->id,
        'gift_type' => 'clt',
        'gift_value' => 150_000,
        'gift_date' => now()->subYears(2)->toDateString(),
    ]);

    // A £10,000 charitable legacy each, to different charities.
    foreach ([[$david, 'Cancer Research UK'], [$sarah, 'British Heart Foundation']] as [$member, $charity]) {
        // `has_will` false leaves `spouse_primary_beneficiary` null, which the column
        // does not permit — set both explicitly rather than letting the factory roll.
        $will = Will::factory()->create([
            'user_id' => $member->id,
            'has_will' => true,
            'spouse_primary_beneficiary' => true,
            'spouse_bequest_percentage' => 100,
        ]);
        Bequest::factory()->create([
            'will_id' => $will->id,
            'user_id' => $member->id,
            'beneficiary_name' => $charity,
            'beneficiary_type' => 'charity',
            'bequest_type' => 'specific_amount',
            'specific_amount' => 10_000,
            'percentage_of_estate' => null,
        ]);
    }

    return [$david->fresh(), $sarah->fresh()];
}

describe('F1 — one household, one bill', function () {
    it('gives the same answer whichever spouse is logged in', function () {
        [$david, $sarah] = ihtHousehold();

        $fromDavid = $this->service->calculate($david, $sarah, true);
        $fromSarah = $this->service->calculate($sarah, $david, true);

        // The headline defect: this differed by £60,000.
        expect($fromDavid['iht_liability'])->toBe($fromSarah['iht_liability'])
            ->and($fromDavid['nrb_available'])->toBe($fromSarah['nrb_available'])
            ->and($fromDavid['charitable_deduction'])->toBe($fromSarah['charitable_deduction'])
            ->and($fromDavid['taxable_estate'])->toBe($fromSarah['taxable_estate']);
    });

    it('reaches the figure the audit computed by hand, from both logins', function () {
        [$david, $sarah] = ihtHousehold();

        foreach ([[$david, $sarah], [$sarah, $david]] as [$user, $spouse]) {
            $result = $this->service->calculate($user, $spouse, true);

            expect($result['total_net_estate'])->toBe(1_234_280.0)
                // W-0367 — the £150,000 settlement is relieved by £6,000 of s19
                // annual exemption (£3,000 for its year, £3,000 carried), so the
                // deduction is £144,000. 650,000 − 144,000.
                ->and($result['nrb_available'])->toBe(506_000.0)
                ->and($result['rnrb_available'])->toBe(350_000.0)
                ->and($result['total_allowances'])->toBe(856_000.0)
                ->and($result['charitable_deduction'])->toBe(20_000.0) // both wills
                // £6,000 lower than before, matching the larger band (W-0367).
                ->and($result['taxable_estate'])->toBe(358_280.0)
                ->and($result['iht_liability'])->toBe(143_312.0);
        }
    });

    it('deducts one spouse gifts from the household band whichever spouse asks', function () {
        [$david, $sarah] = ihtHousehold();

        foreach ([[$david, $sarah], [$sarah, $david]] as [$user, $spouse]) {
            $result = $this->service->calculate($user, $spouse, true);

            expect($result['nrb_gift_deduction'])->toBe(144_000.0)
                ->and($result['nrb_deduction']['clts_in_7_years'])->toBe(144_000.0);
        }
    });

    it('pools both charitable legacies for the exemption, not just the logged-in one', function () {
        [$david, $sarah] = ihtHousehold();

        // Deducting only the logged-in user's £10,000 understated the exemption on
        // both accounts, and was independently wrong of the gift asymmetry.
        expect($this->service->calculate($david, $sarah, true)['charitable_deduction'])->toBe(20_000.0);
    });
});

describe('F1 — the exemption and the rate test use different wills', function () {
    /**
     * tax-compliance-reviewer's statutory ruling: IHTA 1984 Sch 1A tests the estate of
     * ONE deceased person. The second-death estate is the survivor's, so the 10% test
     * runs against the survivor's will alone. Summing both wills would over-qualify
     * households for the 36% rate.
     */
    it('tests the 10% threshold against the survivor legacy alone', function () {
        [$david, $sarah] = ihtHousehold();

        $result = $this->service->calculate($david, $sarah, true);

        // Pooled for the exemption; the survivor's alone for the rate test.
        expect($result['charitable_deduction'])->toBe(20_000.0)
            ->and($result['iht_rate_percent'])->toBe(40.0);
    });

    it('does not let a second will push a household over the 10% threshold', function () {
        [$david, $sarah] = ihtHousehold();

        // Two £70,000 legacies would clear a £73,428 threshold if summed, and neither
        // clears it alone. The rate must stay at 40%.
        Bequest::where('user_id', $david->id)->update(['specific_amount' => 70_000]);
        Bequest::where('user_id', $sarah->id)->update(['specific_amount' => 70_000]);

        $result = $this->service->calculate($david, $sarah, true);

        expect($result['charitable_deduction'])->toBe(140_000.0)
            ->and($result['iht_rate_percent'])->toBe(40.0);
    });
});

describe('F2 — the allowance breakdown reconciles', function () {
    it('publishes every component, and they sum to the total', function () {
        [$david, $sarah] = ihtHousehold();

        $r = $this->service->calculate($david, $sarah, true);

        // This is the whole of F2: the user was shown 325,000 + 0 = 500,000 and the
        // £175,000 difference was two unlabelled effects netting out.
        expect(
            $r['nrb_individual'] + $r['nrb_spouse_modelled'] + $r['nrb_transferred'] - $r['nrb_gift_deduction']
        )->toBe($r['nrb_available']);

        expect($r['nrb_individual'])->toBe(325_000.0)
            ->and($r['nrb_spouse_modelled'])->toBe(325_000.0)
            ->and($r['nrb_gift_deduction'])->toBe(144_000.0);
    });

    /**
     * There is no transferable nil rate band while both spouses are alive — IHTA 1984
     * s8A creates the claim on the survivor's death. `nrb_transferred: 0` is correct
     * today, and the doubled band is a second-death modelling assumption. Do not
     * "fix" this by writing 325,000 into `nrb_transferred`.
     */
    it('keeps the modelled band separate from the statutory transfer', function () {
        [$david, $sarah] = ihtHousehold();

        expect($this->service->calculate($david, $sarah, true)['nrb_transferred'])->toBe(0.0);
    });
});

describe('F3 — allowances cover only the estates being taxed', function () {
    it('does not double the allowances when the spouse estate is not pooled', function () {
        [$david, $sarah] = ihtHousehold();

        // Married, but data sharing off: only David's assets are counted, so only
        // David's allowances apply. This used to give £1,000,000 of allowances
        // against one person's estate.
        $shared = $this->service->calculate($david, $sarah, true);
        $unshared = $this->service->calculate($david, $sarah, false);

        expect($shared['nrb_available'])->toBe(506_000.0)
            ->and($unshared['nrb_available'])->toBe(181_000.0)   // 325,000 − 144,000 of gifts
            ->and($unshared['nrb_spouse_modelled'])->toBe(0.0)
            ->and($unshared['rnrb_available'])->toBeLessThanOrEqual(181_000.0);
    });

    it('produces a bill on an estate that previously escaped entirely', function () {
        $user = User::factory()->create(['marital_status' => 'married', 'date_of_birth' => '1968-03-04', 'gender' => 'male']);
        $spouse = User::factory()->create(['marital_status' => 'married', 'date_of_birth' => '1970-07-19', 'gender' => 'female', 'spouse_id' => $user->id]);
        $user->update(['spouse_id' => $spouse->id]);

        SavingsAccount::factory()->create([
            'user_id' => $user->id,
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
            'current_balance' => 700_000,
        ]);

        // No residence, so no residence allowance either way. £700,000 − £325,000 =
        // £375,000 at 40% = £150,000. With the allowances wrongly doubled it was £0.
        $result = $this->service->calculate($user, $spouse, false);

        expect($result['nrb_available'])->toBe(325_000.0)
            ->and($result['iht_liability'])->toBe(150_000.0);
    });
});

describe('the per-person cap on gifts (IHTA 1984 s8A)', function () {
    it('never lets one spouse gifts consume the other band', function () {
        [$david, $sarah] = ihtHousehold();

        // £400,000 exhausts David's own £325,000 and reaches no further. The old code
        // took max(0, 650,000 − 400,000) = £250,000, eating £75,000 of Sarah's band.
        Gift::where('user_id', $david->id)->update(['gift_value' => 400_000]);

        $result = $this->service->calculate($david, $sarah, true);

        expect($result['nrb_gift_deduction'])->toBe(325_000.0)
            ->and($result['nrb_available'])->toBe(325_000.0);
    });

    it('adds both spouses gifts when both have made them, each capped at their own band', function () {
        [$david, $sarah] = ihtHousehold();

        Gift::where('user_id', $david->id)->update(['gift_value' => 400_000]);
        Gift::factory()->create([
            'user_id' => $sarah->id,
            'gift_type' => 'clt',
            'gift_value' => 100_000,
            'gift_date' => now()->subYears(2)->toDateString(),
        ]);

        $result = $this->service->calculate($david, $sarah, true);

        // W-0367 — each gift is relieved by £6,000 first. David's £400,000
        // becomes £394,000 and is still capped at his own £325,000 band; Sarah's
        // £100,000 becomes £94,000 and is uncapped → £419,000.
        expect($result['nrb_gift_deduction'])->toBe(419_000.0)
            ->and($result['nrb_available'])->toBe(231_000.0);
    });
});

/**
 * W-0134 acceptance 4 — the footnote beneath the allowance rows.
 *
 * The rows were made to add up in cycle 1; this sentence was the last figure on the
 * page a reader could not reconcile with them. It opened "Combined Nil Rate Band of
 * £650,000 available" while the rows itemised £500,000, so a reader who trusts prose
 * over tables took away the wrong band.
 *
 * These assert the RELATIONSHIP between the sentence and the payload — the headline
 * figure parsed back out of the prose must equal `nrb_available` — rather than a
 * fixed string, because a fixed string can be updated in lockstep with a wrong
 * number and still pass.
 */
describe('the nil-rate-band footnote states the band actually applied', function () {
    it('leads with the applied band, not the pre-deduction one', function () {
        [$david, $sarah] = ihtHousehold();

        $result = $this->service->calculate($david, $sarah, true);

        expect($result['nrb_message'])->toMatch('/of £([\d,]+) applied/');

        preg_match('/of £([\d,]+) applied/', $result['nrb_message'], $matches);

        expect((float) str_replace(',', '', $matches[1]))->toBe($result['nrb_available'])
            ->and($result['nrb_message'])->not->toContain('£650,000');
    });

    it('shows the gift deduction as the working that reaches the applied band', function () {
        [$david, $sarah] = ihtHousehold();

        $result = $this->service->calculate($david, $sarah, true);

        // £325,000 each, less the £144,000 net chargeable transfer (the £150,000
        // settlement after its £6,000 s19 exemption), is £506,000 — every figure
        // in the sentence, and they reconcile by hand.
        expect($result['nrb_message'])
            ->toContain('£506,000 applied')
            ->toContain('£325,000 each')
            ->toContain('less £144,000 of allowance used by gifts made within the last 7 years');
    });

    it('says applied rather than available when nothing has been deducted', function () {
        [$david, $sarah] = ihtHousehold();
        Gift::where('user_id', $david->id)->delete();

        $result = $this->service->calculate($david, $sarah, true);

        expect($result['nrb_available'])->toBe(650_000.0)
            ->and($result['nrb_message'])->toContain('£650,000 applied')
            ->and($result['nrb_message'])->not->toContain('available');
    });

    /**
     * The same vocabulary as the `nrb-spouse-modelled` row note in
     * `IHTCalculationTable.vue`. One behaviour, one wording (Rule 20) — the prose
     * must not describe as held today what the row directly above it describes as
     * modelled on a second death.
     */
    it('describes a living spouse band as modelled on second death', function () {
        [$david, $sarah] = ihtHousehold();

        expect($this->service->calculate($david, $sarah, true)['nrb_message'])
            ->toContain('modelled on second death')
            ->toContain('there is no transferable allowance while you are both alive');
    });

    it('reconciles for a single person whose gifts have reduced the band', function () {
        $user = User::factory()->create(['marital_status' => 'single', 'date_of_birth' => '1968-03-04']);
        SavingsAccount::factory()->create([
            'user_id' => $user->id,
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
            'current_balance' => 700_000,
        ]);
        Gift::factory()->create([
            'user_id' => $user->id,
            'gift_type' => 'clt',
            'gift_value' => 150_000,
            'gift_date' => now()->subYears(2)->toDateString(),
        ]);

        $result = $this->service->calculate($user);

        preg_match('/of £([\d,]+) applied/', $result['nrb_message'], $matches);

        // 325,000 − 144,000, the settlement net of its s19 exemption (W-0367).
        expect($result['nrb_available'])->toBe(181_000.0)
            ->and((float) str_replace(',', '', $matches[1]))->toBe($result['nrb_available'])
            ->and($result['nrb_message'])->not->toContain('modelled on second death');
    });
});
