<?php

declare(strict_types=1);

use App\Models\Estate\Bequest;
use App\Models\Estate\IHTProfile;
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

uses(RefreshDatabase::class);

/**
 * W-0136, W-0134 and W-0135 — the projection is assessed, not inherited.
 *
 * The defect these pin: every rate and allowance test in `IHTCalculationService`
 * was evaluated ONCE against the current estate and its answer carried into a
 * projection two or three times larger.
 *
 *   * The £2,000,000 residence-band taper never fired on a projection, however
 *     large. The arithmetic existed and was correct — it was simply never asked
 *     about this estate — and the footnote asserting the estate was below the
 *     threshold was printed beside a column that was millions above it.
 *   * The 10% charitable rate test was decided against today's estate, so a
 *     household on 36% carried that rate to a death whose baseline it could not
 *     possibly qualify against.
 *   * A FIXED cash legacy was inflated in proportion to the estate, because the
 *     projection scaled the exemption by projected ÷ current instead of asking
 *     what the will actually gives.
 *
 * **How these are written, deliberately.** They assert that the answer MOVES when
 * a configured input moves, rather than asserting a literal. A test that pins
 * £350,000 passes just as happily against a hardcoded £350,000; a test that
 * changes the configured taper threshold and requires the band to follow cannot.
 * That distinction is not theoretical here — on 2026-08-21 a mock in this codebase
 * returned `'rate' => 0.40`, the same key the code wrongly asked for, and the
 * suite stayed green over a tax rate no configuration change could move.
 *
 * **Everything is built from fixtures.** The persona household (users 16 and 17) is
 * held by a tester and is read-only.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->service = app(IHTCalculationService::class);
});

/**
 * Overwrite part of the active inheritance tax configuration and hand back a
 * freshly-resolved calculation service reading it.
 *
 * This is how "the answer follows the configuration" is proven rather than
 * asserted: no fixture, no mock, the same seeded row the application reads.
 */
function withIhtConfig(array $overrides): IHTCalculationService
{
    $row = TaxConfiguration::where('is_active', true)->firstOrFail();
    $data = $row->config_data;
    $data['inheritance_tax'] = array_merge($data['inheritance_tax'], $overrides);
    $row->config_data = $data;
    $row->save();

    app(TaxConfigService::class)->clearCache();
    app()->forgetInstance(IHTCalculationService::class);

    return app(IHTCalculationService::class);
}

/**
 * A married household holding a home and cash and nothing else.
 *
 * No investment accounts, on purpose: the Monte Carlo projection is the one
 * stochastic input into the projected estate, and an empty portfolio takes the
 * deterministic branch. Everything that remains — property growth, the cash
 * surplus model, liabilities — is deterministic for a given fixture, so the
 * relationships asserted below hold on every run.
 *
 * @return array{0: User, 1: User}
 */
function projectionHousehold(float $residenceValue, float $cash): array
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

    Property::factory()->create([
        'user_id' => $david->id,
        'property_type' => 'main_residence',
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
        'current_value' => $residenceValue,
    ]);

    SavingsAccount::factory()->create([
        'user_id' => $david->id,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
        'current_balance' => $cash,
        'is_isa' => false,
    ]);

    // The residence allowance needs a direct descendant (IHTA 1984 s8E/s8K).
    FamilyMember::factory()->create([
        'user_id' => $david->id,
        'relationship' => 'child',
    ]);

    return [$david->fresh(), $sarah->fresh()];
}

/**
 * Give a member a will containing one charitable bequest.
 */
function charitableWill(User $member, string $type, float $amount): void
{
    $will = Will::factory()->create([
        'user_id' => $member->id,
        'has_will' => true,
        'spouse_primary_beneficiary' => true,
        'spouse_bequest_percentage' => 100,
    ]);

    Bequest::factory()->create([
        'will_id' => $will->id,
        'user_id' => $member->id,
        'beneficiary_name' => 'Cancer Research UK',
        'beneficiary_type' => 'charity',
        'bequest_type' => $type,
        'specific_amount' => $type === 'specific_amount' ? $amount : null,
        'percentage_of_estate' => $type === 'percentage' ? $amount : null,
    ]);
}

