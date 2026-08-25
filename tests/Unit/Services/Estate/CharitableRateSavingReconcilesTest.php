<?php

declare(strict_types=1);

use App\Agents\EstateAgent;
use App\Models\Estate\Bequest;
use App\Models\Estate\Will;
use App\Models\FamilyMember;
use App\Models\Property;
use App\Models\SavingsAccount;
use App\Models\TaxConfiguration;
use App\Models\User;
use App\Services\Estate\IHTCalculationService;
use App\Services\Plans\EstatePlanService;
use App\Services\TaxConfigService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

/**
 * W-0451 / W-0452 — the charitable rate saving, and the percentage denominator.
 *
 * TWO DEFECTS, ONE DISEASE: three mechanisms answering one question about one
 * household, on three different estates.
 *
 * **W-0451.** A decision trace whose stated purpose is auditability rendered:
 *
 *   "On the taxable estate of £858,780: at 40% = £343,512, at 36% = £309,161
 *    — saving £19,580."
 *
 * £343,512 − £309,161 = £34,351. A £14,771 error — 43% — checkable on a
 * calculator. `EstateAgent` struck both bills on the household chargeable
 * estate; `WillAnalysisService` published the saving as the rate differential on
 * the BASELINE, which over-includes by exactly (charitable gift + residence nil
 * rate band); and the saving from the action the sentence proposes was a third
 * figure that existed nowhere.
 *
 * **W-0452.** `/plans/estate` showed "Current Charitable Rate 4.2%" on a page
 * whose own Net Estate row read £1,728,780, while `/estate` showed 0.8% for the
 * same household in the same session. `EstateAgent` handed `WillAnalysisService`
 * the INDIVIDUAL's net estate with the HOUSEHOLD's available nil rate band, and
 * totalled the LOGGED-IN user's own will rather than the survivor's.
 *
 * **THE AXIS EACH CASE VARIES, NAMED.** An asymmetric fixture is only asymmetric
 * along the axis you varied (`tests/CLAUDE.md` §4). Three axes matter here and
 * the fixture varies all three:
 *
 *   whose will      — the survivor leaves £31,750, the first-to-die £4,930
 *   whose estate    — Patricia holds £892,000, Harold £903,000, household £1,795,000
 *   whose session   — every published-figure case is read from BOTH accounts
 *
 * The predecessor fixture varied the legacies and always read from the
 * survivor's session, so it could not express a defect about *whose* will is
 * read. That is precisely how W-0433's "one definition read by both surfaces"
 * came to be ticked while unmet.
 *
 * **THE THREE CANDIDATE ANSWERS ARE DISTINCT ON THIS HOUSEHOLD**, and none is a
 * round multiple of another — checked in `it('discriminates …')` below, which is
 * the case that stops every other case in the file from proving nothing:
 *
 *   differential × baseline           £45,800.00   (what was published)
 *   differential × chargeable estate  £30,332.80   (the printed working)
 *   the actual tax reduction          £60,122.80   (what the sentence promises)
 *
 * **THE GAP THE ACCEPTANCE ASKED FOR.** baseline £1,145,000 − chargeable estate
 * £758,320 = **£386,680 = £350,000 residence band + £36,680 charitable gift**,
 * exactly the reviewer's identity, and not a round number. On the persona
 * household the gap was the residence band plus the legacy with the legacy at a
 * round £10,000, which is guessable; £36,680 is not.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->taxConfig = app(TaxConfigService::class);
});

function charitableGift(User $user, string $charity, float $amount, string $type = 'specific_amount'): void
{
    $will = Will::firstOrCreate(['user_id' => $user->id], ['has_will' => true]);

    Bequest::create([
        'will_id' => $will->id,
        'user_id' => $user->id,
        'beneficiary_name' => $charity,
        'beneficiary_type' => 'charity',
        'bequest_type' => $type,
        'specific_amount' => $type === 'specific_amount' ? $amount : null,
        'specific_asset_description' => $type === 'specific_asset' ? 'An oil painting' : null,
        'priority_order' => 1,
    ]);
}

/**
 * The Bennetts. Married, a survivor who is unambiguous (a woman fourteen years
 * younger), asymmetric estates, asymmetric legacies, a main residence and a
 * child — so the residence nil rate band is live and the gap between the
 * Schedule 1A baseline and the chargeable estate contains BOTH of the two things
 * the baseline over-includes.
 *
 * @return array{0: User, 1: User} [survivor, first-to-die]
 */
