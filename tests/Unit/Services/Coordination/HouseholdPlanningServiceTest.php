<?php

declare(strict_types=1);

use App\Models\Property;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Coordination\HouseholdPlanningService;
use App\Services\TaxConfigService;

function createHouseholdService(): HouseholdPlanningService
{
    $taxConfig = Mockery::mock(TaxConfigService::class);
    $taxConfig->shouldReceive('getInheritanceTax')->andReturn([
        'nil_rate_band' => 325000,
        'residence_nil_rate_band' => 175000,
        'rate' => 0.40,
    ]);
    $taxConfig->shouldReceive('getIncomeTax')->andReturn([
        'personal_allowance' => 12570,
    ]);
    $taxConfig->shouldReceive('getISAAllowances')->andReturn([
        'annual_allowance' => 20000,
    ]);
    $taxConfig->shouldReceive('getPensionAllowances')->andReturn([
        'annual_allowance' => 60000,
    ]);

    return new HouseholdPlanningService($taxConfig);
}

function createMarriedCouple(): array
{
    $user = User::factory()->create([
        'first_name' => 'James',
        'surname' => 'Carter',
        'marital_status' => 'married',
        'annual_employment_income' => 85000,
        'annual_dividend_income' => 5000,
    ]);

    $spouse = User::factory()->create([
        'first_name' => 'Emily',
        'surname' => 'Carter',
        'marital_status' => 'married',
        'annual_employment_income' => 32000,
    ]);

    $user->spouse_id = $spouse->id;
    $user->save();
    $spouse->spouse_id = $user->id;
    $spouse->save();

    // Create main residence and child for RNRB qualification
    \App\Models\Property::factory()->create([
        'user_id' => $user->id,
        'property_type' => 'main_residence',
        'current_value' => 450000,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ]);
    \App\Models\FamilyMember::factory()->create([
        'user_id' => $user->id,
        'relationship' => 'child',
        'first_name' => 'Oliver',
    ]);
    \App\Models\Property::factory()->create([
        'user_id' => $spouse->id,
        'property_type' => 'main_residence',
        'current_value' => 450000,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ]);
    \App\Models\FamilyMember::factory()->create([
        'user_id' => $spouse->id,
        'relationship' => 'child',
        'first_name' => 'Oliver',
    ]);

    return [$user->fresh(), $spouse->fresh()];
}

afterEach(function () {
    Mockery::close();
});

