<?php

declare(strict_types=1);

use App\Models\Investment\RiskProfile;
use App\Models\User;
use App\Services\NetWorth\NetWorthService;
use App\Services\Risk\AutoRiskCalculator;

beforeEach(function () {
    $this->netWorthService = Mockery::mock(NetWorthService::class);
    $this->netWorthService->shouldReceive('calculateNetWorth')->andReturn([
        'net_worth' => 500000,
        'total_assets' => 600000,
        'total_liabilities' => 100000,
    ]);

    $this->calculator = new AutoRiskCalculator($this->netWorthService);
});

afterEach(function () {
    Mockery::close();
});

describe('Age Factor', function () {
    it('assigns high risk for users under 30', function () {
        $user = User::factory()->create([
            'date_of_birth' => now()->subYears(25)->toDateString(),
            'employment_status' => 'employed',
        ]);

        $result = $this->calculator->calculateRiskProfile($user);
        $ageFactor = collect($result['factor_breakdown'])->firstWhere('factor', 'age');

        expect($ageFactor)->not->toBeNull()
            ->and($ageFactor['level'])->toBe('high')
            ->and($ageFactor['raw_value'])->toBe(25);
    });

    it('assigns upper_medium risk for users aged 30-44', function () {
        $user = User::factory()->create([
            'date_of_birth' => now()->subYears(38)->toDateString(),
            'employment_status' => 'employed',
        ]);

        $result = $this->calculator->calculateRiskProfile($user);
        $ageFactor = collect($result['factor_breakdown'])->firstWhere('factor', 'age');

        expect($ageFactor['level'])->toBe('upper_medium');
    });

    it('assigns medium risk for users aged 45-54', function () {
        $user = User::factory()->create([
            'date_of_birth' => now()->subYears(50)->toDateString(),
            'employment_status' => 'employed',
        ]);

        $result = $this->calculator->calculateRiskProfile($user);
        $ageFactor = collect($result['factor_breakdown'])->firstWhere('factor', 'age');

        expect($ageFactor['level'])->toBe('medium');
    });

    it('assigns lower_medium risk for users aged 55-64', function () {
        $user = User::factory()->create([
            'date_of_birth' => now()->subYears(60)->toDateString(),
            'employment_status' => 'employed',
        ]);

        $result = $this->calculator->calculateRiskProfile($user);
        $ageFactor = collect($result['factor_breakdown'])->firstWhere('factor', 'age');

        expect($ageFactor['level'])->toBe('lower_medium');
    });

    it('assigns low risk for users aged 65+', function () {
        $user = User::factory()->create([
            'date_of_birth' => now()->subYears(70)->toDateString(),
            'employment_status' => 'retired',
        ]);

        $result = $this->calculator->calculateRiskProfile($user);
        $ageFactor = collect($result['factor_breakdown'])->firstWhere('factor', 'age');

        expect($ageFactor['level'])->toBe('low');
    });

    it('assigns medium risk when date of birth is not set', function () {
        $user = User::factory()->create([
            'date_of_birth' => null,
            'employment_status' => 'employed',
        ]);

        $result = $this->calculator->calculateRiskProfile($user);
        $ageFactor = collect($result['factor_breakdown'])->firstWhere('factor', 'age');

        expect($ageFactor['level'])->toBe('medium')
            ->and($ageFactor['raw_value'])->toBeNull();
    });
});

