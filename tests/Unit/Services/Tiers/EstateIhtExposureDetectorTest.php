<?php

declare(strict_types=1);

use App\Models\BusinessInterest;
use App\Models\SpousePermission;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\TaxConfigService;
use App\Services\Tiers\EstateIhtExposureDetector;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(TaxConfigurationSeeder::class));

it('flags exposure and returns positive liability when net worth exceeds NRB+RNRB', function () {
    // NRB (325k) + RNRB (175k) = 500k threshold. Create a savings balance well above it.
    $user = User::factory()->create();
    SavingsAccount::factory()->create([
        'user_id' => $user->id,
        'current_balance' => 700_000.00, // £700k — well above £500k threshold
    ]);

    $result = app(EstateIhtExposureDetector::class)->detect($user);

    expect($result['exposed'])->toBeTrue()
        ->and($result['estimated_liability_gbp'])->toBeGreaterThan(0.0)
        ->and($result['headline'])->toBeString()->not->toBeEmpty()
        // Rule #10: user-facing text must spell out "Inheritance Tax"
        ->and($result['headline'])->toContain('Inheritance Tax');
});

it('returns exposed=false when net worth is below the threshold', function () {
    // New user has no assets — net worth is 0, well below NRB+RNRB (£500k).
    $u = User::factory()->create();
    $result = app(EstateIhtExposureDetector::class)->detect($u);

    expect($result['exposed'])->toBeFalse()
        ->and($result['estimated_liability_gbp'])->toEqual(0.0);
});

it('returns no score — only currency and a plain string headline', function () {
    $u = User::factory()->create();
    $result = app(EstateIhtExposureDetector::class)->detect($u);

    // Rule #13: no scores. Keys must be exactly these — no 'score', no 'rating'.
    // `unmodelled_relief_caveat` joined them for W-0466; it is a sentence or null.
    expect(array_keys($result))->toEqual(['exposed', 'headline', 'estimated_liability_gbp', 'unmodelled_relief_caveat']);
});

it('does not hand the residence allowance to someone with no residence', function () {
    // W-0464. This test used to assert `exposed=false` for £500,000 of savings,
    // on the reasoning that £500,000 equals the nil rate band plus the residence
    // nil rate band. **It encoded the defect.** The residence band requires a main
    // residence passing to direct descendants (IHTA 1984 s8E–s8H) and this user has
    // neither, so their allowance is £325,000 and £175,000 of the estate is taxable.
    //
    // The old detector granted the band to everyone by adding it into a threshold,
    // which is exactly the kind of second, simpler model CSJ's "/m must never work
    // anything out" rule removes.
    $ihtConfig = app(TaxConfigService::class)->getInheritanceTax();
    $nrb = (float) ($ihtConfig['nil_rate_band'] ?? 325000);
    $rnrb = (float) ($ihtConfig['residence_nil_rate_band'] ?? 175000);

    $user = User::factory()->create();
    SavingsAccount::factory()->create([
        'user_id' => $user->id,
        'current_balance' => $nrb + $rnrb,
    ]);

    $result = app(EstateIhtExposureDetector::class)->detect($user);

    $expectedRate = (float) ($ihtConfig['standard_rate'] ?? 0.40);

    expect($result['exposed'])->toBeTrue()
        ->and($result['estimated_liability_gbp'])->toEqualWithDelta($rnrb * $expectedRate, 0.01);
});

it('reports no exposure when the allowances actually cover the estate', function () {
    $ihtConfig = app(TaxConfigService::class)->getInheritanceTax();
    $nrb = (float) ($ihtConfig['nil_rate_band'] ?? 325000);

    $user = User::factory()->create();
    SavingsAccount::factory()->create([
        'user_id' => $user->id,
        'current_balance' => $nrb,
    ]);

    $result = app(EstateIhtExposureDetector::class)->detect($user);

    expect($result['exposed'])->toBeFalse()
        ->and($result['estimated_liability_gbp'])->toEqual(0.0);
});

it('returns exposed=true when net worth is one pound above NRB+RNRB threshold', function () {
    $ihtConfig = app(TaxConfigService::class)->getInheritanceTax();
    $threshold = (float) ($ihtConfig['nil_rate_band'] ?? 325000) + (float) ($ihtConfig['residence_nil_rate_band'] ?? 175000);

    $user = User::factory()->create();
    SavingsAccount::factory()->create([
        'user_id' => $user->id,
        'current_balance' => $threshold + 1.0, // just above: £500,001 (or current threshold + 1)
    ]);

    $result = app(EstateIhtExposureDetector::class)->detect($user);

    expect($result['exposed'])->toBeTrue()
        ->and($result['estimated_liability_gbp'])->toBeGreaterThan(0.0);
});

