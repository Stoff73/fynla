<?php

declare(strict_types=1);

use App\Http\Requests\Estate\StoreBequestRequest;
use App\Http\Requests\Estate\UpdateBequestRequest;
use App\Models\Estate\Bequest;
use App\Models\Estate\IHTProfile;
use App\Models\Estate\Will;
use App\Models\SavingsAccount;
use App\Models\TaxConfiguration;
use App\Models\User;
use App\Services\Estate\IHTCalculationService;
use App\Services\Estate\WillAnalysisService;
use App\Services\TaxConfigService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * W-0020 — charitable cash legacies and the reduced Inheritance Tax rate.
 *
 * getCharitableBequestTotal() compared bequest_type against 'specific', a value
 * the enum has never been able to hold (it is 'specific_amount'). The branch was
 * dead, so a charitable CASH legacy contributed £0 and could never move the rate
 * from 40% to 36% — while a percentage legacy could. That is backwards: most
 * charitable legacies are written as a cash sum.
 *
 * Tax figures below are read from TaxConfigService (Rule 2) — the nil rate
 * band, the 10% component threshold and both rates. Nothing encodes them as a
 * literal, and the reduced-rate test proves the code reads configuration
 * rather than falling back to a constant.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->service = app(WillAnalysisService::class);
    $this->iht = app(TaxConfigService::class)->getInheritanceTax();
});

function charitableBequest(User $user, string $type, array $attributes = []): Bequest
{
    $will = Will::firstOrCreate(['user_id' => $user->id], ['has_will' => true]);

    return Bequest::create(array_merge([
        'will_id' => $will->id,
        'user_id' => $user->id,
        'beneficiary_name' => 'Cancer Research UK',
        'beneficiary_type' => 'charity',
        'bequest_type' => $type,
        'priority_order' => 1,
    ], $attributes));
}

describe('getCharitableBequestTotal', function () {
    it('counts a charitable cash legacy stored as specific_amount', function () {
        $user = User::factory()->create();
        charitableBequest($user, 'specific_amount', ['specific_amount' => 10000]);

        expect($this->service->getCharitableBequestTotal($user, 1_000_000))->toBe(10000.0);
    });

    it('still counts a percentage legacy', function () {
        $user = User::factory()->create();
        charitableBequest($user, 'percentage', ['percentage_of_estate' => 10]);

        expect($this->service->getCharitableBequestTotal($user, 500_000))->toBe(50000.0);
    });

    it('ignores a cash legacy to an individual', function () {
        $user = User::factory()->create();
        $will = Will::firstOrCreate(['user_id' => $user->id], ['has_will' => true]);
        Bequest::create([
            'will_id' => $will->id,
            'user_id' => $user->id,
            'beneficiary_name' => 'William Jones',
            'beneficiary_type' => 'individual',
            'bequest_type' => 'specific_amount',
            'specific_amount' => 10000,
            'priority_order' => 1,
        ]);

        expect($this->service->getCharitableBequestTotal($user, 1_000_000))->toBe(0.0);
    });

    it('excludes an asset gift deliberately, because it carries no value to total', function () {
        $user = User::factory()->create();
        charitableBequest($user, 'specific_asset', ['specific_asset_description' => 'My art collection']);

        expect($this->service->getCharitableBequestTotal($user, 1_000_000))->toBe(0.0);
    });
});

/**
 * A single person whose whole estate is cash, so the residence nil rate band is
 * £0 and the baseline is unambiguous: net estate less the single nil rate band.
 */
function cashOnlyTestator(float $balance = 1_000_000): User
{
    $user = User::factory()->create([
        'marital_status' => 'single',
        'date_of_birth' => '1960-06-01',
        'gender' => 'female',
    ]);

    SavingsAccount::factory()->create([
        'user_id' => $user->id,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
        'current_balance' => $balance,
        'is_isa' => false,
    ]);

    return $user;
}

function charitableAnalysisFor(User $user, ?User $spouse = null): array
{
    $calculation = app(IHTCalculationService::class)->calculate($user->fresh(), $spouse?->fresh(), $spouse !== null);

    return app(WillAnalysisService::class)->analyzeCharitableBequests($calculation);
}