function bennetts(float $survivorLegacy = 31750, float $firstToDieLegacy = 4930): array
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

    // Asymmetric, and asymmetric the RIGHT way round: the survivor holds LESS
    // than the first-to-die. A denominator taken from the logged-in user is then
    // wrong by a different amount from each account, so no reading lands on the
    // household figure by accident.
    SavingsAccount::factory()->create([
        'user_id' => $firstToDie->id,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
        'current_balance' => 903_000,
    ]);
    SavingsAccount::factory()->create([
        'user_id' => $survivor->id,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
        'current_balance' => 412_000,
    ]);
    Property::factory()->create([
        'user_id' => $survivor->id,
        'property_type' => 'main_residence',
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
        'current_value' => 480_000,
    ]);
    FamilyMember::factory()->create([
        'user_id' => $survivor->id,
        'relationship' => 'child',
        'date_of_birth' => '1992-04-04',
    ]);

    charitableGift($survivor, 'British Heart Foundation', $survivorLegacy);
    charitableGift($firstToDie, 'Cancer Research UK', $firstToDieLegacy);

    return [$survivor, $firstToDie];
}

function ihtFor(User $user): array
{
    return app(IHTCalculationService::class)->calculate($user, $user->spouse, true);
}

/**
 * The whole charitable recommendation as the agent produces it, for the session
 * of the user passed in.
 *
 * @return array<string, mixed>
 */
function charitableRecommendation(User $user): array
{
    Cache::flush();
    $agent = app(EstateAgent::class);
    $recommendations = $agent->generateRecommendations($agent->analyze($user->id));

    $charitable = collect($recommendations['data']['recommendations'] ?? [])
        ->firstWhere('category', 'charitable_bequest');

    // A household that produces no charitable recommendation would make every
    // case below pass vacuously against an empty string.
    expect($charitable)->not->toBeNull();

    return $charitable;
}

/**
 * The charitable step's saving sentence, exactly as a user reads it.
 */
function charitableSavingSentence(User $user): string
{
    return (string) collect(charitableRecommendation($user)['decision_trace'])->last()['explanation'];
}

/**
 * Every pound figure a sentence prints, in the order it prints them.
 *
 * @return list<float>
 */
function poundsIn(string $sentence): array
{
    preg_match_all('/£([\d,]+)/', $sentence, $matches);

    return array_map(fn (string $n): float => (float) str_replace(',', '', $n), $matches[1]);
}

function planCharitableGiving(User $user): array
{
    Cache::flush();

    return app(EstatePlanService::class)->generatePlan($user->id)['current_situation']['charitable_giving'];
}

describe('the three candidate answers, and whether this fixture can tell them apart', function () {
    it('discriminates — the three candidates are distinct and none is a round multiple of another', function () {
        // THE CASE THAT MAKES THE OTHER CASES MEAN SOMETHING. Every assertion
        // below about "the saving is X" proves nothing unless the wrong answers
        // are different numbers on this household. On the persona household the
        // gap between the baseline and the chargeable estate was the residence
        // band plus a round £10,000 legacy — guessable, and a wrong reading can
        // land on a right-looking figure.
        [$survivor] = bennetts();
        $c = ihtFor($survivor);

        $differential = (float) $this->taxConfig->getInheritanceTax()['standard_rate']
            - $this->taxConfig->getCharitableReducedRate();

        $onBaseline = (float) $c['charitable_baseline'] * $differential;
        $onChargeable = (float) $c['taxable_estate'] * $differential;
        $actual = (float) $c['charitable_rate_saving'];

        // A penny of tolerance, and no more. Every figure here is a product of
        // two floats, so binary representation moves the last digit; the
        // hypotheses this file exists to part are £15,467 and £29,790 away from
        // each other, six orders of magnitude above the window.
        expect($onBaseline)->toEqualWithDelta(45800.0, 0.01)
            ->and($onChargeable)->toEqualWithDelta(30332.8, 0.01)
            ->and($actual)->toEqualWithDelta(60122.8, 0.01);

        // Pairwise distinct, and pairwise far apart — the smallest gap between
        // any two is £15,467.20, three orders of magnitude above the £1 that
        // rounding to whole pounds can introduce.
        expect(abs($actual - $onBaseline))->toBeGreaterThan(1000.0)
            ->and(abs($actual - $onChargeable))->toBeGreaterThan(1000.0)
            ->and(abs($onBaseline - $onChargeable))->toBeGreaterThan(1000.0);

        // And the gap the acceptance named: the baseline over-includes by
        // exactly the residence nil rate band plus the charitable gift.
        expect((float) $c['charitable_baseline'] - (float) $c['taxable_estate'])
            ->toBe((float) $c['rnrb_available'] + (float) $c['charitable_deduction'])
            ->toBe(386680.0);
    });
});

