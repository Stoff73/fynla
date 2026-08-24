<?php

declare(strict_types=1);

use App\Agents\EstateAgent;
use App\Models\DCPension;
use App\Models\SavingsAccount;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Support\Facades\Cache;

/**
 * W-0391 — the backend guard for the will planning screen's estate figure.
 *
 * `WillPlanning.vue` read `iht_summary.current.net_estate`: the COMBINED
 * second-death household estate the Inheritance Tax engine models for a married
 * couple. It is deliberately the same number for both spouses, so both wills in
 * a mirror pair told their testator they leave their partner the whole
 * household — a figure matching neither estate, overstating one spouse's by 2.3
 * times on the live persona.
 *
 * The per-user figure already existed on the same response. This locks the
 * distinction so it cannot quietly collapse again: if a change ever makes
 * `user_net_estate` equal `total_net_estate`, the component test that asserts
 * "David's figure and Sarah's differ" would still pass on its own mocked
 * payload. Only a test against the real engine can catch that.
 *
 * DELIBERATELY ASYMMETRIC ESTATES. £800,000 against £350,000: the two spouses'
 * own figures, the household total, and half the household total are four
 * distinct numbers, so no coincidence can make a wrong reading look right. A
 * 50/50 household would make the per-user figure and half the household figure
 * the same number and this suite could not fail (tests/CLAUDE.md §4, Collision).
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
});

/**
 * @return array{0: User, 1: User}
 */
function unequalMarriedPair(): array
{
    $richer = User::factory()->withActivePremiumSubscription()->create([
        'tier' => 'premium',
        'marital_status' => 'married',
        'date_of_birth' => '1975-11-02',
    ]);
    $poorer = User::factory()->withActivePremiumSubscription()->create([
        'tier' => 'premium',
        'marital_status' => 'married',
        'date_of_birth' => '1978-04-22',
        'spouse_id' => $richer->id,
    ]);
    $richer->update(['spouse_id' => $poorer->id]);

    SavingsAccount::factory()->create([
        'user_id' => $richer->id,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
        'current_balance' => 800_000,
    ]);
    SavingsAccount::factory()->create([
        'user_id' => $poorer->id,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
        'current_balance' => 350_000,
    ]);

    return [$richer, $poorer];
}

describe('POST /api/estate/calculate-iht publishes a per-user estate', function () {
    it('gives the two spouses different own-estate figures and one shared household figure', function () {
        [$richer, $poorer] = unequalMarriedPair();

        $his = $this->actingAs($richer)->postJson('/api/estate/calculate-iht')->assertOk()->json('calculation');
        $hers = $this->actingAs($poorer)->postJson('/api/estate/calculate-iht')->assertOk()->json('calculation');

        expect((float) $his['user_net_estate'])->toBe(800000.0)
            ->and((float) $hers['user_net_estate'])->toBe(350000.0)
            // The assertion that cannot hold while one household aggregate is
            // served to both. This is the defect, stated as a test.
            ->and($his['user_net_estate'])->not->toBe($hers['user_net_estate']);

        // And the household figure IS the same for both — which is correct, and
        // exactly why it must never be presented as one person's estate.
        expect((float) $his['total_net_estate'])->toBe((float) $hers['total_net_estate'])
            ->and((float) $his['total_net_estate'])->toBe(1150000.0);
    });

    it('reads each spouse\'s own estate as the other\'s spouse estate', function () {
        // The pair reconciles: what he calls his is what she calls her spouse's.
        // A per-user figure that did not would be a third mechanism.
        [$richer, $poorer] = unequalMarriedPair();

        $his = $this->actingAs($richer)->postJson('/api/estate/calculate-iht')->assertOk()->json('calculation');
        $hers = $this->actingAs($poorer)->postJson('/api/estate/calculate-iht')->assertOk()->json('calculation');

        expect((float) $his['user_net_estate'])->toBe((float) $hers['spouse_net_estate'])
            ->and((float) $hers['user_net_estate'])->toBe((float) $his['spouse_net_estate']);
    });

    it('never reports one spouse\'s own estate as the household total', function () {
        [$richer, $poorer] = unequalMarriedPair();

        foreach ([$richer, $poorer] as $user) {
            $calculation = $this->actingAs($user)->postJson('/api/estate/calculate-iht')->assertOk()->json('calculation');

            expect((float) $calculation['user_net_estate'])
                ->not->toBe((float) $calculation['total_net_estate']);
        }
    });

    it('agrees with the estate net worth endpoint the /m estate screen reads', function () {
        // Rule 20, measured rather than asserted: `/api/estate/net-worth` feeds
        // resources/mobile/views/modules/Estate.vue, and the will screen now
        // reads `user_net_estate`. If those two ever diverge, one user is being
        // shown two different own-estate figures in one session — which is the
        // shape of the defect this whole batch is about.
        [$richer, $poorer] = unequalMarriedPair();

        foreach ([$richer, $poorer] as $user) {
            $calculation = $this->actingAs($user)->postJson('/api/estate/calculate-iht')->assertOk()->json('calculation');
            $netWorth = $this->actingAs($user)->getJson('/api/estate/net-worth')->assertOk()->json('data.net_worth');

            expect((float) $calculation['user_net_estate'])->toBe((float) $netWorth['net_worth']);
        }
    });
});