describe('the reduced rate for charitable legacies', function () {
    /*
     * W-0452 CHANGED HOW THESE CASES REACH THE SERVICE, and it is worth saying
     * why rather than leaving the next reader to infer it from a signature.
     *
     * They used to hand `analyzeCharitableBequests()` a net estate and a nil rate
     * band directly, and it struck its own baseline from them. That was the
     * defect: the caller in production passed the INDIVIDUAL's net estate with
     * the HOUSEHOLD's available band, and no test could see it, because every
     * test supplied both numbers itself. **A test that supplies the input cannot
     * catch a caller supplying the wrong one** (`tests/CLAUDE.md` §4, Mock).
     *
     * The position is now settled once in `IHTCalculationService` and expressed
     * here, so these cases drive the real calculation and read what it published.
     */
    it('moves a cash legacy of a tenth of the baseline to the reduced rate', function () {
        // Baseline is the net estate less the nil rate band; the residence nil
        // rate band is excluded (IHTA 1984 Sch 1A).
        $user = cashOnlyTestator();
        $baseline = 1_000_000.0 - (float) $this->iht['nil_rate_band'];
        $threshold = $baseline * app(TaxConfigService::class)->getCharitableThresholdPercent();

        charitableBequest($user, 'specific_amount', ['specific_amount' => $threshold + 1]);

        $analysis = charitableAnalysisFor($user);

        expect($analysis['baseline'])->toBe(round($baseline, 2));
        expect($analysis['status'])->not->toBe('below');
        expect($analysis['effective_rate'])->toBe(app(TaxConfigService::class)->getCharitableReducedRate());
    });

    it('reads the reduced rate from configuration rather than a fallback constant', function () {
        // Pins Rule 2 properly: asserting against the same array element the
        // code reads passes whether or not the code honours it. Move the
        // configured rate and the analysis must move with it.
        $config = TaxConfiguration::where('is_active', true)->firstOrFail();
        $data = $config->config_data;
        $data['inheritance_tax']['reduced_rate_charity'] = 0.30;
        $config->update(['config_data' => $data]);
        app()->forgetInstance(TaxConfigService::class);

        $user = cashOnlyTestator();
        $iht = app(TaxConfigService::class)->getInheritanceTax();
        $baseline = 1_000_000.0 - (float) $iht['nil_rate_band'];

        charitableBequest($user, 'specific_amount', [
            'specific_amount' => $baseline * app(TaxConfigService::class)->getCharitableThresholdPercent() + 1,
        ]);

        expect(charitableAnalysisFor($user)['effective_rate'])->toBe(0.30);
    });

    it('strikes the baseline against the household\'s AVAILABLE nil rate band, not a single band', function () {
        // The same intent as the case this replaces — a surviving spouse's band
        // includes the transferred band, so a baseline computed from a single
        // £325,000 disagrees with the Inheritance Tax calculation about the same
        // household — but asserted on the layer where the decision now lives.
        // The old case passed the combined band in as an argument and asserted it
        // came back out, which proves subtraction and nothing about the household.
        $spouse = User::factory()->create([
            'marital_status' => 'married',
            'date_of_birth' => '1955-01-01',
            'gender' => 'male',
        ]);
        $user = User::factory()->create([
            'marital_status' => 'married',
            'date_of_birth' => '1962-01-01',
            'gender' => 'female',
            'spouse_id' => $spouse->id,
        ]);
        $spouse->update(['spouse_id' => $user->id]);

        SavingsAccount::factory()->create([
            'user_id' => $user->id,
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
            'current_balance' => 1_000_000,
            'is_isa' => false,
        ]);

        $singleNrb = (float) $this->iht['nil_rate_band'];
        $analysis = charitableAnalysisFor($user, $spouse);

        expect($analysis['baseline'])->toBe(round(1_000_000.0 - $singleNrb * 2, 2))
            ->and($analysis['baseline'])->not->toBe(round(1_000_000.0 - $singleNrb, 2));
    });

    it('goes quiet rather than naming a shortfall it cannot compute', function () {
        // An asset gift to charity DOES count in law but carries no figure, so
        // telling the user to give another £X would assume it is worth nothing.
        $user = cashOnlyTestator();
        charitableBequest($user, 'specific_asset', ['specific_asset_description' => 'My art collection']);

        $analysis = charitableAnalysisFor($user);

        expect($analysis['has_unvalued_charitable_gifts'])->toBeTrue();
        expect($analysis['message'])->toBe(app(WillAnalysisService::class)->unvaluedCharitableGiftsMessage());
        expect($analysis['message'])->not->toContain('Increase');
    });

    it('leaves a smaller cash legacy on the standard rate, with the shortfall named', function () {
        $user = cashOnlyTestator();
        $baseline = 1_000_000.0 - (float) $this->iht['nil_rate_band'];
        $threshold = $baseline * app(TaxConfigService::class)->getCharitableThresholdPercent();

        charitableBequest($user, 'specific_amount', ['specific_amount' => $threshold / 2]);

        $analysis = charitableAnalysisFor($user);

        expect($analysis['status'])->toBe('below');
        expect($analysis['effective_rate'])->toBe($this->iht['standard_rate']);
        expect($analysis['shortfall'])->toBeGreaterThan(0.0);
        // The shortfall names what is missing from the THRESHOLD, and the message
        // asks for exactly that much — the pair a user acts on.
        expect($analysis['shortfall'])->toBe(round($threshold / 2, 2));
    });
});