describe('W-0136 — the residence band taper reaches the projection', function () {
    it('tapers the projected band while leaving today\'s intact', function () {
        [$david, $sarah] = projectionHousehold(900_000, 900_000);

        $result = $this->service->calculate($david, $sarah, true);

        // The fixture only demonstrates the defect if it straddles the threshold.
        // Assert that, so a fixture that drifts fails loudly rather than passing
        // for the wrong reason.
        expect($result['total_net_estate'])->toBeLessThan(2_000_000.0)
            ->and($result['projected_net_estate'])->toBeGreaterThan(2_000_000.0);

        // Today: below the threshold, so the full combined band stands.
        expect($result['rnrb_available'])->toBe(350_000.0)
            ->and($result['rnrb_status'])->toBe('full');

        // At death: above it, so the band is reduced. This was £350,000 before.
        $excess = $result['projected_net_estate'] - 2_000_000.0;
        $expected = max(0.0, 350_000.0 - $excess * 0.5);

        expect($result['projected_rnrb_available'])->toBe(round($expected, 2))
            ->and($result['projected_rnrb_available'])->toBeLessThan($result['rnrb_available'])
            ->and($result['projected_rnrb_status'])->toBe('tapered');
    });

    it('extinguishes the projected band entirely once the taper exceeds it', function () {
        [$david, $sarah] = projectionHousehold(1_400_000, 500_000);

        $result = $this->service->calculate($david, $sarah, true);

        expect($result['projected_net_estate'])->toBeGreaterThan(2_700_000.0)
            ->and($result['rnrb_available'])->toBe(350_000.0)
            ->and($result['projected_rnrb_available'])->toBe(0.0)
            ->and($result['projected_rnrb_status'])->toBe('tapered')
            ->and($result['projected_total_allowances'])->toBe($result['projected_nrb_available']);
    });

    it('states the projected position rather than repeating today\'s', function () {
        [$david, $sarah] = projectionHousehold(1_400_000, 500_000);

        $result = $this->service->calculate($david, $sarah, true);

        // The complaint that raised W-0136: "Your combined estate is below the
        // £2,000,000 taper threshold" printed under a column showing millions more.
        expect($result['rnrb_message'])->toContain('below')
            ->and($result['projected_rnrb_message'])->toContain('exceeds')
            ->and($result['projected_rnrb_message'])->not->toBe($result['rnrb_message']);
    });

    it('follows the configured taper threshold rather than a literal', function () {
        [$david, $sarah] = projectionHousehold(900_000, 900_000);

        $tapered = $this->service->calculate($david, $sarah, true);
        expect($tapered['projected_rnrb_available'])->toBeLessThan(350_000.0);

        // Lift the threshold above the projected estate. A hardcoded £2,000,000
        // cannot follow this; the band must come back in full and the tax must fall.
        $service = withIhtConfig(['rnrb_taper_threshold' => 50_000_000]);
        $lifted = $service->calculate($david->fresh(), $sarah->fresh(), true);

        expect($lifted['projected_rnrb_available'])->toBe(350_000.0)
            ->and($lifted['projected_rnrb_status'])->toBe('full')
            ->and($lifted['projected_iht_liability'])->toBeLessThan($tapered['projected_iht_liability'])
            // The current column is unaffected: it was never above the old threshold.
            ->and($lifted['rnrb_available'])->toBe($tapered['rnrb_available']);
    });

    it('follows the configured taper rate rather than a literal', function () {
        [$david, $sarah] = projectionHousehold(900_000, 900_000);

        $atHalf = $this->service->calculate($david, $sarah, true);

        // A gentler taper leaves more band standing, so less tax.
        $service = withIhtConfig(['rnrb_taper_rate' => 0.1]);
        $atTenth = $service->calculate($david->fresh(), $sarah->fresh(), true);

        expect($atTenth['projected_rnrb_available'])->toBeGreaterThan($atHalf['projected_rnrb_available'])
            ->and($atTenth['projected_iht_liability'])->toBeLessThan($atHalf['projected_iht_liability']);
    });

    it('leaves a household below the threshold on the full band in both columns', function () {
        // The home is worth more than the combined band, so the s8E(2) cap does not
        // bite and the only thing that could reduce the band is the taper.
        [$david, $sarah] = projectionHousehold(500_000, 100_000);

        $result = $this->service->calculate($david, $sarah, true);

        expect($result['projected_net_estate'])->toBeLessThan(2_000_000.0)
            ->and($result['rnrb_available'])->toBe(350_000.0)
            ->and($result['projected_rnrb_available'])->toBe(350_000.0)
            ->and($result['projected_rnrb_status'])->toBe('full');
    });

    it('caps the projected band at the projected residence value, not today\'s', function () {
        // A modest home: the s8E(2) cap bites rather than the taper, and the whole
        // estate stays well below the taper threshold so the two do not confound.
        [$david, $sarah] = projectionHousehold(120_000, 50_000);

        $result = $this->service->calculate($david, $sarah, true);

        expect($result['projected_net_estate'])->toBeLessThan(2_000_000.0)
            // Today the band is capped at the home's current value.
            ->and($result['rnrb_available'])->toBe(120_000.0)
            ->and($result['rnrb_status'])->toBe('residence_capped')
            // At death the home is worth more, so the cap is higher. Feeding the
            // current value into the projected call froze the cap at a past price.
            ->and($result['projected_rnrb_available'])->toBeGreaterThan($result['rnrb_available'])
            ->and($result['projected_rnrb_available'])->toBeLessThanOrEqual(350_000.0);
    });
});

