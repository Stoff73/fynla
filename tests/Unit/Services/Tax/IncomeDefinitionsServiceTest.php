<?php

declare(strict_types=1);

use App\Models\DCPension;
use App\Models\User;
use App\Services\Property\PropertyService;
use App\Services\Tax\IncomeDefinitionsService;
use App\Services\TaxConfigService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Database\Eloquent\Model;

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->taxConfig = app(TaxConfigService::class);
    $this->service = new IncomeDefinitionsService($this->taxConfig, app(PropertyService::class));

    // These tests verify income-definition math only. Mute model events so the
    // RecommendationCacheObserver (agent cache invalidation) does not fire when
    // arranging DCPension fixtures — it is irrelevant here and keeps the unit
    // test isolated from the wider agent dependency graph.
    $this->modelEventDispatcher = Model::getEventDispatcher();
    Model::unsetEventDispatcher();
});

afterEach(function () {
    Model::setEventDispatcher($this->modelEventDispatcher);
    Mockery::close();
});

describe('Total Income', function () {
    it('sums all income sources', function () {
        $user = User::factory()->create([
            'annual_employment_income' => 60000,
            'annual_self_employment_income' => 0,
            'annual_dividend_income' => 5000,
            'annual_interest_income' => 2000,
            'annual_other_income' => 1000,
            'annual_trust_income' => 0,
        ]);

        $result = $this->service->calculate($user->id);
        // 60000 + 5000 + 2000 + 1000 = 68000 (rental comes from Property model, pension from DB/State)
        expect($result['total_income'])->toBe(68000.00);
    });

    it('returns zero for user with no income', function () {
        $user = User::factory()->create([
            'annual_employment_income' => 0,
            'annual_self_employment_income' => 0,
            'annual_rental_income' => 0,
            'annual_dividend_income' => 0,
            'annual_interest_income' => 0,
            'annual_other_income' => 0,
            'annual_trust_income' => 0,
        ]);

        $result = $this->service->calculate($user->id);
        expect($result['total_income'])->toBe(0.00);
    });
});

describe('Net Income', function () {
    it('deducts pension relief from total income', function () {
        $user = User::factory()->create([
            'annual_employment_income' => 60000,
        ]);
        DCPension::factory()->create([
            'user_id' => $user->id,
            'annual_salary' => 60000,
            'employee_contribution_percent' => 5.00,
            'employer_contribution_percent' => 3.00,
        ]);

        $result = $this->service->calculate($user->id);
        expect($result['deductions']['pension_relief'])->toBe(3000.00);
        expect($result['net_income'])->toBe(57000.00);
    });

    // W-0205. Gift Aid is not one of the reliefs ITA 2007 s24 lists, so it does not
    // reduce net income — it comes off at s58, with the Blind Person's Allowance.
    // This asserted 58,750 as net income, which is the s58 figure under the s23 name.
    it('leaves net income alone for a Gift Aid donor and deducts the gross-up at adjusted net income', function () {
        $user = User::factory()->create([
            'annual_employment_income' => 60000,
            'annual_charitable_donations' => 1000,
            'is_gift_aid' => true,
        ]);

        $result = $this->service->calculate($user->id);

        expect($result['deductions']['gift_aid_gross'])->toBe(1250.00)
            ->and($result['net_income'])->toBe(60000.00)
            ->and($result['adjusted_net_income'])->toBe(58750.00);
    });

    it('does not deduct Gift Aid when is_gift_aid is false', function () {
        $user = User::factory()->create([
            'annual_employment_income' => 60000,
            'annual_charitable_donations' => 1000,
            'is_gift_aid' => false,
        ]);

        $result = $this->service->calculate($user->id);
        expect($result['deductions']['gift_aid_gross'])->toBe(0.00);
        expect($result['net_income'])->toBe(60000.00);
    });
});

