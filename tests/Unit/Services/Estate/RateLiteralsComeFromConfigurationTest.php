<?php

declare(strict_types=1);

use App\Models\Estate\Bequest;
use App\Models\Estate\Will;
use App\Models\SavingsAccount;
use App\Models\TaxConfiguration;
use App\Models\User;
use App\Services\Estate\IHTCalculationService;
use App\Services\Estate\WillAnalysisService;
use App\Services\Investment\Recommendation\ContributionWaterfallService;
use App\Services\Plans\PlanConfigService;
use App\Services\TaxConfigService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

/**
 * W-0451 — every rate a user is shown, or a figure computed from one, comes from
 * `TaxConfigService` (Rule 2).
 *
 * W-0431 fixed the three rate messages in `IHTCalculationService`. The
 * tax-compliance gate on that batch then found four more, and **two of them were
 * not literals in prose at all**:
 *
 *   WillAnalysisService:74   `$potentialSaving = $baseline * 0.04` — the 40 minus
 *                            36 differential baked into ARITHMETIC. At a 31%
 *                            reduced rate the true differential is 9 points, so
 *                            the application understated the saving by more than
 *                            half, in a figure a user acts on.
 *   ContributionWaterfall    the Lifetime ISA government bonus hardcoded at 0.25
 *                            on the line AFTER the one reading its own
 *                            configuration.
 *
 * **A sweep for percentages in prose is structurally blind to a rate expressed as
 * a decimal in arithmetic** — no `%`, no string — and the arithmetic form is the
 * more damaging, because it changes a figure rather than a caption. Both passes
 * are covered here.
 *
 * EVERY RATE MOVES, NOT A SUBSET. The previous guard moved the reduced rate and
 * the threshold and left `standard_rate` at 0.40 — so a re-hardcoded "40%" passed
 * it green, and 40% is the rate quoted in every branch. **A guard that moves two
 * of three inputs silently certifies the third.** The configuration below moves
 * all four, to values nothing else in the codebase uses.
 */
const MOVED_STANDARD_RATE = 0.41;
const MOVED_REDUCED_RATE = 0.31;
const MOVED_THRESHOLD = 0.12;
const MOVED_LISA_BONUS = 0.35;

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

/**
 * Move every rate this batch touches, at once.
 */
function moveEveryRate(): void
{
    $config = TaxConfiguration::where('is_active', true)->firstOrFail();
    $data = $config->config_data;

    $data['inheritance_tax']['standard_rate'] = MOVED_STANDARD_RATE;
    $data['inheritance_tax']['reduced_rate_charity'] = MOVED_REDUCED_RATE;
    $data['inheritance_tax']['charity_threshold_percent'] = MOVED_THRESHOLD;
    $data['isa']['lifetime_isa']['government_bonus_rate'] = MOVED_LISA_BONUS;

    $config->update(['config_data' => $data]);

    app()->forgetInstance(TaxConfigService::class);
    app()->forgetInstance(PlanConfigService::class);
}

/**
 * The Lifetime ISA step's own trace entry, which is where the bonus is explained
 * to the user in pounds.
 *
 * @param  array<string, mixed>  $result
 * @return array<string, mixed>|null
 */
function lisaStepFrom(array $result): ?array
{
    foreach ($result['recommendations'] ?? [] as $rec) {
        if (($rec['step'] ?? null) === 'lisa') {
            // Every sentence this step produces, joined — the headline the user
            // reads AND the decision trace behind it. The rate was hardcoded in
            // three of them and patching two would have left a card contradicting
            // its own explanation.
            $rec['explanation'] = implode(' ', array_merge(
                [(string) ($rec['explanation'] ?? '')],
                array_map(fn ($e) => (string) ($e['explanation'] ?? ''), $rec['decision_trace'] ?? []),
            ));

            return $rec;
        }
    }

    return null;
}