describe('W-0467 — the headline says whose estate and when', function () {
    it('calls a pooled figure the household on the second death', function () {
        // Married + sharing accepted is the exact predicate the engine pools on, so
        // `iht_liability` here covers BOTH estates against doubled allowances and
        // falls due on the SECOND death. This user's own first-death liability,
        // with spouse exemption, is £0 — which is why "your estate" was wrong.
        // Built inline, not via a shared helper: two files declaring one global
        // test helper made `./vendor/bin/pest` fatal at collection for two days
        // (fixed 1af23f8e5), and that is a cheap mistake to not repeat.
        $user = User::factory()->create(['marital_status' => 'married']);
        $spouse = User::factory()->create(['marital_status' => 'married']);
        $user->update(['spouse_id' => $spouse->id]);
        $spouse->update(['spouse_id' => $user->id]);

        // Pooled allowances are doubled, so the estate has to clear both bands
        // before a bill exists at all — otherwise this asserts on the no-liability
        // branch and proves nothing about the wording.
        SavingsAccount::factory()->create(['user_id' => $user->id, 'current_balance' => 700_000.00]);
        SavingsAccount::factory()->create(['user_id' => $spouse->id, 'current_balance' => 700_000.00]);

        $headline = app(EstateIhtExposureDetector::class)->detect($user)['headline'];

        expect($headline)->toContain('Your household')
            ->and($headline)->toContain('on the second death')
            ->and($headline)->not->toContain('Your estate could be subject to');
    });

    it('still says "your estate" to someone whose estate it actually is', function () {
        $user = User::factory()->create(['marital_status' => 'single']);
        SavingsAccount::factory()->create([
            'user_id' => $user->id,
            'current_balance' => 900_000.00,
        ]);

        $headline = app(EstateIhtExposureDetector::class)->detect($user)['headline'];

        expect($headline)->toContain('Your estate could be subject to')
            ->and($headline)->not->toContain('second death');
    });

    it('does not tell a married user with sharing off that it is their own estate', function () {
        // `compliance-lead` finding F, 2026-08-24. The "single" branch was never the
        // single branch — the predicate is "not pooled", and it caught married users
        // whose partner has no linked account, and married users with sharing off or
        // revoked. To them it said "Your estate could be subject to up to £X", the
        // exact defect W-0467 exists to fix, in the branch nobody changed.
        //
        // W-0347 makes this group GROW: sharing is now genuinely opt-in and
        // revocable, so more households land here over time.
        $user = User::factory()->create(['marital_status' => 'married']);
        $spouse = User::factory()->create(['marital_status' => 'married']);
        $user->update(['spouse_id' => $spouse->id]);
        $spouse->update(['spouse_id' => $user->id]);

        // Linked, but sharing REJECTED — the state a revoke leaves behind.
        SpousePermission::create([
            'user_id' => $user->id,
            'spouse_id' => $spouse->id,
            'status' => 'rejected',
            'requested_at' => now(),
            'responded_at' => now(),
        ]);

        SavingsAccount::factory()->create(['user_id' => $user->id, 'current_balance' => 900_000.00]);

        $headline = app(EstateIhtExposureDetector::class)->detect($user->fresh())['headline'];

        expect($headline)->toContain('Based on your own records alone')
            ->and($headline)->toContain('does not allow for anything passing to your partner')
            ->and($headline)->not->toContain('Your household')
            // The sentence that was wrong for this user.
            ->and($headline)->not->toContain('Your estate could be subject to up to');
    });

    it('makes no unhedged promise about what upgrading achieves', function () {
        // `compliance-lead` finding E: "to help reduce this" asserted an outcome
        // about a paid product on a conversion surface — the one clause with nothing
        // qualifying it was the clause asking for money.
        $user = User::factory()->create(['marital_status' => 'single']);
        SavingsAccount::factory()->create(['user_id' => $user->id, 'current_balance' => 900_000.00]);

        $headline = app(EstateIhtExposureDetector::class)->detect($user)['headline'];

        expect($headline)->not->toContain('to help reduce this')
            // Finding D: "personalised" is the word separating guidance from a
            // personal recommendation, and Fynla is not FCA-authorised.
            ->and($headline)->not->toContain('personalised')
            ->and($headline)->toContain('could use to explore');
    });
});

describe('W-0466 — the caveat reaches the only Inheritance Tax figure /m shows', function () {
    it('carries the caveat when the estate holds a business interest', function () {
        $user = User::factory()->create(['marital_status' => 'single']);
        SavingsAccount::factory()->create(['user_id' => $user->id, 'current_balance' => 900_000.00]);
        BusinessInterest::factory()->create([
            'user_id' => $user->id,
            'current_valuation' => 400_000.00,
            'bpr_eligible' => true,
            'trading_status' => 'trading',
        ]);

        $result = app(EstateIhtExposureDetector::class)->detect($user);

        // The market is spelled out: CLAUDE.md Rule 9 allows no acronym but ISA,
        // and `compliance-lead` flagged "AIM" on 2026-08-24. Asserting the ABSENCE
        // of the acronym as well as the presence of the words, so a well-meaning
        // "(AIM)" added later for recognisability fails here rather than shipping —
        // that would be a Rule 9 amendment and CSJ's alone to make.
        expect($result['unmodelled_relief_caveat'])
            ->toContain('Agricultural Property Relief')
            ->and($result['unmodelled_relief_caveat'])->toContain('Alternative Investment Market')
            ->and($result['unmodelled_relief_caveat'])->not->toContain('AIM')
            // Both directions, because the two exclusions bend the figure opposite ways.
            ->and($result['unmodelled_relief_caveat'])->toContain('higher or lower')
            // Rule 3 — a household told its figure may be materially wrong must be
            // given somewhere to go, not just informed.
            ->and($result['unmodelled_relief_caveat'])->toContain('regulated financial adviser');
    });

    it('says nothing to a household the exclusions cannot affect', function () {
        // The other half of the pair. Without this the caveat could be published
        // unconditionally and the first case would still pass — "only where it
        // applies" is the condition that needs a test, not the wording.
        $user = User::factory()->create(['marital_status' => 'single']);
        SavingsAccount::factory()->create(['user_id' => $user->id, 'current_balance' => 900_000.00]);

        expect(app(EstateIhtExposureDetector::class)->detect($user)['unmodelled_relief_caveat'])->toBeNull();
    });
});