describe('Adjusted Net Income', function () {
    it('deducts BPA when registered blind', function () {
        $user = User::factory()->create([
            'annual_employment_income' => 60000,
            'is_registered_blind' => true,
        ]);

        $result = $this->service->calculate($user->id);
        expect($result['deductions']['blind_persons_allowance'])->toBe(3250.00);
        expect($result['adjusted_net_income'])->toBe(56750.00);
    });

    it('does not deduct BPA when not registered blind', function () {
        $user = User::factory()->create([
            'annual_employment_income' => 60000,
            'is_registered_blind' => false,
        ]);

        $result = $this->service->calculate($user->id);
        expect($result['deductions']['blind_persons_allowance'])->toBe(0.00);
        expect($result['adjusted_net_income'])->toBe(60000.00);
    });
});

describe('Threshold and Adjusted Income', function () {
    it('calculates threshold income by deducting employee pension contributions', function () {
        $user = User::factory()->create([
            'annual_employment_income' => 250000,
        ]);
        DCPension::factory()->create([
            'user_id' => $user->id,
            'annual_salary' => 250000,
            'employee_contribution_percent' => 5.00,
            'employer_contribution_percent' => 10.00,
        ]);

        $result = $this->service->calculate($user->id);
        // FA 2004 s228ZA: threshold income = total income less net-pay employee
        // contributions, deducted ONCE. Total = 250000, employee = 12500.
        // Threshold = 250000 - 12500 = 237500 (the old code double-deducted to 225000).
        expect($result['threshold_income'])->toBe(237500.00);
    });

    it('calculates adjusted income by adding employer contributions', function () {
        $user = User::factory()->create([
            'annual_employment_income' => 250000,
        ]);
        DCPension::factory()->create([
            'user_id' => $user->id,
            'annual_salary' => 250000,
            'employee_contribution_percent' => 5.00,
            'employer_contribution_percent' => 10.00,
        ]);

        $result = $this->service->calculate($user->id);
        // FA 2004 s228ZA: adjusted income = total income plus employer
        // contributions. Total = 250000, employer = 25000.
        // Adjusted = 250000 + 25000 = 275000 (the old code understated it to 250000).
        expect($result['adjusted_income'])->toBe(275000.00);
    });

    it('no longer under-states a high earner so the AA taper correctly applies', function () {
        // The old double-deduction chain gave threshold 225000 / adjusted 250000,
        // and adjusted 250000 (< £260k) fell below the taper trigger, so no taper.
        // Correct FA 2004 s228ZA gives threshold 237500 / adjusted 275000 → taper applies.
        $user = User::factory()->create([
            'annual_employment_income' => 250000,
        ]);
        DCPension::factory()->create([
            'user_id' => $user->id,
            'annual_salary' => 250000,
            'employee_contribution_percent' => 5.00,
            'employer_contribution_percent' => 10.00,
        ]);

        $result = $this->service->calculate($user->id);

        expect($result['threshold_income'])->toBe(237500.00)
            ->and($result['adjusted_income'])->toBe(275000.00)
            ->and($result['adjusted_allowances']['pension_aa_tapered'])->toBeTrue();
        // Adjusted excess = 275000 - 260000 = 15000; reduction = 7500; AA = 52500.
        expect($result['adjusted_allowances']['pension_annual_allowance'])->toBe(52500.00);
    });
});

