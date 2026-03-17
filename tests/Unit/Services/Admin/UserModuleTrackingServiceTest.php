<?php

declare(strict_types=1);

uses(
    Tests\TestCase::class,
    Illuminate\Foundation\Testing\RefreshDatabase::class,
);

use App\Models\CashAccount;
use App\Models\CriticalIllnessPolicy;
use App\Models\DBPension;
use App\Models\DCPension;
use App\Models\DisabilityPolicy;
use App\Models\Estate\Asset;
use App\Models\Estate\Gift;
use App\Models\Estate\LastingPowerOfAttorney;
use App\Models\Estate\Trust;
use App\Models\Estate\Will;
use App\Models\Household;
use App\Models\IncomeProtectionPolicy;
use App\Models\Investment\Holding;
use App\Models\Investment\InvestmentAccount;
use App\Models\Investment\RiskProfile;
use App\Models\LifeInsurancePolicy;
use App\Models\RetirementProfile;
use App\Models\SavingsAccount;
use App\Models\SicknessIllnessPolicy;
use App\Models\StatePension;
use App\Models\User;
use App\Services\Admin\UserModuleTrackingService;

beforeEach(function () {
    $this->service = new UserModuleTrackingService();
});

describe('Protection module status', function () {
    it('returns complete status for user with all protection module data', function () {
        $user = User::factory()->create();

        LifeInsurancePolicy::factory()->create(['user_id' => $user->id]);
        CriticalIllnessPolicy::factory()->create(['user_id' => $user->id]);
        IncomeProtectionPolicy::factory()->create(['user_id' => $user->id]);

        $result = $this->service->getModuleStatus($user);

        expect($result['protection']['status'])->toBe('complete')
            ->and($result['protection']['details']['life'])->toBe(1)
            ->and($result['protection']['details']['ci'])->toBe(1)
            ->and($result['protection']['details']['ip'])->toBe(1);
    });

    it('returns partial status for user with some protection data', function () {
        $user = User::factory()->create();

        // Only life insurance, no critical illness
        LifeInsurancePolicy::factory()->create(['user_id' => $user->id]);

        $result = $this->service->getModuleStatus($user);

        expect($result['protection']['status'])->toBe('partial')
            ->and($result['protection']['details']['life'])->toBe(1)
            ->and($result['protection']['details']['ci'])->toBe(0);
    });

    it('returns empty status for user with no data', function () {
        $user = User::factory()->create();

        $result = $this->service->getModuleStatus($user);

        expect($result['protection']['status'])->toBe('empty')
            ->and($result['protection']['details']['life'])->toBe(0)
            ->and($result['protection']['details']['ci'])->toBe(0)
            ->and($result['protection']['details']['ip'])->toBe(0)
            ->and($result['protection']['details']['disability'])->toBe(0)
            ->and($result['protection']['details']['sickness'])->toBe(0);
    });
});

describe('Skipped modules', function () {
    it('returns skipped status for explicitly skipped modules', function () {
        $user = User::factory()->create([
            'onboarding_skipped_steps' => ['protection', 'savings', 'investment', 'retirement', 'estate'],
        ]);

        $result = $this->service->getModuleStatus($user);

        expect($result['protection']['status'])->toBe('skipped')
            ->and($result['protection']['details'])->toBe([])
            ->and($result['savings']['status'])->toBe('skipped')
            ->and($result['investment']['status'])->toBe('skipped')
            ->and($result['retirement']['status'])->toBe('skipped')
            ->and($result['estate']['status'])->toBe('skipped');
    });
});