function testatorLeavingToCharity(float $legacy = 30000): User
{
    $user = User::factory()->create([
        'marital_status' => 'single',
        'spouse_id' => null,
        'date_of_birth' => '1964-09-30',
        'gender' => 'female',
    ]);

    // W-0452 added the estate. These cases used to hand the service a net estate
    // as an argument; the charitable position is now settled by the real
    // Inheritance Tax calculation, so the testator needs assets to have one.
    // Cash only, so the residence nil rate band is £0 and the baseline is
    // net estate less the single nil rate band, unambiguously.
    SavingsAccount::factory()->create([
        'user_id' => $user->id,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
        'current_balance' => 1_000_000,
        'is_isa' => false,
    ]);

    $will = Will::create(['user_id' => $user->id, 'has_will' => true]);

    Bequest::create([
        'will_id' => $will->id,
        'user_id' => $user->id,
        'beneficiary_name' => 'British Heart Foundation',
        'beneficiary_type' => 'charity',
        'bequest_type' => 'specific_amount',
        'specific_amount' => $legacy,
        'priority_order' => 1,
    ]);

    return $user;
}

function charitablePositionFor(User $user): array
{
    Cache::flush();
    $calculation = app(IHTCalculationService::class)->calculate($user->fresh());

    return app(WillAnalysisService::class)->analyzeCharitableBequests($calculation);
}

describe('the arithmetic pass — a rate hidden in a calculation', function () {
    it('derives the potential saving from the configured rate differential', function () {
        // The strongest case in the set, and the one a prose sweep cannot see.
        //
        // **W-0451 MOVED WHAT THIS CASE ASSERTS, and the old expectations are
        // recorded here rather than quietly replaced.** It expected £27,000 and
        // £67,500 — the differential applied to the BASELINE (£675,000). That
        // base was itself wrong: it over-includes the charitable gift and the
        // residence nil rate band, and the sentence quoting it printed two bills
        // struck on the chargeable estate, so the published saving and the
        // published working differed by 43%.
        //
        // The saving is now the difference between two Inheritance Tax bills, so
        // this case asserts the differential is READ rather than baked, on the
        // figures the calculation publishes. Both rates move — at 40/36 the
        // differential is exactly 0.04, so a re-hardcoded `* 0.04` is
        // byte-identical to a correct reading and only a moved configuration can
        // tell them apart.
        $user = testatorLeavingToCharity(1000);

        $before = charitablePositionFor($user);
        expect((float) $before['potential_saving'])->toEqualWithDelta(
            (float) $before['taxable_estate'] * 0.40 - (float) $before['taxable_estate_if_qualifying'] * 0.36,
            0.01,
        );

        moveEveryRate();

        $after = charitablePositionFor($user);

        // The assertion that matters: the answer MOVED when the real input moved,
        // and it moved to the figure the MOVED rates give — not merely to
        // something different.
        expect((float) $after['potential_saving'])->toEqualWithDelta(
            (float) $after['taxable_estate'] * MOVED_STANDARD_RATE
                - (float) $after['taxable_estate_if_qualifying'] * MOVED_REDUCED_RATE,
            0.01,
        )->and(abs((float) $after['potential_saving'] - (float) $before['potential_saving']))
            ->toBeGreaterThan(1000.0);
    });

    it('derives the Lifetime ISA government bonus from configuration', function () {
        // The second arithmetic instance: `:152` already read the Lifetime ISA
        // configuration and `:155` then hardcoded 0.25, so the allowance moved
        // with configuration and the bonus did not.
        //
        // This drives the REAL service. An earlier draft asserted only that the
        // config key moved, which proves the key is readable and says nothing
        // about whether the waterfall reads it — a test named after a service it
        // never calls (tests/CLAUDE.md §4, Decoy). ContributionWaterfallService
        // had no coverage at all before this case.
        $context = ['personal' => ['age' => 30]];
        $goalModifiers = ['has_house_purchase_goal' => true];

        $before = app(ContributionWaterfallService::class)
            ->allocate($context, 10_000.0, [], $goalModifiers, []);
        $beforeLisa = lisaStepFrom($before);

        // £4,000 allowance at the seeded 25% bonus = £1,000.
        expect($beforeLisa)->not->toBeNull()
            ->and($beforeLisa['explanation'])->toContain('25%')
            ->and($beforeLisa['explanation'])->toContain('£1,000');

        moveEveryRate();

        $after = app(ContributionWaterfallService::class)
            ->allocate($context, 10_000.0, [], $goalModifiers, []);
        $afterLisa = lisaStepFrom($after);

        // Same £4,000 allowance at 35% = £1,400. Neither the rate nor the bonus
        // can be reached from the other by rounding.
        expect($afterLisa['explanation'])->toContain('35%')
            ->and($afterLisa['explanation'])->toContain('£1,400')
            ->and($afterLisa['explanation'])->not->toContain('25%');
    });
});