describe('Adjusted Allowances', function () {
    it('tapers personal allowance when ANI exceeds 100k', function () {
        $user = User::factory()->create([
            'annual_employment_income' => 130000,
        ]);

        $result = $this->service->calculate($user->id);
        // PA reduction = floor((130000 - 100000) / 2) = 15000
        // Adjusted PA = max(0, 12570 - 15000) = 0
        expect($result['adjusted_allowances']['personal_allowance'])->toBe(0.00);
        expect($result['adjusted_allowances']['personal_allowance_tapered'])->toBeTrue();
    });

    it('keeps full PA when income below 100k', function () {
        $user = User::factory()->create([
            'annual_employment_income' => 60000,
        ]);

        $result = $this->service->calculate($user->id);
        expect($result['adjusted_allowances']['personal_allowance'])->toBe(12570.00);
        expect($result['adjusted_allowances']['personal_allowance_tapered'])->toBeFalse();
    });

    it('tapers pension AA when both thresholds exceeded', function () {
        $user = User::factory()->create([
            'annual_employment_income' => 300000,
        ]);
        DCPension::factory()->create([
            'user_id' => $user->id,
            'annual_salary' => 300000,
            'employee_contribution_percent' => 2.00,
            'employer_contribution_percent' => 5.00,
        ]);

        $result = $this->service->calculate($user->id);
        expect($result['adjusted_allowances']['pension_aa_tapered'])->toBeTrue();
        expect($result['adjusted_allowances']['pension_annual_allowance'])->toBeLessThan(60000.00);
        expect($result['adjusted_allowances']['pension_annual_allowance'])->toBeGreaterThanOrEqual(10000.00);
    });

    it('keeps full pension AA when threshold income below 200k', function () {
        $user = User::factory()->create([
            'annual_employment_income' => 80000,
        ]);

        $result = $this->service->calculate($user->id);
        expect($result['adjusted_allowances']['pension_annual_allowance'])->toBe(60000.00);
        expect($result['adjusted_allowances']['pension_aa_tapered'])->toBeFalse();
    });
});

describe('Components breakdown', function () {
    it('returns all income components including pension_income', function () {
        $user = User::factory()->create([
            'annual_employment_income' => 50000,
            'annual_dividend_income' => 3000,
        ]);

        $result = $this->service->calculate($user->id);
        expect($result['components'])->toHaveKeys([
            'employment', 'self_employment', 'rental', 'dividend',
            'interest', 'other', 'trust', 'pension_income',
        ]);
        expect($result['components']['employment'])->toBe(50000.00);
        expect($result['components']['dividend'])->toBe(3000.00);
    });
});

/**
 * W-0189 — the panel printed a chain whose steps did not produce the figures
 * beneath them, and the question that had to be settled before touching anything
 * was whether the ARITHMETIC or the PRESENTATION was wrong.
 *
 * It is the presentation. Threshold Income and Adjusted Income both branch from
 * TOTAL income; neither continues the Net Income column above it, and the employee
 * contribution they involve has already been deducted once at Net Income. Deducting
 * it a second time would be the bug.
 *
 * These pin that branching explicitly, and pin the arrangement the service now
 * publishes so the screen can name it instead of leaving a reader to guess why one
 * deduction appears at two steps.
 */