describe('Household (spouse) merging', function () {
    it('merges spouse data when spouse_id is linked', function () {
        $spouse = User::factory()->create();
        $user = User::factory()->create(['spouse_id' => $spouse->id]);

        // Primary user: 1 life policy
        LifeInsurancePolicy::factory()->create(['user_id' => $user->id]);
        // Spouse: 1 life policy + 1 critical illness
        LifeInsurancePolicy::factory()->create(['user_id' => $spouse->id]);
        CriticalIllnessPolicy::factory()->create(['user_id' => $spouse->id]);

        // Primary: 1 cash account
        CashAccount::factory()->create(['user_id' => $user->id]);
        // Spouse: 1 cash account
        CashAccount::factory()->create(['user_id' => $spouse->id]);

        // Primary: 1 DC pension
        DCPension::factory()->create(['user_id' => $user->id]);
        // Spouse: 1 DC pension
        DCPension::factory()->create(['user_id' => $spouse->id]);

        $result = $this->service->getModuleStatus($user);

        // Protection: 2 life (1+1), 1 ci (0+1) => complete (life>0 && ci>0)
        expect($result['protection']['status'])->toBe('complete')
            ->and($result['protection']['details']['life'])->toBe(2)
            ->and($result['protection']['details']['ci'])->toBe(1);

        // Savings: 2 cash accounts (1+1) => partial (cash>0 but savings=0)
        expect($result['savings']['details']['cash'])->toBe(2);

        // Retirement: 2 DC pensions (1+1) => partial (no state pension / no profile)
        expect($result['retirement']['details']['dc'])->toBe(2);
    });
});

describe('Savings module status', function () {
    it('returns correct savings status including ISA count', function () {
        $user = User::factory()->create();

        CashAccount::factory()->create([
            'user_id' => $user->id,
            'current_balance' => 5000.00,
        ]);

        SavingsAccount::factory()->create([
            'user_id' => $user->id,
            'is_isa' => true,
            'current_balance' => 10000.00,
        ]);

        SavingsAccount::factory()->create([
            'user_id' => $user->id,
            'is_isa' => false,
            'current_balance' => 3000.00,
        ]);

        $result = $this->service->getModuleStatus($user);

        expect($result['savings']['status'])->toBe('complete')
            ->and($result['savings']['details']['cash'])->toBe(1)
            ->and($result['savings']['details']['savings'])->toBe(2)
            ->and($result['savings']['details']['isa'])->toBe(1)
            ->and($result['savings']['details']['cashTotal'])->toBe(5000.00)
            ->and($result['savings']['details']['savingsTotal'])->toBe(13000.00)
            ->and($result['savings']['details']['hasEmergencyFund'])->toBeTrue();
    });

    it('returns empty savings status with no accounts', function () {
        $user = User::factory()->create();

        $result = $this->service->getModuleStatus($user);

        expect($result['savings']['status'])->toBe('empty')
            ->and($result['savings']['details']['cash'])->toBe(0)
            ->and($result['savings']['details']['savings'])->toBe(0);
    });
});

describe('Investment module status', function () {
    it('returns correct investment status with risk profile check', function () {
        $user = User::factory()->create();

        $account = InvestmentAccount::factory()->create([
            'user_id' => $user->id,
            'current_value' => 50000.00,
        ]);

        Holding::factory()->create([
            'holdable_id' => $account->id,
            'holdable_type' => InvestmentAccount::class,
        ]);

        RiskProfile::factory()->create(['user_id' => $user->id]);

        $result = $this->service->getModuleStatus($user);

        expect($result['investment']['status'])->toBe('complete')
            ->and($result['investment']['details']['accounts'])->toBe(1)
            ->and($result['investment']['details']['holdings'])->toBe(1)
            ->and($result['investment']['details']['totalValue'])->toBe(50000.00)
            ->and($result['investment']['details']['hasRiskProfile'])->toBeTrue();
    });

    it('returns partial investment status without risk profile', function () {
        $user = User::factory()->create();

        // Create account without triggering observers (which auto-create a RiskProfile)
        InvestmentAccount::withoutEvents(function () use ($user) {
            InvestmentAccount::factory()->create([
                'user_id' => $user->id,
                'current_value' => 25000.00,
            ]);
        });

        $result = $this->service->getModuleStatus($user);

        expect($result['investment']['status'])->toBe('partial')
            ->and($result['investment']['details']['accounts'])->toBe(1)
            ->and($result['investment']['details']['hasRiskProfile'])->toBeFalse();
    });
});