describe('Income Stability Factor', function () {
    it('assigns upper_medium for full-time employed', function () {
        $user = User::factory()->create([
            'date_of_birth' => now()->subYears(40)->toDateString(),
            'employment_status' => 'employed',
        ]);

        $result = $this->calculator->calculateRiskProfile($user);
        $factor = collect($result['factor_breakdown'])->firstWhere('factor', 'income_stability');

        expect($factor['level'])->toBe('upper_medium')
            ->and($factor['value'])->toBe('Stable');
    });

    it('assigns lower_medium for self-employed', function () {
        $user = User::factory()->create([
            'date_of_birth' => now()->subYears(40)->toDateString(),
            'employment_status' => 'self_employed',
        ]);

        $result = $this->calculator->calculateRiskProfile($user);
        $factor = collect($result['factor_breakdown'])->firstWhere('factor', 'income_stability');

        expect($factor['level'])->toBe('lower_medium')
            ->and($factor['value'])->toBe('Variable');
    });

    it('assigns lower_medium for other employment types', function () {
        $user = User::factory()->create([
            'date_of_birth' => now()->subYears(35)->toDateString(),
            'employment_status' => 'other',
        ]);

        $result = $this->calculator->calculateRiskProfile($user);
        $factor = collect($result['factor_breakdown'])->firstWhere('factor', 'income_stability');

        expect($factor['level'])->toBe('lower_medium');
    });

    it('assigns lower_medium for part-time workers', function () {
        $user = User::factory()->create([
            'date_of_birth' => now()->subYears(45)->toDateString(),
            'employment_status' => 'part_time',
        ]);

        $result = $this->calculator->calculateRiskProfile($user);
        $factor = collect($result['factor_breakdown'])->firstWhere('factor', 'income_stability');

        expect($factor['level'])->toBe('lower_medium')
            ->and($factor['value'])->toBe('Reduced');
    });

    it('assigns lower_medium for retired users', function () {
        $user = User::factory()->create([
            'date_of_birth' => now()->subYears(68)->toDateString(),
            'employment_status' => 'retired',
        ]);

        $result = $this->calculator->calculateRiskProfile($user);
        $factor = collect($result['factor_breakdown'])->firstWhere('factor', 'income_stability');

        expect($factor['level'])->toBe('lower_medium')
            ->and($factor['value'])->toBe('Fixed');
    });

    it('assigns low for unemployed users', function () {
        $user = User::factory()->create([
            'date_of_birth' => now()->subYears(30)->toDateString(),
            'employment_status' => 'unemployed',
        ]);

        $result = $this->calculator->calculateRiskProfile($user);
        $factor = collect($result['factor_breakdown'])->firstWhere('factor', 'income_stability');

        expect($factor['level'])->toBe('low')
            ->and($factor['value'])->toBe('None');
    });
});

describe('Risk Mismatch Detection', function () {
    it('detects mismatch when tolerance differs from capacity by more than 2 levels', function () {
        $profile = new RiskProfile([
            'risk_tolerance' => 'high',
            'risk_level' => 'low',
        ]);

        $result = $this->calculator->detectRiskMismatch($profile);

        expect($result)->not->toBeNull()
            ->and($result['type'])->toBe('mismatch')
            ->and($result['user_tolerance'])->toBe('high')
            ->and($result['calculated_capacity'])->toBe('low')
            ->and($result['difference'])->toBe(4);
    });

    it('detects mismatch when user tolerance is much lower than capacity', function () {
        $profile = new RiskProfile([
            'risk_tolerance' => 'low',
            'risk_level' => 'high',
        ]);

        $result = $this->calculator->detectRiskMismatch($profile);

        expect($result)->not->toBeNull()
            ->and($result['difference'])->toBe(4);
    });

    it('returns null when tolerance matches capacity exactly', function () {
        $profile = new RiskProfile([
            'risk_tolerance' => 'medium',
            'risk_level' => 'medium',
        ]);

        $result = $this->calculator->detectRiskMismatch($profile);

        expect($result)->toBeNull();
    });

    it('returns null when tolerance is within 2 levels of capacity', function () {
        $profile = new RiskProfile([
            'risk_tolerance' => 'upper_medium',
            'risk_level' => 'medium',
        ]);

        $result = $this->calculator->detectRiskMismatch($profile);

        expect($result)->toBeNull();
    });

    it('returns null when tolerance is 2 levels different', function () {
        $profile = new RiskProfile([
            'risk_tolerance' => 'high',
            'risk_level' => 'medium',
        ]);

        $result = $this->calculator->detectRiskMismatch($profile);

        expect($result)->toBeNull();
    });

    it('returns null when no tolerance is set', function () {
        $profile = new RiskProfile([
            'risk_tolerance' => null,
            'risk_level' => 'medium',
        ]);

        $result = $this->calculator->detectRiskMismatch($profile);

        expect($result)->toBeNull();
    });

    it('returns null when no risk level is set', function () {
        $profile = new RiskProfile([
            'risk_tolerance' => 'medium',
            'risk_level' => null,
        ]);

        $result = $this->calculator->detectRiskMismatch($profile);

        expect($result)->toBeNull();
    });
});

describe('Factor Count', function () {
    it('returns 9 factors in the breakdown', function () {
        $user = User::factory()->create([
            'date_of_birth' => now()->subYears(40)->toDateString(),
            'employment_status' => 'employed',
        ]);

        $result = $this->calculator->calculateRiskProfile($user);

        expect($result['factor_breakdown'])->toHaveCount(9);
    });

    it('includes age and income_stability factors', function () {
        $user = User::factory()->create([
            'date_of_birth' => now()->subYears(40)->toDateString(),
            'employment_status' => 'employed',
        ]);

        $result = $this->calculator->calculateRiskProfile($user);
        $factorNames = collect($result['factor_breakdown'])->pluck('factor')->toArray();

        expect($factorNames)->toContain('age')
            ->and($factorNames)->toContain('income_stability');
    });
});