describe('W-0189 — which base each definition is built from', function () {
    it('builds threshold and adjusted income from total income, not from net income', function () {
        $user = User::factory()->create([
            'annual_employment_income' => 145000,
            'annual_dividend_income' => 14290,
            'is_gift_aid' => true,
            'annual_charitable_donations' => 4000,
        ]);
        DCPension::factory()->create([
            'user_id' => $user->id,
            'annual_salary' => 145000,
            'employee_contribution_percent' => 8.00,
            'employer_contribution_percent' => 8.00,
        ]);

        $result = $this->service->calculate($user->id);
        $employee = $result['deductions']['employee_pension_contributions'];
        $employer = $result['deductions']['employer_pension_contributions'];

        // Gift Aid reduces ADJUSTED net income and does NOT reduce threshold income,
        // so with a donation in play those two are provably different numbers — which
        // is what makes this a test of the base rather than a restatement of the
        // fixture.
        //
        // W-0205 moved the differentiator. This used to compare net income against
        // threshold income, which worked only because net income was wrongly carrying
        // the Gift Aid deduction. Those two now coincide for a net-pay contributor,
        // correctly — the deduction that separates the definitions is the one at s58.
        expect($result['deductions']['gift_aid_gross'])->toBeGreaterThan(0.0)
            ->and($result['adjusted_net_income'])->not->toBe($result['threshold_income']);

        expect($result['threshold_income'])->toBe(round($result['total_income'] - $employee, 2))
            ->and($result['adjusted_income'])->toBe(round($result['total_income'] + $employer, 2));
    });

    it('deducts the employee contribution once across the two definitions that name it', function () {
        // David Jones's figures, with the £14,290 arriving as dividends rather than
        // rental profit — the deduction behaves identically and the fixture stays a
        // unit fixture.
        $user = User::factory()->create([
            'annual_employment_income' => 145000,
            'annual_dividend_income' => 14290,
        ]);
        DCPension::factory()->create([
            'user_id' => $user->id,
            'annual_salary' => 145000,
            'employee_contribution_percent' => 8.00,
            'employer_contribution_percent' => 8.00,
        ]);

        $result = $this->service->calculate($user->id);
        $employee = $result['deductions']['employee_pension_contributions'];

        // £11,600 out of £159,290 leaves £147,690 — reached once, by either route,
        // never £136,090. The screen showed the deduction twice and printed £147,690
        // beneath the second one.
        expect($employee)->toBe(11600.00)
            ->and($result['total_income'])->toBe(159290.00)
            ->and($result['net_income'])->toBe(147690.00)
            ->and($result['threshold_income'])->toBe(147690.00)
            ->and($result['threshold_income'])->not->toBe(round($result['net_income'] - $employee, 2));
    });

    it('names the arrangement as net pay when no workplace pension sacrifices salary', function () {
        $user = User::factory()->create(['annual_employment_income' => 145000]);
        DCPension::factory()->create([
            'user_id' => $user->id,
            'annual_salary' => 145000,
            'employee_contribution_percent' => 8.00,
            'employer_contribution_percent' => 8.00,
            'salary_sacrifice' => false,
        ]);

        expect($this->service->calculate($user->id)['pension_arrangement'])->toBe('net_pay');
    });

    it('names salary sacrifice where a workplace pension uses it', function () {
        $user = User::factory()->create(['annual_employment_income' => 145000]);
        DCPension::factory()->create([
            'user_id' => $user->id,
            'annual_salary' => 145000,
            'employee_contribution_percent' => 8.00,
            'employer_contribution_percent' => 8.00,
            'salary_sacrifice' => true,
        ]);

        $result = $this->service->calculate($user->id);

        // Naming it does NOT change the figures. The sacrificed pay is not added
        // back under FA 2004 s228ZA(3) because nothing records whether the entered
        // employment income is the pre- or post-sacrifice figure; the screen states
        // the arrangement rather than claiming a treatment that was never applied.
        expect($result['pension_arrangement'])->toBe('salary_sacrifice')
            ->and($result['threshold_income'])->toBe(round($result['total_income'] - 11600.00, 2));
    });

    it('names no arrangement for a user with nothing to deduct', function () {
        $user = User::factory()->create(['annual_employment_income' => 128880]);

        $result = $this->service->calculate($user->id);

        // Sarah Jones: every figure on her panel is the same figure, and it must
        // stay that way rather than growing steps that do nothing.
        expect($result['pension_arrangement'])->toBe('none')
            ->and($result['threshold_income'])->toBe($result['total_income'])
            ->and($result['adjusted_income'])->toBe($result['total_income']);
    });
});

/**
 * W-0205 — a row labelled with one statute carrying another statute's number.
 *
 * "Net income" is defined: ITA 2007 s23 Step 2, total income less the reliefs s24
 * lists. **Gift Aid is not one of them.** A Gift Aid donation extends the basic rate
 * band; it does not reduce net income. The grossed-up donation is deducted one
 * definition further down, at adjusted net income (s58), with the Blind Person's
 * Allowance.
 *
 * The service deducted it at net income, so for a donor the figure under that label
 * was net income less the grossed-up donation — part of the way to adjusted net
 * income, and not a figure with a name. No outcome was wrong: the donation was
 * deducted exactly once on the way to s58 either way, and threshold income never read
 * the intermediate. The panel exists to be checked, and was checked by the one reader
 * who would notice — someone reconciling against HMRC's own definitions.
 *
 * These assert the three figures against the statutory definitions rather than
 * against what the service used to print.
 */
