<?php

declare(strict_types=1);

use App\Models\FamilyMember;
use App\Models\Household;
use App\Models\Investment\InvestmentAccount;
use App\Models\Property;
use App\Models\User;
use App\Services\Benefits\ChildBenefitService;
use App\Services\Shared\CrossModuleAssetAggregator;
use App\Services\TaxConfigService;
use App\Services\UKTaxCalculator;
use App\Services\UserProfile\UserProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $aggregator = new CrossModuleAssetAggregator;

    // Mock TaxConfigService for UKTaxCalculator
    $mockTaxConfig = Mockery::mock(TaxConfigService::class);
    $mockTaxConfig->shouldReceive('getIncomeTax')
        ->andReturn([
            'personal_allowance' => 12570,
            'bands' => [
                ['name' => 'Basic Rate', 'lower_limit' => 12570, 'upper_limit' => 50270, 'min' => 0, 'max' => 37700, 'rate' => 20],
                ['name' => 'Higher Rate', 'lower_limit' => 50270, 'upper_limit' => 125140, 'min' => 37700, 'max' => 125140, 'rate' => 40],
                ['name' => 'Additional Rate', 'lower_limit' => 125140, 'upper_limit' => null, 'min' => 125140, 'max' => null, 'rate' => 45],
            ],
        ]);
    $mockTaxConfig->shouldReceive('getNationalInsurance')
        ->andReturn([
            'class_1' => [
                'employee' => [
                    'primary_threshold' => 12570,
                    'upper_earnings_limit' => 50270,
                    'main_rate' => 0.08,
                    'additional_rate' => 0.02,
                ],
            ],
            'class_4' => [
                'lower_profits_limit' => 12570,
                'upper_profits_limit' => 50270,
                'main_rate' => 0.09,
                'additional_rate' => 0.02,
            ],
        ]);
    $mockTaxConfig->shouldReceive('getDividendTax')
        ->andReturn([
            'allowance' => 500,
            'basic_rate' => 8.75,        // Flattened structure with percentages
            'higher_rate' => 33.75,
            'additional_rate' => 39.35,
        ]);
    $mockTaxConfig->shouldReceive('getTaxYear')
        ->andReturn('2025/26');
    $mockTaxConfig->shouldReceive('getChildBenefit')
        ->andReturn([
            'eldest_child_weekly' => 26.05,
            'additional_child_weekly' => 17.25,
            'eldest_child_annual' => 1354.60,
            'additional_child_annual' => 897.00,
            'high_income_charge_threshold' => 60000,
            'high_income_full_clawback' => 80000,
            'clawback_increment' => 200,
        ]);

    $taxCalculator = new UKTaxCalculator($mockTaxConfig);
    $childBenefitService = new ChildBenefitService($mockTaxConfig);
    $this->service = new UserProfileService($aggregator, $taxCalculator, $childBenefitService);

    // Create a household
    $this->household = Household::factory()->create();

    // Create a test user
    $this->user = User::factory()->create([
        'household_id' => $this->household->id,
        'first_name' => 'Test',
        'middle_name' => null,
        'surname' => 'User',
        'email' => 'test@example.com',
        'date_of_birth' => '1985-05-15',
        'gender' => 'male',
        'marital_status' => 'single',
        'occupation' => 'Software Engineer',
        'annual_employment_income' => 75000.00,
        'annual_rental_income' => 12000.00,
        'annual_dividend_income' => 3000.00,
    ]);

    // Create family members
    $this->familyMember = FamilyMember::factory()->create([
        'user_id' => $this->user->id,
        'relationship' => 'child',
        'name' => 'Test Child',
    ]);

    // Create assets - include monthly_rental_income so rental income is calculated from properties
    // Set all expense fields to 0 so taxable rental income = gross rental income
    $this->property = Property::factory()->create([
        'user_id' => $this->user->id,
        'current_value' => 500000.00,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.00,
        'property_type' => 'buy_to_let',
        'monthly_rental_income' => 1000.00, // 12000/year to match user's annual_rental_income
        'monthly_gas' => 0,
        'monthly_electricity' => 0,
        'monthly_water' => 0,
        'monthly_building_insurance' => 0,
        'monthly_contents_insurance' => 0,
        'monthly_service_charge' => 0,
        'managing_agent_fee' => 0,
    ]);

    $this->investment = InvestmentAccount::factory()->create([
        'user_id' => $this->user->id,
        'current_value' => 50000.00,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.00,
    ]);
});

afterEach(function () {
    Mockery::close();
});

