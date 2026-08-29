<?php

declare(strict_types=1);

use App\Models\Estate\Gift;
use App\Models\User;
use App\Services\Estate\IHTCalculationService;
use Database\Seeders\PremiumTestPersonaSeeder;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * W-0154 — the peak_earners household's inheritance tax, figure by figure, against
 * hand-computed values from `tests/Persona/peak_earners.md`.
 *
 * This is the acceptance criterion the item was raised to record, and the only one of
 * the sixteen still open: **the internal arithmetic reconciling is not the same as the
 * figures being right.** Every expectation below is derived from the persona source and
 * written out, so a reader can check the derivation rather than trust the number.
 *
 * It caught a live regression the moment it was written. See the duplicate-settlement
 * test at the bottom.
 *
 * Derivation, all from `tests/Persona/peak_earners.md`:
 *
 *   Properties      850,000 (Willows, joint) + 425,000 (City flat, joint)
 *                 + 118,000 (Manchester, David 40% of 295,000)        = 1,393,000
 *   Savings          25,000 + 6,280 + 4,500 + 22,500 + 22,500
 *                 + 50,000 (Premium Bonds, David individual — NS&I
 *                   cannot be held jointly, per the persona file's
 *                   own 2026-08-21 note)                              =   130,780
 *   Investments      95,000 + 85,000 + 95,000 + 30,000                =   305,000
 *   Chattels         35,000 + 85,000 + 18,000 + 8,500 + 4,500 + 42,000 =  193,000
 *                                                          gross      = 2,021,780
 *   Mortgages        65,000 + 180,000 + 48,000 (40% of 120,000)       =   293,000
 *                                                          net estate = 1,728,780
 *
 *   Pensions are outside the estate (2026/27), so the £180,000 workplace pot, the
 *   £320,000 SIPP and Sarah's defined benefit scheme are all correctly absent.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(PremiumTestPersonaSeeder::class);

    $this->david = User::where('email', 'david.jones@example.com')->firstOrFail();
    $this->sarah = User::where('email', 'sarah.jones@example.com')->firstOrFail();

    $this->figures = fn (User $u): array => app(IHTCalculationService::class)
        ->calculate($u, $u->liveSpouse(), $u->hasAcceptedSpousePermission());
});

it('values the household estate as the persona file describes it', function () {
    $c = ($this->figures)($this->david);

    expect((float) $c['total_gross_assets'])->toBe(2_021_780.0)
        ->and((float) $c['total_liabilities'])->toBe(293_000.0)
        ->and((float) $c['total_net_estate'])->toBe(1_728_780.0);
});

it('gives each spouse their own share, and the two shares sum to the household', function () {
    // David holds the Manchester 40% and the whole of the Premium Bonds; Sarah holds
    // the engagement ring. Everything else is halved.
    $his = ($this->figures)($this->david);
    $hers = ($this->figures)($this->sarah);

    expect((float) $his['user_net_estate'])->toBe(989_500.0)
        ->and((float) $hers['user_net_estate'])->toBe(739_280.0)
        ->and((float) $his['user_net_estate'] + (float) $hers['user_net_estate'])
        ->toBe((float) $his['total_net_estate']);
});

it('gives one household one answer, whichever spouse is logged in', function () {
    // The defect this item was raised for: £149,712 against £89,712 from the same
    // household, because every per-person input was read from the logged-in user
    // while every asset was pooled.
    $his = ($this->figures)($this->david);
    $hers = ($this->figures)($this->sarah);

    expect((float) $his['iht_liability'])->toBe((float) $hers['iht_liability'])
        ->and((float) $his['iht_liability'])->toBe(343_512.0);
});

it('reports allowance components that add up to the totals beside them', function () {
    // 325,000 own + 325,000 modelled second-death − 150,000 consumed by the 2020
    // settlement = 500,000. A user must be able to reach the total from the parts.
    $c = ($this->figures)($this->david);

    expect((float) $c['nrb_individual'] + (float) $c['nrb_spouse_modelled'] - (float) $c['nrb_gift_deduction'])
        ->toBe((float) $c['nrb_available'])
        ->and((float) $c['nrb_available'])->toBe(500_000.0)
        ->and((float) $c['rnrb_individual'] + (float) $c['rnrb_spouse_modelled'])
        ->toBe((float) $c['rnrb_available'])
        ->and((float) $c['rnrb_available'])->toBe(350_000.0)
        ->and((float) $c['total_allowances'])->toBe(850_000.0);
});

it('works the taxable estate and the bill from those figures', function () {
    // 1,728,780 − 850,000 of allowances − 20,000 of charitable bequests (£10,000 in
    // each mirror will) = 858,780, at the standard rate.
    $c = ($this->figures)($this->david);

    expect((float) $c['taxable_estate'])->toBe(858_780.0)
        ->and((float) $c['iht_liability'])->toBe(343_512.0)
        ->and((float) $c['iht_rate_percent'])->toBe(40.0);
});

it('does not report the reduced charitable rate on this estate', function () {
    // Schedule 1A baseline is the net estate less the AVAILABLE NIL RATE BAND only —
    // the residence band is not deducted (IHTM45008). 1,728,780 − 500,000 = 1,228,780,
    // and 10% of that is 122,878. The £20,000 given does not reach it, so 40% stands.
    $c = ($this->figures)($this->david);

    expect((float) $c['iht_rate_percent'])->toBe(40.0);
});

it('records the trust settlement once, however often the household is re-seeded', function () {
    // THE REGRESSION THIS SUITE CAUGHT, found live in the development database.
    // `purgeHouseholdData()` force-deleted `Trust` and never listed `Gift`, and a
    // query-builder delete fires no model events — so `TrustObserver` never cleaned up
    // and the next `Trust::updateOrCreate()` wrote another settlement. Four runs left
    // four identical £150,000 transfers against one trust, capping the gift deduction
    // at the whole £325,000 band instead of £150,000 and overstating the household's
    // bill by £70,000.
    //
    // This guards the `gifts.trust_id` foreign key, NOT the seeder's purge list: the
    // cascade takes the gift with the trust row at the database level, which a mass
    // delete cannot bypass. Checked — it still passes with the purge-list entry
    // reverted, so do not read it as covering that line.
    $this->seed(PremiumTestPersonaSeeder::class);
    $this->seed(PremiumTestPersonaSeeder::class);

    $david = User::where('email', 'david.jones@example.com')->firstOrFail();

    expect(Gift::where('user_id', $david->id)->where('gift_type', 'clt')->count())->toBe(1)
        ->and((float) ($this->figures)($david)['nrb_gift_deduction'])->toBe(150_000.0)
        ->and((float) ($this->figures)($david)['iht_liability'])->toBe(343_512.0);
});