describe('W-0205 — Gift Aid is deducted at adjusted net income, not at net income', function () {
    it('gives a Gift Aid donor the same net income as an identical non-donor', function () {
        $attributes = ['annual_employment_income' => 80000, 'annual_charitable_donations' => 2000];

        $donor = User::factory()->create($attributes + ['is_gift_aid' => true]);
        $nonDonor = User::factory()->create($attributes + ['is_gift_aid' => false]);

        $donorResult = $this->service->calculate($donor->id);
        $nonDonorResult = $this->service->calculate($nonDonor->id);

        // s24 lists no relief a donation qualifies for, so the two are identical at
        // s23 Step 2 and diverge only at s58.
        expect($donorResult['net_income'])->toBe($nonDonorResult['net_income'])
            ->and($donorResult['adjusted_net_income'])->not->toBe($nonDonorResult['adjusted_net_income']);
    });

    it('deducts the grossed-up donation once, between net income and adjusted net income', function () {
        $user = User::factory()->create([
            'annual_employment_income' => 80000,
            'annual_charitable_donations' => 2000,
            'is_gift_aid' => true,
        ]);

        $result = $this->service->calculate($user->id);
        $grossUp = $result['deductions']['gift_aid_gross'];

        // £2,000 net is £2,500 gross at the basic rate.
        expect($grossUp)->toBe(2500.00)
            ->and($result['net_income'])->toBe(80000.00)
            ->and($result['adjusted_net_income'])->toBe(round($result['net_income'] - $grossUp, 2));
    });

    it('deducts Gift Aid and the Blind Person\'s Allowance at the same step', function () {
        $user = User::factory()->create([
            'annual_employment_income' => 80000,
            'annual_charitable_donations' => 2000,
            'is_gift_aid' => true,
            'is_registered_blind' => true,
        ]);

        $result = $this->service->calculate($user->id);
        $grossUp = $result['deductions']['gift_aid_gross'];
        $bpa = $result['deductions']['blind_persons_allowance'];

        // Both are s58 deductions and neither touches s23 Step 2.
        expect($bpa)->toBeGreaterThan(0.0)
            ->and($result['net_income'])->toBe(80000.00)
            ->and($result['adjusted_net_income'])->toBe(round(80000.00 - $grossUp - $bpa, 2));
    });

    it('leaves threshold income and adjusted income untouched by a donation', function () {
        $attributes = [
            'annual_employment_income' => 145000,
            'annual_dividend_income' => 14290,
            'annual_charitable_donations' => 4000,
        ];

        $donor = User::factory()->create($attributes + ['is_gift_aid' => true]);
        $nonDonor = User::factory()->create($attributes + ['is_gift_aid' => false]);

        foreach ([$donor, $nonDonor] as $person) {
            DCPension::factory()->create([
                'user_id' => $person->id,
                'annual_salary' => 145000,
                'employee_contribution_percent' => 8.00,
                'employer_contribution_percent' => 8.00,
            ]);
        }

        $donorResult = $this->service->calculate($donor->id);
        $nonDonorResult = $this->service->calculate($nonDonor->id);

        // Acceptance 3: if either of these moves, the fix is wrong. Gift Aid belongs
        // to the Personal Allowance taper, not to the Annual Allowance taper.
        expect($donorResult['threshold_income'])->toBe($nonDonorResult['threshold_income'])
            ->and($donorResult['adjusted_income'])->toBe($nonDonorResult['adjusted_income'])
            ->and($donorResult['threshold_income'])->toBe(round($donorResult['total_income'] - $donorResult['deductions']['employee_pension_contributions'], 2));
    });

    it('tapers the Personal Allowance on the figure that includes the donation', function () {
        // £110,000 is over the £100,000 taper threshold; a £8,000 net donation grosses
        // to £10,000 and pulls adjusted net income to £100,000, restoring the full
        // allowance. The taper must read s58, not s23 — this is the one place the
        // distinction reaches a number the user is charged on.
        $user = User::factory()->create([
            'annual_employment_income' => 110000,
            'annual_charitable_donations' => 8000,
            'is_gift_aid' => true,
        ]);

        $result = $this->service->calculate($user->id);

        expect($result['net_income'])->toBe(110000.00)
            ->and($result['adjusted_net_income'])->toBe(100000.00)
            ->and($result['adjusted_allowances']['personal_allowance_tapered'])->toBeFalse();
    });
});
