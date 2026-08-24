<?php

declare(strict_types=1);

use App\Models\Estate\Bequest;
use App\Models\Estate\IHTProfile;
use App\Models\Estate\Will;
use App\Models\SavingsAccount;
use App\Models\TaxConfiguration;
use App\Models\User;
use App\Services\Estate\IHTCalculationService;
use App\Services\TaxConfigService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * W-0399 — the two charitable figures answer different questions, and only one of
 * them reached a screen.
 *
 * `determineIHTRate()` separates them, on tax-compliance-reviewer's statutory
 * ruling of 2026-08-21 (quoted in full in that method):
 *
 *   charitable_deduction        the section 23(1) exemption. POOLED across the
 *                               household — every member's legacy leaves the
 *                               combined estate.
 *   charitable_rate_test_amount what Schedule 1A's 10% test compares. The
 *                               SURVIVOR's will alone, because the statute tests
 *                               one deceased person's estate. Summing both wills
 *                               would over-qualify households for the 36% rate.
 *
 * The second figure was computed and then read by **nothing** — it never entered
 * the result array, so `IHTPlanning.vue` rendered the pooled £20,000 under the
 * words "Your will leaves …" while the message beside it quoted the survivor's
 * £10,000. Two correct numbers, one false label, no explanation.
 *
 * **Nothing here changes the arithmetic.** These cases pin the existing statutory
 * behaviour so the presentation fix cannot drift from it.
 *
 * THE FIXTURES MUST MAKE THE TWO FIGURES DIFFER. On the peak_earners household
 * both spouses leave £10,000, so the pooled figure is exactly twice the rate-test
 * figure and any wrong reading that halves or doubles lands on a real number.
 * Worse, every "is it the survivor's?" question has the same answer either way.
 * The couple below leave **£30,000 and £5,000**, so the exemption (£35,000), the
 * rate test (£30,000), the other legacy (£5,000) and any half of anything are all
 * distinct.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->service = app(IHTCalculationService::class);
});

/**
 * A married couple whose survivor is unambiguous — a woman fourteen years younger
 * — each leaving a DIFFERENT charitable legacy.
 *
 * @return array{0: User, 1: User}
 */
function unequalCharitableCouple(float $survivorLegacy = 30000, float $firstToDieLegacy = 5000): array
{
    $firstToDie = User::factory()->create([
        'first_name' => 'Harold',
        'surname' => 'Bennett',
        'marital_status' => 'married',
        'date_of_birth' => '1950-02-11',
        'gender' => 'male',
    ]);
    $survivor = User::factory()->create([
        'first_name' => 'Patricia',
        'surname' => 'Bennett',
        'marital_status' => 'married',
        'date_of_birth' => '1964-09-30',
        'gender' => 'female',
        'spouse_id' => $firstToDie->id,
    ]);
    $firstToDie->update(['spouse_id' => $survivor->id]);

    // Asymmetric estates as well as asymmetric legacies — a 50/50 household makes
    // "whose estate is this?" unanswerable by any assertion.
    SavingsAccount::factory()->create([
        'user_id' => $firstToDie->id,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
        'current_balance' => 900_000,
    ]);
    SavingsAccount::factory()->create([
        'user_id' => $survivor->id,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
        'current_balance' => 400_000,
    ]);

    charitableLegacyFor($survivor, 'British Heart Foundation', $survivorLegacy);
    charitableLegacyFor($firstToDie, 'Cancer Research UK', $firstToDieLegacy);

    return [$survivor, $firstToDie];
}

function charitableLegacyFor(User $user, string $charity, float $amount): void
{
    $will = Will::create(['user_id' => $user->id, 'has_will' => true]);

    Bequest::create([
        'will_id' => $will->id,
        'user_id' => $user->id,
        'beneficiary_name' => $charity,
        'beneficiary_type' => 'charity',
        'bequest_type' => 'specific_amount',
        'specific_amount' => $amount,
        'priority_order' => 1,
    ]);
}

function calculateFor(User $user): array
{
    return app(IHTCalculationService::class)->calculate($user, $user->spouse, true);
}