describe('W-0451 — the sentence and the saving it claims', function () {
    it('prints two Inheritance Tax bills that subtract to the saving it publishes', function () {
        // THE PROPERTY A READER CHECKS, asserted the way a reader checks it:
        // parsed out of the rendered sentence, not read from the array that
        // produced it. Reading the array would pass against a sentence that
        // printed something else entirely — which is exactly what happened.
        [$survivor] = bennetts();

        $sentence = charitableSavingSentence($survivor);
        $pounds = poundsIn($sentence);

        // shortfall, threshold, shortfall again, chargeable estate, bill at the
        // standard rate, chargeable estate after the gift, bill at the reduced
        // rate, saving.
        expect($pounds)->toHaveCount(8);

        [, , , $chargeable, $billAtStandard, $chargeableAfterGift, $billAtReduced, $saving] = $pounds;

        // Each bill is its own printed rate applied to its own printed base.
        preg_match_all('/(\d+)% of/', $sentence, $rates);
        [$standardPercent, $reducedPercent] = array_map('floatval', $rates[1]);

        expect($billAtStandard)->toBe(round($chargeable * $standardPercent / 100))
            ->and($billAtReduced)->toBe(round($chargeableAfterGift * $reducedPercent / 100));

        // And the difference of the two printed bills IS the printed saving.
        // Tolerance is £1 and no more: `number_format` to whole pounds can move
        // each figure by at most 50p. The error this case exists to catch was
        // £14,771 — the two hypotheses are 14,771 times apart, so a £1 window
        // cannot span both. On this household the difference is exactly zero.
        expect(abs(($billAtStandard - $billAtReduced) - $saving))->toBeLessThanOrEqual(1.0);
    });

    it('measures the saving on the chargeable estate, not on the Schedule 1A baseline', function () {
        // THE CASE THE RECONCILIATION ABOVE CANNOT PROVE — measured, not asserted.
        //
        // Moving the WHOLE sentence onto the baseline — both bills and the bases
        // each is printed against — leaves the reconciliation green, because a
        // wrong base applied consistently still subtracts. Verified: that
        // mutation reddened only the literal-valued canary at the top of this
        // file until the structural assertion at the end of this case was added.
        //
        // The reconciliation catches a saving computed ELSEWHERE (the original
        // defect); only this case catches a saving computed on the wrong estate.
        [$survivor] = bennetts();
        $c = ihtFor($survivor);

        $standardRate = (float) $c['iht_rate'];
        $reducedRate = $this->taxConfig->getCharitableReducedRate();

        expect((float) $c['charitable_tax_at_standard_rate'])
            ->toEqualWithDelta((float) $c['taxable_estate'] * $standardRate, 0.01)
            ->and((float) $c['charitable_tax_at_reduced_rate'])
            ->toEqualWithDelta((float) $c['charitable_taxable_estate_if_qualifying'] * $reducedRate, 0.01);

        // The gift itself leaves the estate under the section 23(1) exemption,
        // so the second bill is struck on an estate smaller by the shortfall.
        // A sentence printing ONE base for two bills could not be made to add up.
        expect((float) $c['charitable_taxable_estate_if_qualifying'])
            ->toBe((float) $c['taxable_estate'] - (float) $c['charitable_shortfall']);

        // AND THE BASE IS THE CHARGEABLE ESTATE, structurally — not merely
        // whatever `taxable_estate` happens to hold. Swapping the whole sentence
        // onto the baseline (bills AND the bases they are printed against) is
        // internally consistent and survived every assertion above, because both
        // sides of each moved together. This is the one that parts them: the
        // chargeable estate is the net estate less the allowances less the
        // exemption, and the baseline is not.
        expect((float) $c['taxable_estate'])->toEqualWithDelta(
            max(0, (float) $c['total_net_estate'] - (float) $c['total_allowances'] - (float) $c['charitable_deduction']),
            0.01,
        )->and((float) $c['taxable_estate'])->not->toBe((float) $c['charitable_baseline']);
    });

    it('publishes one saving, and the plan surface and the decision trace both quote it', function () {
        // Rule 20. Two surfaces, one figure, and the figure is the calculation's.
        [$survivor] = bennetts();
        $c = ihtFor($survivor);

        $pounds = poundsIn(charitableSavingSentence($survivor));
        $savingInTheSentence = end($pounds);
        $fromPlan = (float) planCharitableGiving($survivor)['potential_saving'];

        expect($fromPlan)->toBe((float) $c['charitable_rate_saving'])
            ->and($savingInTheSentence)->toBe(round((float) $c['charitable_rate_saving']));
    });

    it('leaves an already-qualifying estate the rate differential on its own chargeable estate', function () {
        // The other branch of the one formula. No shortfall, so both bills sit
        // on the same chargeable estate and the difference collapses to the
        // differential — which is what "the reduced rate is worth" means for
        // someone who already has it. It reconciles for the same structural
        // reason, not by a second rule.
        [$survivor] = bennetts(survivorLegacy: 250_000);
        $c = ihtFor($survivor);

        $differential = (float) $this->taxConfig->getInheritanceTax()['standard_rate']
            - $this->taxConfig->getCharitableReducedRate();

        expect($c['charitable_rate_qualifies'])->toBeTrue()
            ->and((float) $c['charitable_shortfall'])->toBe(0.0)
            ->and((float) $c['charitable_rate_saving'])
            ->toEqualWithDelta((float) $c['taxable_estate'] * $differential, 0.01);

        $pounds = poundsIn(charitableSavingSentence($survivor));
        [$given, $chargeable, $billAtStandard, $billAtReduced, $saving] = $pounds;

        expect($given)->toBe(250000.0)
            ->and($chargeable)->toBe(round((float) $c['taxable_estate']))
            ->and(abs(($billAtStandard - $billAtReduced) - $saving))->toBeLessThanOrEqual(1.0);
    });

    it('moves the saving when the configured rates move', function () {
        // Rule 2 through the real path. At the seeded 40/36 the differential is
        // exactly 0.04, so a re-hardcoded `* 0.04` is byte-identical to a correct
        // reading — the configuration must move for the assertion to mean
        // anything, and both rates must move, not one.
        [$survivor] = bennetts();
        $before = (float) ihtFor($survivor)['charitable_rate_saving'];

        // Moved here rather than borrowed from the sibling Rule 2 guard: a
        // top-level constant in another Pest file is only defined once that file
        // has been loaded, so borrowing it makes this case's meaning depend on
        // suite order.
        $config = TaxConfiguration::where('is_active', true)->firstOrFail();
        $data = $config->config_data;
        $data['inheritance_tax']['standard_rate'] = 0.41;
        $data['inheritance_tax']['reduced_rate_charity'] = 0.31;
        $config->update(['config_data' => $data]);
        app()->forgetInstance(TaxConfigService::class);
        Cache::flush();

        $after = ihtFor($survivor->fresh());

        // BOTH rates move, to values nothing else uses. Moving one certifies the
        // other: at 40/36 the differential is exactly 0.04, so a re-hardcoded
        // `* 0.04` is byte-identical to a correct reading.
        expect((float) $after['iht_rate'])->toBe(0.41)
            ->and((float) $after['charitable_rate_saving'])
            ->toEqualWithDelta(
                (float) $after['taxable_estate'] * 0.41
                - (float) $after['charitable_taxable_estate_if_qualifying'] * 0.31,
                0.01
            );

        // And it MOVED. `* 0.04` would have produced the same figure twice.
        expect(abs((float) $after['charitable_rate_saving'] - $before))->toBeGreaterThan(1000.0);
    });
});