describe('HouseholdPlanningService', function () {
    describe('calculateHouseholdNetWorth', function () {
        it('returns individual net worth for single user', function () {
            $service = createHouseholdService();
            $user = User::factory()->create(['marital_status' => 'single']);

            SavingsAccount::factory()->create([
                'user_id' => $user->id,
                'current_balance' => 25000,
                'ownership_type' => 'individual',
            ]);

            $result = $service->calculateHouseholdNetWorth($user);

            expect($result['has_spouse'])->toBeFalse();
            expect($result['total_assets'])->toBeGreaterThanOrEqual(25000);
            expect($result['spouse_share'])->toBe(0.0);
        });

        it('combines assets for married couple with data sharing', function () {
            $service = createHouseholdService();
            [$user, $spouse] = createMarriedCouple();

            SavingsAccount::factory()->create([
                'user_id' => $user->id,
                'current_balance' => 50000,
                'ownership_type' => 'individual',
            ]);

            SavingsAccount::factory()->create([
                'user_id' => $spouse->id,
                'current_balance' => 30000,
                'ownership_type' => 'individual',
            ]);

            $result = $service->calculateHouseholdNetWorth($user);

            expect($result['has_spouse'])->toBeTrue();
            expect($result['total_assets'])->toBeGreaterThanOrEqual(80000);
            expect($result['user_share'])->toBeGreaterThanOrEqual(50000);
            expect($result['spouse_share'])->toBeGreaterThanOrEqual(30000);
        });

        it('does not double count joint assets', function () {
            $service = createHouseholdService();
            [$user, $spouse] = createMarriedCouple();

            // createMarriedCouple() creates a 450k property for each spouse (900k total)
            // Now add a joint property - single record, 50/50 split
            Property::factory()->create([
                'user_id' => $user->id,
                'joint_owner_id' => $spouse->id,
                'current_value' => 500000,
                'ownership_type' => 'joint',
                'ownership_percentage' => 50,
            ]);

            $result = $service->calculateHouseholdNetWorth($user);

            // Total = 450k (user property) + 450k (spouse property) + 500k (joint) = 1,400,000
            // Joint property should NOT be double counted
            expect($result['total_assets'])->toBe(1400000.0);
        });
    });

    describe('generateSpousalOptimisations', function () {
        it('returns empty array when no spouse', function () {
            $service = createHouseholdService();
            $user = User::factory()->create(['marital_status' => 'single']);

            $result = $service->generateSpousalOptimisations($user);

            expect($result)->toBeEmpty();
        });

        it('generates recommendations when data sharing is enabled', function () {
            $service = createHouseholdService();
            [$user, $spouse] = createMarriedCouple();

            $result = $service->generateSpousalOptimisations($user);

            // Should have pension recommendation (different tax bands)
            expect($result)->toBeArray();
            $types = array_column($result, 'type');
            expect($types)->toContain('pension_contribution');
        });

        it('recommends pension contribution for higher rate taxpayer', function () {
            $service = createHouseholdService();
            [$user, $spouse] = createMarriedCouple();

            $result = $service->generateSpousalOptimisations($user);

            $pensionRec = collect($result)->firstWhere('type', 'pension_contribution');
            expect($pensionRec)->not->toBeNull();
            expect($pensionRec['potential_savings'])->toBeGreaterThan(0);
            expect($pensionRec['description'])->toContain('James');
        });
    });

    describe('modelDeathOfSpouseScenario', function () {
        it('returns individual scenario for single user', function () {
            $service = createHouseholdService();
            $user = User::factory()->create(['marital_status' => 'single']);

            $result = $service->modelDeathOfSpouseScenario($user);

            expect($result['scenario'])->toBe('Individual estate analysis');
            expect($result['survivor_name'])->toBeNull();
        });

        it('calculates NRB transfer on first death', function () {
            $service = createHouseholdService();
            [$user, $spouse] = createMarriedCouple();

            $result = $service->modelDeathOfSpouseScenario($user, 'primary');

            // Spousal exemption: no IHT on first death
            expect($result['iht_first_death'])->toBe(0.0);

            // Full NRB transfers to surviving spouse
            expect($result['nrb_transferred'])->toBe(325000.0);
            expect($result['rnrb_transferred'])->toBe(175000.0);
        });

        it('calculates surviving spouse total with combined allowances', function () {
            $service = createHouseholdService();
            [$user, $spouse] = createMarriedCouple();

            $result = $service->modelDeathOfSpouseScenario($user, 'primary');

            // Combined allowances: 2x NRB + 2x RNRB = 1,000,000
            expect($result['total_allowances_on_second_death'])->toBe(1000000.0);
        });

        it('includes pension death benefits', function () {
            $service = createHouseholdService();
            [$user, $spouse] = createMarriedCouple();

            // Create DC pension for user
            \App\Models\DCPension::factory()->create([
                'user_id' => $user->id,
                'current_fund_value' => 250000,
                'scheme_name' => 'Workplace Pension',
            ]);

            $result = $service->modelDeathOfSpouseScenario($user, 'primary');

            expect($result['pension_death_benefits']['dc_total'])->toBe(250000.0);
            expect($result['pension_death_benefits']['details'])->toHaveCount(1);
        });

        it('accepts partner parameter for spouse death', function () {
            $service = createHouseholdService();
            [$user, $spouse] = createMarriedCouple();

            $result = $service->modelDeathOfSpouseScenario($user, 'partner');

            expect($result['deceased_name'])->toBe('Emily');
            expect($result['survivor_name'])->toBe('James');
        });
    });
});