describe('the pooled exemption and the Schedule 1A rate test are different figures', function () {
    it('publishes both, and they do not agree', function () {
        [$survivor] = unequalCharitableCouple();

        $c = calculateFor($survivor);

        expect((float) $c['charitable_deduction'])->toBe(35000.0)
            ->and((float) $c['charitable_rate_test_amount'])->toBe(30000.0)
            // The assertion the old payload could not support at all: the second
            // figure did not exist outside one private method.
            ->and($c['charitable_deduction'])->not->toBe($c['charitable_rate_test_amount']);
    });

    it('gives the same pair whichever partner is logged in', function () {
        // Both figures are properties of the household's second death, not of the
        // session. This is the one thing that SHOULD be identical across the two
        // accounts, and it is worth pinning because so much else in this module
        // wrongly was.
        [$survivor, $firstToDie] = unequalCharitableCouple();

        $hers = calculateFor($survivor);
        $his = calculateFor($firstToDie);

        expect((float) $his['charitable_deduction'])->toBe((float) $hers['charitable_deduction'])
            ->and((float) $his['charitable_rate_test_amount'])->toBe((float) $hers['charitable_rate_test_amount'])
            ->and((float) $his['charitable_rate_test_amount'])->toBe(30000.0);
    });

    it('moves the exemption but NOT the rate test when the first-to-die gives more', function () {
        // The probe that proves the rate test reads the survivor's will alone.
        // The first-to-die's legacy goes from £5,000 to £80,000 — sixteen times
        // larger, and larger than the survivor's — so a rate test that pooled
        // would leap to £110,000. It must not move at all.
        // Two households side by side rather than one household mutated: the
        // service caches per user, and re-seeding mid-test to clear it deadlocks
        // inside RefreshDatabase's transaction.
        [$survivorA] = unequalCharitableCouple(30000, 5000);
        [$survivorB] = unequalCharitableCouple(30000, 80000);

        $before = calculateFor($survivorA);
        $after = calculateFor($survivorB);

        expect((float) $before['charitable_deduction'])->toBe(35000.0)
            ->and((float) $after['charitable_deduction'])->toBe(110000.0)
            // The exemption moved by £75,000. The rate test did not move at all.
            ->and((float) $before['charitable_rate_test_amount'])->toBe(30000.0)
            ->and((float) $after['charitable_rate_test_amount'])->toBe(30000.0);
    });

    it('coincide for a single person', function () {
        // REGRESSION GUARD ONLY — THIS CASE CANNOT DISCRIMINATE. With one will
        // there is nothing to pool, so the two figures are equal whether the
        // distinction is implemented correctly, implemented backwards, or not
        // implemented at all. It is here to catch a single person losing their
        // figure entirely, and it proves nothing about the household behaviour
        // above (tests/CLAUDE.md §4, Collision).
        $single = User::factory()->create([
            'marital_status' => 'single',
            'spouse_id' => null,
            'date_of_birth' => '1964-09-30',
            'gender' => 'female',
        ]);
        SavingsAccount::factory()->create([
            'user_id' => $single->id,
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
            'current_balance' => 900_000,
        ]);
        charitableLegacyFor($single, 'British Heart Foundation', 30000);

        $c = app(IHTCalculationService::class)->calculate($single, null, false);

        expect((float) $c['charitable_deduction'])->toBe(30000.0)
            ->and((float) $c['charitable_rate_test_amount'])->toBe(30000.0);
    });

    it('reports a rate-test figure even when no legacy is recorded', function () {
        // The third branch of determineIHTRate() set no rate-test amount at all,
        // so the card could not tell "nothing given" from "figure unavailable".
        $single = User::factory()->create([
            'marital_status' => 'single',
            'spouse_id' => null,
            'date_of_birth' => '1964-09-30',
            'gender' => 'female',
        ]);
        SavingsAccount::factory()->create([
            'user_id' => $single->id,
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
            'current_balance' => 900_000,
        ]);

        $c = app(IHTCalculationService::class)->calculate($single, null, false);

        expect($c)->toHaveKey('charitable_rate_test_amount')
            ->and((float) $c['charitable_rate_test_amount'])->toBe(0.0);
    });
});

describe('W-0433 — the percentage is measured against the baseline', function () {
    it('reconciles with the baseline and the rate-test amount it is published beside', function () {
        // The percentage divided by the NET ESTATE while every threshold beside
        // it is 10% of the BASELINE, so the sentence invited the user to compare
        // two percentages of different things.
        //
        // Asserted as a RECONCILIATION rather than a literal, deliberately: on
        // many households the baseline is a round fraction of the net estate, so
        // a wrong denominator lands on a number that looks plausible. If
        // percent, baseline and amount reconcile, the denominator is right
        // whatever the household.
        [$survivor] = unequalCharitableCouple();

        $c = calculateFor($survivor);

        $impliedAmount = ($c['charitable_giving_percent'] / 100) * $c['charitable_baseline'];

        expect(round($impliedAmount, 2))->toBe(round((float) $c['charitable_rate_test_amount'], 2));
    });

    it('does not measure against the net estate', function () {
        [$survivor] = unequalCharitableCouple();

        $c = calculateFor($survivor);

        $againstNetEstate = ((float) $c['charitable_rate_test_amount'] / (float) $c['total_net_estate']) * 100;

        // The two denominators must give different answers here, or this case
        // cannot discriminate — asserted rather than assumed.
        expect(round($againstNetEstate, 4))->not->toBe(round((float) $c['charitable_giving_percent'], 4))
            ->and(round((float) $c['charitable_giving_percent'], 4))
            ->toBeGreaterThan(round($againstNetEstate, 4));
    });
});