describe('W-0452 — one charitable percentage, from one division', function () {
    it('gives the same percentage from either spouse\'s session', function () {
        // THE AXIS. The predecessor fixture varied the legacies and always read
        // from the survivor's session, so it could not express a defect about
        // whose will is read. Harold is NOT the survivor and his legacy is not
        // hers, so a percentage taken from the logged-in user reads 0.4% from his
        // account and 2.8% from hers.
        [$survivor, $firstToDie] = bennetts();

        $hers = planCharitableGiving($survivor);
        $his = planCharitableGiving($firstToDie);

        expect($his['current_percentage'])->toBe($hers['current_percentage'])
            ->and($his['shortfall'])->toBe($hers['shortfall'])
            ->and($his['potential_saving'])->toBe($hers['potential_saving'])
            ->and($his['status'])->toBe($hers['status']);
    });

    it('takes the numerator from the survivor\'s will and the denominator from the household', function () {
        [$survivor] = bennetts();
        $c = ihtFor($survivor);

        // Numerator: the survivor's £31,750, not the pooled £36,680 and not
        // Harold's £4,930. Denominator: the household baseline.
        expect((float) $c['charitable_rate_test_amount'])->toBe(31750.0)
            ->and((float) $c['charitable_deduction'])->toBe(36680.0)
            ->and((float) $c['charitable_baseline'])
            ->toBe((float) $c['total_net_estate'] - (float) $c['nrb_available']);

        // Asserted as a reconciliation rather than as a literal, because on many
        // households the baseline is a round fraction of the net estate and a
        // wrong denominator lands on a plausible number.
        expect((float) $c['charitable_giving_percent'] / 100 * (float) $c['charitable_baseline'])
            ->toBe((float) $c['charitable_rate_test_amount']);

        // And the wrong denominators are different numbers here, from either
        // account — so the reconciliation above is discriminating, not decorative.
        $individualDenominators = [
            (float) $c['user_net_estate'] - (float) $c['nrb_available'],
            (float) $c['spouse_net_estate'] - (float) $c['nrb_available'],
        ];
        foreach ($individualDenominators as $wrong) {
            expect($wrong)->not->toBe((float) $c['charitable_baseline']);
            expect((float) $c['charitable_rate_test_amount'] / $wrong * 100)
                ->not->toBe((float) $c['charitable_giving_percent']);
        }
    });

    it('names the survivor in the sentence and the action, from the FIRST-TO-DIE\'s session', function () {
        /*
         * W-0451 C1 — THE CASE THAT WOULD HAVE CAUGHT WHAT THIS BATCH INTRODUCED.
         *
         * Moving the numerator to the survivor's will without moving the label
         * left every sentence naming whoever was logged in. Read from the
         * first-to-die's account, the application reported the SURVIVOR's
         * charitable position under the READER's name and instructed the reader
         * to add a legacy to their OWN will.
         *
         * **That instruction cannot produce the outcome it promises.** A legacy
         * in the first-to-die's will raises the pooled section 23(1) exemption
         * and leaves `$rateTestAmount` untouched, so the rate stays standard, the
         * estate is smaller by the whole gift, and the identical instruction is
         * issued again on the next run.
         *
         * **The sibling case below asserts the FIGURES agree from both sessions —
         * correct, they are household figures — and asserts nothing about the
         * name attached to them.** That is why it could not see this. The axis
         * here is not "does the number move", it is "whose name is on it", and
         * only the first-to-die's session can express it.
         */
        [$survivor, $firstToDie] = bennetts();

        $recommendation = charitableRecommendation($firstToDie);
        $sentence = (string) collect($recommendation['decision_trace'])->last()['explanation'];
        $action = implode(' | ', $recommendation['actions']);

        // The survivor is named. The reader is not — this is not their will.
        expect($sentence)->toContain('Patricia')
            ->and($sentence)->not->toContain('Harold')
            ->and($action)->toContain("Patricia's will")
            ->and($action)->not->toContain("Harold's will")
            ->and($recommendation['description'])->toContain('Patricia');

        // And the reader is told WHY they are looking at someone else's will.
        expect($sentence)->toContain('second death');

        // From the survivor's own session the note is absent — a disclosure that
        // always fires is one the reader learns to skip — and the name is hers,
        // which is also the reader's, so the sentence reads naturally either way.
        $hers = charitableRecommendation($survivor);
        $herSentence = (string) collect($hers['decision_trace'])->last()['explanation'];

        expect($herSentence)->toContain('Patricia')
            ->and($herSentence)->not->toContain('second death');
    });

    it('publishes the percentage the plan renders — one division, both surfaces', function () {
        // W-0433's acceptance said "one definition read by both surfaces" and was
        // met on the denominator only. `EstatePlanService` kept its own division,
        // over a baseline `EstateAgent` had struck on the individual's net estate
        // — matching denominators in name and not in value. That division is gone.
        [$survivor, $firstToDie] = bennetts();
        $c = ihtFor($survivor);

        foreach ([$survivor, $firstToDie] as $account) {
            expect(planCharitableGiving($account)['current_percentage'])
                ->toBe(round((float) $c['charitable_giving_percent'], 1));
        }
    });

    it('does not move the baseline when the gift moves — Schedule 1A adds the donated amount back', function () {
        // The statutory property that makes one subtraction the whole answer. If
        // the threshold moved with the gift, "give another £X to reach £T" would
        // be chasing a target that recedes, and no single saving figure could be
        // right.
        [$small] = bennetts();
        $smallBaseline = (float) ihtFor($small)['charitable_baseline'];

        [$large] = bennetts(survivorLegacy: 250_000);
        $largeCalc = ihtFor($large);

        expect((float) $largeCalc['charitable_baseline'])->toBe($smallBaseline)
            ->and((float) $largeCalc['charitable_deduction'])->not->toBe(36680.0);
    });

    it('reads the unvalued-gift question of the survivor, not of whoever is logged in', function () {
        // A gift of an asset or a residuary share qualifies in law but carries no
        // figure, so the application goes quiet rather than naming a shortfall
        // that assumes it is worth nothing. WHOSE will is asked was the logged-in
        // user's; it is the will the rate test runs on.
        // READ FROM BOTH SESSIONS, and the first-to-die's is the one that
        // matters. A first draft of this case read only from the survivor's
        // account and a mutation reading the logged-in user's will SURVIVED it
        // — because from her session the logged-in user IS the survivor. That is
        // the exact blindness the reviewer named on W-0433, reproduced here by
        // the author who had just written it up. Found by mutation testing, not
        // by reading the file.
        [$survivor, $firstToDie] = bennetts();

        charitableGift($firstToDie, 'Oxfam', 0, 'specific_asset');
        Cache::flush();
        expect(ihtFor($survivor->fresh())['charitable_has_unvalued_gifts'])->toBeFalse();
        Cache::flush();
        expect(ihtFor($firstToDie->fresh())['charitable_has_unvalued_gifts'])->toBeFalse();

        charitableGift($survivor, 'Shelter', 0, 'specific_asset');
        Cache::flush();
        expect(ihtFor($survivor->fresh())['charitable_has_unvalued_gifts'])->toBeTrue();
        Cache::flush();
        expect(ihtFor($firstToDie->fresh())['charitable_has_unvalued_gifts'])->toBeTrue();
    });
});