describe('the charitable exemption is re-assessed, not scaled', function () {
    it('keeps a fixed cash legacy fixed in the projection', function () {
        [$david, $sarah] = projectionHousehold(900_000, 900_000);
        charitableWill($david, 'specific_amount', 10_000);
        charitableWill($sarah, 'specific_amount', 10_000);

        $result = $this->service->calculate($david, $sarah, true);

        // The household's fixed £20,000 of cash legacies used to be multiplied by
        // projected ÷ current — on the persona household, £20,000 became £50,891.
        expect($result['charitable_deduction'])->toBe(20_000.0)
            ->and($result['projected_charitable_deduction'])->toBe(20_000.0)
            // And the projection really is larger, so this is not passing because
            // nothing grew.
            ->and($result['projected_net_estate'])->toBeGreaterThan($result['total_net_estate'] * 1.2);
    });

    it('grows a percentage legacy with the estate', function () {
        [$david, $sarah] = projectionHousehold(900_000, 900_000);
        charitableWill($david, 'percentage', 5);

        $result = $this->service->calculate($david, $sarah, true);

        // The other half of the distinction: a share of the estate DOES grow with
        // it. Freezing everything would be the mirror-image defect.
        expect($result['charitable_deduction'])->toBe(round($result['total_net_estate'] * 0.05, 2))
            ->and($result['projected_charitable_deduction'])->toBe(round($result['projected_net_estate'] * 0.05, 2))
            ->and($result['projected_charitable_deduction'])->toBeGreaterThan($result['charitable_deduction']);
    });

    it('re-runs the 10% rate test against the projected estate', function () {
        [$david, $sarah] = projectionHousehold(900_000, 900_000);

        // A planning percentage large enough to qualify today. The baseline roughly
        // doubles by death while the qualifying share is measured against whichever
        // estate is being tested, so the household must be re-tested, not assumed.
        IHTProfile::create([
            'user_id' => $sarah->id,
            'marital_status' => 'married',
            'charitable_giving_percent' => 10,
        ]);

        $result = $this->service->calculate($david, $sarah, true);

        expect($result['iht_rate'])->toBe(0.36)
            ->and($result['projected_iht_rate'])->toBeFloat();

        // Whatever the projected rate is, the liability must be computed with it
        // rather than with the current one.
        expect($result['projected_iht_liability'])
            ->toBe(round($result['projected_taxable_estate'] * $result['projected_iht_rate'], 2));
    });
});