describe('bequest_type comparisons match the enum', function () {
    /*
     * The pin the board item asked for. A string typo against an enum is
     * invisible to review — the code reads correctly, the branch simply never
     * runs. This asserts every literal the service compares bequest_type
     * against is one the validation rules (and therefore the column) allow.
     */
    it('never compares against a value the column cannot hold', function () {
        $allowed = [];
        foreach ([new StoreBequestRequest, new UpdateBequestRequest] as $request) {
            preg_match('/in:([a-z_,]+)/', (string) $request->rules()['bequest_type'], $matches);
            $allowed = array_merge($allowed, explode(',', $matches[1] ?? ''));
        }
        $allowed = array_unique(array_filter($allowed));

        expect($allowed)->not->toBeEmpty();

        $source = file_get_contents(app_path('Services/Estate/WillAnalysisService.php'));
        preg_match_all(
            '/bequest_type\s*(?:===|==|!==|!=)\s*[\'"]([a-z_]+)[\'"]/',
            $source,
            $comparisons,
        );

        expect($comparisons[1])->not->toBeEmpty();

        foreach ($comparisons[1] as $compared) {
            expect($allowed)->toContain($compared);
        }
    });
});

describe('the Inheritance Tax rate reads the recorded will (W-0020 end to end)', function () {
    /*
     * The board's headline symptom. A user recorded a charitable legacy, saw it
     * in their generated will, and the estate page still said
     * charitable_giving_percent = 0, charitable_deduction = 0, iht_rate = 0.4 —
     * because the rate was decided solely by a planning percentage typed on the
     * Inheritance Tax profile and never consulted the bequests at all.
     *
     * £1,000,000 cash estate, single, no property (so the residence nil rate
     * band is £0 and the arithmetic is unambiguous):
     *   baseline  = £1,000,000 − £325,000 = £675,000
     *   threshold = 10% of baseline       = £67,500
     */
    it('moves the rate to the reduced rate from bequests alone, with the profile percentage at zero', function () {
        $user = User::factory()->create(['marital_status' => 'single']);

        SavingsAccount::factory()->create([
            'user_id' => $user->id,
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
            'current_balance' => 1_000_000,
            'is_isa' => false,
        ]);

        IHTProfile::create([
            'user_id' => $user->id,
            'marital_status' => 'single',
            // Deliberately zero — the only figure the rate used to depend on.
            'charitable_giving_percent' => 0,
        ]);

        $service = app(IHTCalculationService::class);
        $taxConfig = app(TaxConfigService::class);

        $before = $service->calculate($user);
        expect((float) $before['iht_rate'])->toBe((float) $this->iht['standard_rate']);
        expect((float) $before['charitable_deduction'])->toBe(0.0);

        $baseline = (float) $before['total_net_estate'] - (float) $this->iht['nil_rate_band'];
        $threshold = $baseline * $taxConfig->getCharitableThresholdPercent();

        charitableBequest($user, 'specific_amount', ['specific_amount' => $threshold + 1]);

        $after = $service->calculate($user->fresh());

        expect((float) $after['iht_rate'])->toBe($taxConfig->getCharitableReducedRate());
        expect((float) $after['charitable_deduction'])->toBe(round($threshold + 1, 2));
        expect((float) $after['charitable_giving_percent'])->toBeGreaterThan(0.0);
    });

    it('leaves the rate alone when the recorded legacy is below the threshold', function () {
        $user = User::factory()->create(['marital_status' => 'single']);

        SavingsAccount::factory()->create([
            'user_id' => $user->id,
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
            'current_balance' => 1_000_000,
            'is_isa' => false,
        ]);

        IHTProfile::create([
            'user_id' => $user->id,
            'marital_status' => 'single',
            'charitable_giving_percent' => 0,
        ]);

        charitableBequest($user, 'specific_amount', ['specific_amount' => 10_000]);

        $result = app(IHTCalculationService::class)->calculate($user);

        expect((float) $result['iht_rate'])->toBe((float) $this->iht['standard_rate']);
        // Still deducted from the taxable estate — exempt under IHTA 1984 s.23
        // even where it does not reach the 10% threshold for the reduced rate.
        expect((float) $result['charitable_deduction'])->toBe(10_000.0);
    });

    it('still honours the profile percentage for a user with no bequests recorded', function () {
        $user = User::factory()->create(['marital_status' => 'single']);

        SavingsAccount::factory()->create([
            'user_id' => $user->id,
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
            'current_balance' => 1_000_000,
            'is_isa' => false,
        ]);

        IHTProfile::create([
            'user_id' => $user->id,
            'marital_status' => 'single',
            'charitable_giving_percent' => 10,
        ]);

        $result = app(IHTCalculationService::class)->calculate($user);

        expect((float) $result['iht_rate'])->toBe(app(TaxConfigService::class)->getCharitableReducedRate());
    });
});