describe('getCompleteProfile', function () {
    it('returns complete user profile with all sections', function () {
        $profile = $this->service->getCompleteProfile($this->user);

        expect($profile)->toBeArray();
        expect($profile)->toHaveKeys([
            'personal_info',
            'income_occupation',
            'family_members',
            'assets_summary',
            'liabilities_summary',
        ]);
    });

    it('returns correct personal information', function () {
        $profile = $this->service->getCompleteProfile($this->user);

        expect($profile['personal_info'])->toHaveKeys([
            'id',
            'name',
            'email',
            'date_of_birth',
            'gender',
            'marital_status',
        ]);

        expect($profile['personal_info']['name'])->toBe('Test User');
        expect($profile['personal_info']['email'])->toBe('test@example.com');
    });

    it('returns correct income and occupation data', function () {
        $profile = $this->service->getCompleteProfile($this->user);

        expect($profile['income_occupation'])->toHaveKeys([
            'occupation',
            'annual_employment_income',
            'annual_rental_income',
            'annual_dividend_income',
            'total_annual_income',
        ]);

        expect($profile['income_occupation']['occupation'])->toBe('Software Engineer');
        // Service returns numeric value, not string
        expect((float) $profile['income_occupation']['annual_employment_income'])->toBe(75000.0);
        expect($profile['income_occupation']['total_annual_income'])->toBe(90000.0);
    });

    it('calculates total annual income correctly', function () {
        $profile = $this->service->getCompleteProfile($this->user);

        $expectedTotal =
            75000.00 + // employment
            0.00 +     // self-employment
            12000.00 + // rental
            3000.00 +  // dividend
            0.00;      // other

        expect($profile['income_occupation']['total_annual_income'])->toBe($expectedTotal);
    });

    it('includes family members in profile', function () {
        $profile = $this->service->getCompleteProfile($this->user);

        // Service returns array, not Collection
        expect($profile['family_members'])->toBeArray();
        expect($profile['family_members'])->toHaveCount(1);
        expect($profile['family_members'][0]['name'])->toBe('Test Child');
    });

    it('returns empty array when user has no family members', function () {
        // Delete family members
        FamilyMember::where('user_id', $this->user->id)->delete();

        $profile = $this->service->getCompleteProfile($this->user);

        // Service returns array, not Collection
        expect($profile['family_members'])->toBeArray();
        expect($profile['family_members'])->toHaveCount(0);
    });

    it('includes assets summary in profile', function () {
        $profile = $this->service->getCompleteProfile($this->user);

        expect($profile['assets_summary'])->toBeArray();
        expect($profile['assets_summary'])->toHaveKeys([
            'cash',
            'investments',
            'properties',
            'business',
            'chattels',
            'pensions',
            'total',
        ]);
    });

    it('calculates assets summary correctly', function () {
        $profile = $this->service->getCompleteProfile($this->user);

        expect($profile['assets_summary']['properties']['total'])->toBe(500000.00);
        expect($profile['assets_summary']['investments']['total'])->toBe(50000.00);
        expect($profile['assets_summary']['total'])->toBeGreaterThan(0);
    });
});

describe('updatePersonalInfo', function () {
    it('updates personal information successfully', function () {
        $updateData = [
            'first_name' => 'Updated',
            'surname' => 'Person',
            'date_of_birth' => '1990-01-01',
            'gender' => 'female',
            'marital_status' => 'married',
            'city' => 'Manchester',
        ];

        $updatedUser = $this->service->updatePersonalInfo($this->user, $updateData);

        expect($updatedUser->first_name)->toBe('Updated');
        expect($updatedUser->surname)->toBe('Person');
        expect($updatedUser->date_of_birth->format('Y-m-d'))->toBe('1990-01-01');
        expect($updatedUser->city)->toBe('Manchester');
    });

    it('persists updated information to database', function () {
        $updateData = [
            'first_name' => 'Database',
            'surname' => 'TestUser',
            'city' => 'Birmingham',
        ];

        $this->service->updatePersonalInfo($this->user, $updateData);

        // Refresh user from database
        $this->user->refresh();

        expect($this->user->first_name)->toBe('Database');
        expect($this->user->surname)->toBe('TestUser');
        expect($this->user->city)->toBe('Birmingham');
    });

    it('returns updated User model', function () {
        $updateData = ['first_name' => 'Changed'];

        $result = $this->service->updatePersonalInfo($this->user, $updateData);

        expect($result)->toBeInstanceOf(User::class);
        expect($result->id)->toBe($this->user->id);
    });
});

describe('updateIncomeOccupation', function () {
    it('updates income and occupation information successfully', function () {
        $updateData = [
            'occupation' => 'Senior Developer',
            'employer' => 'New Company Ltd',
            'annual_employment_income' => 95000.00,
            'annual_rental_income' => 15000.00,
        ];

        $updatedUser = $this->service->updateIncomeOccupation($this->user, $updateData);

        expect($updatedUser->occupation)->toBe('Senior Developer');
        expect($updatedUser->employer)->toBe('New Company Ltd');
        // Model casts return float, not string
        expect((float) $updatedUser->annual_employment_income)->toBe(95000.0);
    });

    it('persists updated income information to database', function () {
        $updateData = [
            'annual_employment_income' => 100000.00,
            'annual_self_employment_income' => 20000.00,
        ];

        $this->service->updateIncomeOccupation($this->user, $updateData);

        // Refresh user from database
        $this->user->refresh();

        // Model casts return float, not string
        expect((float) $this->user->annual_employment_income)->toBe(100000.0);
        expect((float) $this->user->annual_self_employment_income)->toBe(20000.0);
    });

    it('returns updated User model', function () {
        $updateData = ['occupation' => 'Test'];

        $result = $this->service->updateIncomeOccupation($this->user, $updateData);

        expect($result)->toBeInstanceOf(User::class);
        expect($result->id)->toBe($this->user->id);
    });
});