describe('W-0134 — the published components reconcile to the published totals', function () {
    it('reconciles the nil rate band components in both columns', function () {
        [$david, $sarah] = projectionHousehold(900_000, 900_000);

        $result = $this->service->calculate($david, $sarah, true);

        $reconciled = $result['nrb_individual']
            + $result['nrb_spouse_modelled']
            + $result['nrb_transferred']
            - $result['nrb_gift_deduction'];

        expect($reconciled)->toBe($result['nrb_available'])
            ->and($result['projected_nrb_available'])->toBe($result['nrb_available']);
    });

    it('reconciles both allowance totals to their components', function () {
        [$david, $sarah] = projectionHousehold(900_000, 900_000);

        $result = $this->service->calculate($david, $sarah, true);

        expect($result['total_allowances'])
            ->toBe(round($result['nrb_available'] + $result['rnrb_available'], 2))
            ->and($result['projected_total_allowances'])
            ->toBe(round($result['projected_nrb_available'] + $result['projected_rnrb_available'], 2));
    });

    it('reconciles the projected taxable estate to its own published figures', function () {
        [$david, $sarah] = projectionHousehold(900_000, 900_000);
        charitableWill($david, 'specific_amount', 10_000);

        $result = $this->service->calculate($david, $sarah, true);

        $expected = max(0, $result['projected_net_estate']
            - $result['projected_total_allowances']
            - $result['projected_charitable_deduction']);

        expect($result['projected_taxable_estate'])->toBe(round($expected, 2))
            ->and($result['projected_iht_liability'])
            ->toBe(round($result['projected_taxable_estate'] * $result['projected_iht_rate'], 2));
    });

    it('follows the configured standard rate rather than a literal', function () {
        [$david, $sarah] = projectionHousehold(900_000, 900_000);

        $atForty = $this->service->calculate($david, $sarah, true);
        expect($atForty['iht_rate'])->toBe(0.4);

        $service = withIhtConfig(['standard_rate' => 0.45]);
        $atFortyFive = $service->calculate($david->fresh(), $sarah->fresh(), true);

        expect($atFortyFive['iht_rate'])->toBe(0.45)
            ->and($atFortyFive['projected_iht_rate'])->toBe(0.45)
            ->and($atFortyFive['iht_liability'])
            ->toBe(round($atFortyFive['taxable_estate'] * 0.45, 2))
            ->and($atFortyFive['projected_iht_liability'])
            ->toBe(round($atFortyFive['projected_taxable_estate'] * 0.45, 2))
            ->and($atFortyFive['projected_iht_liability'])
            ->toBeGreaterThan($atForty['projected_iht_liability']);
    });

    it('follows the configured nil rate band rather than a literal', function () {
        [$david, $sarah] = projectionHousehold(900_000, 900_000);

        $atSeeded = $this->service->calculate($david, $sarah, true);

        $service = withIhtConfig(['nil_rate_band' => 400_000]);
        $atRaised = $service->calculate($david->fresh(), $sarah->fresh(), true);

        expect($atRaised['nrb_individual'])->toBe(400_000.0)
            ->and($atRaised['nrb_available'])->toBe(800_000.0)
            ->and($atRaised['projected_nrb_available'])->toBe(800_000.0)
            ->and($atRaised['projected_iht_liability'])
            ->toBeLessThan($atSeeded['projected_iht_liability']);
    });
});

describe('W-0135 — the plan and the drill-down read one calculation', function () {
    it('gives the plan the same projected figures the calculation published', function () {
        [$david, $sarah] = projectionHousehold(900_000, 900_000);
        charitableWill($david, 'specific_amount', 10_000);
        charitableWill($sarah, 'specific_amount', 10_000);

        $calculation = $this->service->calculate($david, $sarah, true);
        $plan = app(EstatePlanService::class)->generatePlan($david->id);

        $projected = $plan['current_situation']['iht_summary']['projected'] ?? null;

        expect($projected)->not->toBeNull();

        // `/plans/estate` recomputed these itself — net estate minus the CURRENT
        // allowances at the CURRENT rate, with the charitable exemption ignored
        // entirely — so the same user at the same instant was quoted two different
        // projected bills depending on which screen they were looking at.
        expect($projected['taxable_estate'])->toBe($calculation['projected_taxable_estate'])
            ->and($projected['iht_liability'])->toBe($calculation['projected_iht_liability'])
            ->and($projected['total_allowances'])->toBe($calculation['projected_total_allowances'])
            ->and($projected['rnrb_available'])->toBe($calculation['projected_rnrb_available'])
            ->and($projected['charitable_deduction'])->toBe($calculation['projected_charitable_deduction']);
    });

    it('gives the plan the allowance explanations the calculation produced', function () {
        [$david, $sarah] = projectionHousehold(900_000, 900_000);

        $calculation = $this->service->calculate($david, $sarah, true);
        $plan = app(EstatePlanService::class)->generatePlan($david->id);
        $situation = $plan['current_situation'];

        // Two hand-written message sets described one household's allowances in two
        // different ways, and only one of them mentioned the gift deduction the
        // arithmetic had already applied.
        expect($situation['nrb_message'])->toBe($calculation['nrb_message'])
            ->and($situation['rnrb_message'])->toBe($calculation['rnrb_message'])
            ->and($situation['projected_rnrb_message'])->toBe($calculation['projected_rnrb_message']);
    });
});