describe('the position is published whole, on every branch', function () {
    it('answers every charitable question on the no-legacy branch too', function () {
        // The third branch of determineIHTRate() used to be the one that forgot a
        // key — a rate-test figure computed and published by two branches out of
        // three, which is how a statutory distinction came to reach no screen.
        // The branches now share one array, so a key cannot be added to two of
        // them.
        $user = User::factory()->create([
            'marital_status' => 'single',
            'date_of_birth' => '1960-03-03',
            'gender' => 'male',
        ]);
        SavingsAccount::factory()->create([
            'user_id' => $user->id,
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
            'current_balance' => 800_000,
        ]);

        $c = ihtFor($user);

        foreach ([
            'charitable_giving_percent', 'charitable_rate_test_amount', 'charitable_baseline',
            'charitable_threshold', 'charitable_shortfall', 'charitable_rate_qualifies',
            'charitable_has_unvalued_gifts', 'charitable_taxable_estate_if_qualifying',
            'charitable_tax_at_standard_rate', 'charitable_tax_at_reduced_rate',
            'charitable_rate_saving',
        ] as $key) {
            expect($c)->toHaveKey($key);
        }

        expect((float) $c['charitable_rate_test_amount'])->toBe(0.0)
            ->and($c['charitable_rate_qualifies'])->toBeFalse()
            ->and((float) $c['charitable_shortfall'])->toBe((float) $c['charitable_threshold']);
    });
});