describe('the cached Inheritance Tax calculation notices a new bequest', function () {
    /*
     * The calculation is cached in `iht_calculations`, keyed on hashes of assets
     * and liabilities. That was sound while the rate depended only on the
     * profile percentage — but once the rate reads recorded bequests (W-0020),
     * a user could record a charitable legacy, qualify for the reduced rate, and
     * keep being served the old 40% figure from cache until their assets
     * happened to change. The bequests are now part of the key.
     */
    it('does not serve a stale rate after a qualifying legacy is recorded', function () {
        $user = User::factory()->create(['marital_status' => 'single']);

        SavingsAccount::factory()->create([
            'user_id' => $user->id,
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
            'current_balance' => 1_000_000,
            'is_isa' => false,
        ]);

        IHTProfile::create([
            'user_id' => $user->id,
            'marital_status' => 'single',
            'charitable_giving_percent' => 0,
        ]);

        $service = app(IHTCalculationService::class);

        // persist: true writes the cache row the next read would be served from.
        $before = $service->calculate($user, null, false, true);
        expect((float) $before['iht_rate'])->toBe((float) $this->iht['standard_rate']);

        $baseline = (float) $before['total_net_estate'] - (float) $this->iht['nil_rate_band'];
        $threshold = $baseline * app(TaxConfigService::class)->getCharitableThresholdPercent();

        charitableBequest($user, 'specific_amount', ['specific_amount' => $threshold + 1]);

        // Nothing about the user's assets or liabilities changed, so before the
        // fix this read came straight back out of the cache at 40%.
        $after = $service->calculate($user->fresh());

        expect((float) $after['iht_rate'])
            ->toBe(app(TaxConfigService::class)->getCharitableReducedRate());
    });
});