describe('the prose pass — a rate quoted in a sentence', function () {
    it('quotes the moved threshold and reduced rate in the charitable status message', function () {
        // WillAnalysisService:351-353 hardcoded "10%" three hundred lines from
        // `:55`, which computed it from configuration. Same quantity, two
        // readings, one class.
        $user = testatorLeavingToCharity(1000);

        moveEveryRate();

        $message = charitablePositionFor($user)['message'];

        expect($message)->toContain('12% threshold')
            ->and($message)->toContain('31%')
            // Every literal it used to assert is gone.
            ->and($message)->not->toContain('10% threshold')
            ->and($message)->not->toContain('36%');
    });

    it('spells out Inheritance Tax in the charitable status message (Rule 9)', function () {
        $user = testatorLeavingToCharity(1000);

        $message = charitablePositionFor($user)['message'];

        expect($message)->toContain('Inheritance Tax')
            ->and($message)->not->toContain('IHT');
    });
});

describe('the const pass — a rate in a declaration that cannot interpolate', function () {
    it('follows configuration in the unvalued-gifts message', function () {
        // C4 of the 2026-08-23 verdict, and a THIRD structural blind spot.
        // `UNVALUED_CHARITABLE_GIFTS_MESSAGE` was a `const` hardcoding "the 10%
        // needed" — the same quantity, in the same `message` key, in the same
        // return array as the three sentences fixed beside it. Under a 12%
        // configuration one branch said 10% and three said 12%.
        //
        // A sweep reading `const` declarations sees a fixed string and moves on,
        // because a fixed string is what a constant is FOR. **The moment a
        // sentence needs a configured value it stops being a constant**,
        // whatever the language lets you declare.
        expect(app(WillAnalysisService::class)->unvaluedCharitableGiftsMessage())
            ->toContain('10% needed');

        moveEveryRate();

        $message = app(WillAnalysisService::class)->unvaluedCharitableGiftsMessage();

        expect($message)->toContain('12% needed')
            ->and($message)->not->toContain('10% needed')
            // Rule 9 holds in this sentence too.
            ->and($message)->toContain('Inheritance Tax')
            ->and($message)->not->toContain('IHT ');
    });
});

describe('one home for the Schedule 1A threshold', function () {
    it('serves the plans surface the same threshold the calculation uses', function () {
        // `PlanConfigService` kept a SECOND configuration home for a statutory
        // value, so an admin could move one and change what /plans/estate
        // displays while the calculation used the other.
        expect(app(PlanConfigService::class)->getCharitableGivingThreshold())->toBe(10.0);

        moveEveryRate();

        expect(app(PlanConfigService::class)->getCharitableGivingThreshold())
            ->toBe(MOVED_THRESHOLD * 100)
            // And it now tracks the tax configuration exactly, which is the
            // point: one statutory figure, one home.
            ->and(app(PlanConfigService::class)->getCharitableGivingThreshold())
            ->toBe(app(TaxConfigService::class)->getCharitableThresholdPercent() * 100);
    });
});