/**
 * W-0397 — the third mechanism, and the one that disagreed.
 *
 * `EstateAgent::buildAssetSummary()` summed every gathered asset including the
 * ones flagged `is_iht_exempt`, and it was the only mechanism in the application
 * that did. So one user saw two different own-estate figures in one session: the
 * mobile dashboard said £1,489,500 where the will planning screen and the /m
 * estate screen both said £989,500 — his defined contribution pensions, exactly.
 *
 * THE FIXTURE MUST HOLD A NON-ZERO EXEMPT ASSET. This is the whole reason the
 * defect survived a persona run: Sarah Jones's only exempt asset is a defined
 * benefit scheme valued at £0, so her figure is identical whether the filter
 * exists or not. Testing the household through her can never fail
 * (tests/CLAUDE.md §4, Collision). Every case below gives the user a pension
 * worth more than a third of their estate.
 */
describe('one user, one own-estate figure, across every mechanism (W-0397)', function () {
    it('agrees across the estate agent, the Inheritance Tax engine and the net worth endpoint', function () {
        $user = User::factory()->withActivePremiumSubscription()->create([
            'tier' => 'premium',
            'marital_status' => 'single',
            'spouse_id' => null,
            'date_of_birth' => '1975-11-02',
        ]);

        SavingsAccount::factory()->create([
            'user_id' => $user->id,
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
            'current_balance' => 400_000,
        ]);

        // Outside the estate for Inheritance Tax, because a nominated
        // beneficiary takes it under the scheme's discretion.
        DCPension::factory()->create([
            'user_id' => $user->id,
            'current_fund_value' => 300_000,
        ]);

        Cache::forget("estate_analysis_{$user->id}");
        $agent = app(EstateAgent::class)->analyze($user->id)['data']['summary'];
        Cache::forget("estate_analysis_{$user->id}");

        $calculation = $this->actingAs($user)->postJson('/api/estate/calculate-iht')->assertOk()->json('calculation');
        $netWorth = $this->actingAs($user)->getJson('/api/estate/net-worth')->assertOk()->json('data.net_worth');

        // £400,000, not £700,000. The pension is present and worth three
        // quarters of the savings, so a mechanism that counted it could not
        // reach this number by accident.
        expect((float) $agent['net_estate'])->toBe(400000.0)
            ->and((float) $calculation['user_net_estate'])->toBe(400000.0)
            ->and((float) $netWorth['net_worth'])->toBe(400000.0);
    });

    it('reconciles its own breakdown to its own total', function () {
        // A breakdown that does not add up to the total beside it is how this
        // class of defect hides: filtering the total and not the parts would
        // have left the estate card internally contradictory instead of
        // contradicting another screen.
        $user = User::factory()->withActivePremiumSubscription()->create([
            'tier' => 'premium',
            'marital_status' => 'single',
            'spouse_id' => null,
            'date_of_birth' => '1975-11-02',
        ]);

        SavingsAccount::factory()->create([
            'user_id' => $user->id,
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
            'current_balance' => 400_000,
        ]);
        DCPension::factory()->create([
            'user_id' => $user->id,
            'current_fund_value' => 300_000,
        ]);

        Cache::forget("estate_analysis_{$user->id}");
        $data = app(EstateAgent::class)->analyze($user->id)['data'];
        Cache::forget("estate_analysis_{$user->id}");

        $breakdown = $data['asset_breakdown'];
        $parts = (float) $breakdown['liquid'] + (float) $breakdown['semi_liquid'] + (float) $breakdown['illiquid'];

        expect($parts)->toBe((float) $data['summary']['gross_estate'])
            ->and((float) $data['summary']['gross_estate'])->toBe(400000.0)
            // The pension was the whole of `illiquid` before the filter.
            ->and((float) $breakdown['illiquid'])->toBe(0.0);
    });

    it('leaves a user with no exempt assets exactly where they were', function () {
        // The regression guard. Sarah Jones is this shape, which is why she
        // could never have surfaced the defect — and why her figure must not
        // move now that it is fixed.
        $user = User::factory()->withActivePremiumSubscription()->create([
            'tier' => 'premium',
            'marital_status' => 'single',
            'spouse_id' => null,
            'date_of_birth' => '1975-11-02',
        ]);

        SavingsAccount::factory()->create([
            'user_id' => $user->id,
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
            'current_balance' => 400_000,
        ]);

        Cache::forget("estate_analysis_{$user->id}");
        $summary = app(EstateAgent::class)->analyze($user->id)['data']['summary'];
        Cache::forget("estate_analysis_{$user->id}");

        expect((float) $summary['net_estate'])->toBe(400000.0);
    });
});