describe('W-0433 / C2 — the profile branch uses the same definition', function () {
    it('measures a profile-declared intention against the baseline too', function () {
        // THE BRANCH NO FIXTURE HAS EVER REACHED. W-0433's fix lived inside the
        // recorded-bequest branch, so a user with NO will bequests but a typed
        // Inheritance Tax profile percentage kept the old definition — a
        // percentage of the NET ESTATE, quoted against a BASELINE threshold in
        // the same sentence. No seeded profile carries a non-zero value, which
        // is exactly why nothing caught it. Any user can enter one.
        $user = User::factory()->create([
            'marital_status' => 'single',
            'spouse_id' => null,
            'date_of_birth' => '1964-09-30',
            'gender' => 'female',
        ]);
        SavingsAccount::factory()->create([
            'user_id' => $user->id,
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
            'current_balance' => 1_000_000,
        ]);

        // Declared intention, no recorded bequest anywhere.
        IHTProfile::create(['user_id' => $user->id, 'charitable_giving_percent' => 5.0]);

        $c = app(IHTCalculationService::class)->calculate($user, null, false);

        expect((float) $c['charitable_rate_test_amount'])->toBeGreaterThan(0.0);

        // The published percentage reconciles with the baseline and the amount
        // beside it — the same reconciliation the recorded-bequest branch
        // satisfies. Asserted as a relationship, not a literal, so it holds
        // whatever the household.
        $implied = ($c['charitable_giving_percent'] / 100) * $c['charitable_baseline'];
        expect(round($implied, 2))->toBe(round((float) $c['charitable_rate_test_amount'], 2));

        // And it is NOT the typed 5%, which was a percentage of the net estate.
        expect(round((float) $c['charitable_giving_percent'], 4))->not->toBe(5.0);
    });
});

describe('Rule 2 (W-0431) — every rate in the message comes from configuration', function () {
    it('quotes the reduced rate that configuration actually holds, not a literal', function () {
        // The countermeasure that matters: change the real input and the answer
        // must MOVE. The sentences hardcoded "40%", "36%" and "10%" beside a
        // calculation reading TaxConfigService, so a configuration change would
        // have left the message asserting a rate the estate was not charged —
        // the W-0132 defect one layer over.
        //
        // 41%, 31% and 12% are deliberately values nothing else in the codebase
        // uses, so none can be produced by a fallback, a default, or a
        // coincidence.
        //
        // C3, from the tax-compliance verdict: this moved the reduced rate and
        // the threshold and left `standard_rate` at 0.40 — so a re-hardcoded
        // "40%" passed this test green. A guard that moves two of three inputs
        // certifies the third, and the one it certified is the rate quoted in
        // every branch of the message. All three move now.
        $config = TaxConfiguration::where('is_active', true)->firstOrFail();
        $data = $config->config_data;
        $data['inheritance_tax']['standard_rate'] = 0.41;
        $data['inheritance_tax']['reduced_rate_charity'] = 0.31;
        $data['inheritance_tax']['charity_threshold_percent'] = 0.12;
        $config->update(['config_data' => $data]);

        app()->forgetInstance(TaxConfigService::class);

        [$survivor] = unequalCharitableCouple();
        $message = calculateFor($survivor)['iht_rate_message'];

        expect($message)->toContain('41%')
            ->and($message)->toContain('31%')
            ->and($message)->toContain('12%')
            // And every literal it used to assert is gone.
            ->and($message)->not->toContain('40%')
            ->and($message)->not->toContain('36%')
            ->and($message)->not->toContain('10% threshold');
    });
});

describe('Rule 9 — the rate messages spell out Inheritance Tax', function () {
    it('never abbreviates it in the message a user reads', function () {
        [$survivor] = unequalCharitableCouple();

        $message = calculateFor($survivor)['iht_rate_message'];

        expect($message)->toContain('Inheritance Tax rate')
            // The acronym must not survive anywhere in the sentence.
            ->and($message)->not->toContain('IHT');
    });

    it('spells it out on the no-legacy message too', function () {
        $single = User::factory()->create([
            'marital_status' => 'single',
            'spouse_id' => null,
            'date_of_birth' => '1964-09-30',
            'gender' => 'female',
        ]);
        SavingsAccount::factory()->create([
            'user_id' => $single->id,
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
            'current_balance' => 900_000,
        ]);

        $message = app(IHTCalculationService::class)->calculate($single, null, false)['iht_rate_message'];

        expect($message)->toContain('Standard Inheritance Tax rate')
            ->and($message)->not->toContain('IHT');
    });
});