describe('Retirement module status', function () {
    it('returns correct retirement status with pensions and profile', function () {
        $user = User::factory()->create();

        DCPension::factory()->create([
            'user_id' => $user->id,
            'current_fund_value' => 150000.00,
        ]);

        DBPension::factory()->create(['user_id' => $user->id]);
        StatePension::factory()->create(['user_id' => $user->id]);
        RetirementProfile::factory()->create(['user_id' => $user->id]);

        $result = $this->service->getModuleStatus($user);

        expect($result['retirement']['status'])->toBe('complete')
            ->and($result['retirement']['details']['dc'])->toBe(1)
            ->and($result['retirement']['details']['db'])->toBe(1)
            ->and($result['retirement']['details']['hasStatePension'])->toBeTrue()
            ->and($result['retirement']['details']['hasRetirementProfile'])->toBeTrue()
            ->and($result['retirement']['details']['dcTotal'])->toBe(150000.00);
    });

    it('returns partial retirement status without state pension', function () {
        $user = User::factory()->create();

        DCPension::factory()->create(['user_id' => $user->id]);

        $result = $this->service->getModuleStatus($user);

        expect($result['retirement']['status'])->toBe('partial')
            ->and($result['retirement']['details']['dc'])->toBe(1)
            ->and($result['retirement']['details']['hasStatePension'])->toBeFalse()
            ->and($result['retirement']['details']['hasRetirementProfile'])->toBeFalse();
    });
});

describe('Estate module status', function () {
    it('returns correct estate status with will and LPA', function () {
        $user = User::factory()->create();
        $household = Household::factory()->create();

        Will::factory()->withWill()->create(['user_id' => $user->id]);
        LastingPowerOfAttorney::factory()->create(['user_id' => $user->id]);
        Trust::factory()->create([
            'user_id' => $user->id,
            'household_id' => $household->id,
            'current_value' => 200000.00,
        ]);
        Gift::factory()->create([
            'user_id' => $user->id,
            'gift_type' => 'pet',
            'status' => 'within_7_years',
        ]);
        Asset::factory()->create([
            'user_id' => $user->id,
            'asset_type' => 'property',
            'ownership_type' => 'individual',
        ]);

        $result = $this->service->getModuleStatus($user);

        expect($result['estate']['status'])->toBe('complete')
            ->and($result['estate']['details']['hasWill'])->toBeTrue()
            ->and($result['estate']['details']['lpa'])->toBe(1)
            ->and($result['estate']['details']['trustCount'])->toBe(1)
            ->and($result['estate']['details']['trustValue'])->toBe(200000.00)
            ->and($result['estate']['details']['giftCount'])->toBe(1)
            ->and($result['estate']['details']['assetCount'])->toBe(1);
    });

    it('returns partial estate status with will but no LPA', function () {
        $user = User::factory()->create();

        Will::factory()->withWill()->create(['user_id' => $user->id]);

        $result = $this->service->getModuleStatus($user);

        expect($result['estate']['status'])->toBe('partial')
            ->and($result['estate']['details']['hasWill'])->toBeTrue()
            ->and($result['estate']['details']['lpa'])->toBe(0);
    });

    it('returns empty estate status with no data', function () {
        $user = User::factory()->create();

        $result = $this->service->getModuleStatus($user);

        expect($result['estate']['status'])->toBe('empty')
            ->and($result['estate']['details']['hasWill'])->toBeFalse()
            ->and($result['estate']['details']['lpa'])->toBe(0)
            ->and($result['estate']['details']['trustCount'])->toBe(0)
            ->and($result['estate']['details']['giftCount'])->toBe(0)
            ->and($result['estate']['details']['assetCount'])->toBe(0);
    });
});