/**
 * W-0132 — one answer to "is this person leaving money to charity".
 *
 * `/settings/family` asked the question and answered "Not set" for a user whose
 * will held a £10,000 charitable legacy the estate calculation was already using.
 * It was reading `users.charitable_bequest`, a column written by a toggle on
 * /estate and never loaded back — a fourth mechanism, and the only one that was
 * wrong. The will is the instrument, so the card reads this summary instead.
 *
 * Every test below sets `users.charitable_bequest` to the value that would give
 * the WRONG answer, so a summary that consulted the column could not pass.
 */
describe('charitableBequestSummary — the will answers, not the toggle', function () {
    it('reports a recorded legacy on an account whose toggle was never answered', function () {
        $user = User::factory()->create();
        charitableBequest($user, 'specific_amount', ['specific_amount' => 10000]);

        expect($this->service->charitableBequestSummary($user->fresh()))->toBe([
            'has_bequests' => true,
            'count' => 1,
            'fixed_total' => 10000.0,
            'has_estate_share' => false,
        ]);
    });

    it('reports a recorded legacy even where the user answered the toggle No', function () {
        $user = User::factory()->create();
        charitableBequest($user, 'specific_amount', ['specific_amount' => 10000]);

        expect($this->service->charitableBequestSummary($user->fresh())['has_bequests'])->toBeTrue();
    });

    it('reports nothing recorded where the user answered the toggle Yes but left no legacy', function () {
        $user = User::factory()->create();
        Will::create(['user_id' => $user->id, 'has_will' => true]);

        expect($this->service->charitableBequestSummary($user->fresh()))->toBe([
            'has_bequests' => false,
            'count' => 0,
            'fixed_total' => 0.0,
            'has_estate_share' => false,
        ]);
    });

    it('reports nothing recorded for a user with no will at all', function () {
        $user = User::factory()->create();

        expect($this->service->charitableBequestSummary($user)['has_bequests'])->toBeFalse();
    });

    it('excludes a non-charitable beneficiary from the count and the total', function () {
        $user = User::factory()->create();
        charitableBequest($user, 'specific_amount', ['specific_amount' => 10000]);
        charitableBequest($user, 'specific_amount', [
            'beneficiary_name' => 'Ravi Raman',
            'beneficiary_type' => 'individual',
            'specific_amount' => 50000,
            'priority_order' => 2,
        ]);

        $summary = $this->service->charitableBequestSummary($user->fresh());

        expect($summary['count'])->toBe(1)
            ->and($summary['fixed_total'])->toBe(10000.0);
    });

    /**
     * A percentage gift is worth nothing until an estate is valued, and a settings
     * page has no business valuing one to render a card. Counting it as £0 inside a
     * printed total is the same class of defect as the one being fixed, so it is
     * flagged and left out of the total instead.
     */
    it('flags a share of the estate rather than totalling it as zero', function () {
        $user = User::factory()->create();
        charitableBequest($user, 'percentage', ['percentage_of_estate' => 10]);

        expect($this->service->charitableBequestSummary($user->fresh()))->toBe([
            'has_bequests' => true,
            'count' => 1,
            'fixed_total' => 0.0,
            'has_estate_share' => true,
        ]);
    });

    it('reports both parts where a will mixes a cash legacy with a residuary gift', function () {
        $user = User::factory()->create();
        charitableBequest($user, 'specific_amount', ['specific_amount' => 10000]);
        charitableBequest($user, 'residuary', ['priority_order' => 2]);

        $summary = $this->service->charitableBequestSummary($user->fresh());

        expect($summary['count'])->toBe(2)
            ->and($summary['fixed_total'])->toBe(10000.0)
            ->and($summary['has_estate_share'])->toBeTrue();
    });

    /**
     * The summary and the figure the estate calculation deducts must not be able to
     * disagree about the same will — both read `Bequest::isCharitable()`.
     */
    it('agrees with the total the estate calculation deducts', function () {
        $user = User::factory()->create();
        charitableBequest($user, 'specific_amount', ['specific_amount' => 10000]);

        $user = $user->fresh();

        expect($this->service->charitableBequestSummary($user)['fixed_total'])
            ->toBe($this->service->getCharitableBequestTotal($user, 0));
    });
});
