<?php

declare(strict_types=1);

use App\Models\DCPension;
use App\Models\User;
use App\Services\Stores\PropertyStore;
use App\Services\Tax\IncomeDefinitionsService;
use App\Services\TaxConfigService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Database\Eloquent\Model;

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->taxConfig = app(TaxConfigService::class);
    $this->service = new IncomeDefinitionsService($this->taxConfig, app(PropertyStore::class));

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

    it('deducts Gift Aid gross-up when is_gift_aid is true', function () {
        $user = User::factory()->create([
            'annual_employment_income' => 60000,
            'annual_charitable_donations' => 1000,
            'is_gift_aid' => true,
        ]);

        $result = $this->service->calculate($user->id);
        expect($result['deductions']['gift_aid_gross'])->toBe(1250.00);
        expect($result['net_income'])->toBe(58750.00);
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
