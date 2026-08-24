<?php

declare(strict_types=1);

use App\Models\DBPension;
use App\Models\DCPension;
use App\Models\FamilyMember;
use App\Models\Property;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Coordination\HouseholdPlanningService;
use App\Services\Stores\MortgageStore;
use App\Services\Stores\PropertyStore;
use App\Services\TaxConfigService;

function createHouseholdService(array $ihtOverrides = []): HouseholdPlanningService
{
    $taxConfig = Mockery::mock(TaxConfigService::class);
    $taxConfig->shouldReceive('getInheritanceTax')->andReturn([
        'nil_rate_band' => 325000,
        'residence_nil_rate_band' => 175000,
        // W-0154: the real configuration key is `standard_rate`. This mock said `rate`,
        // which mirrored the defect in the service rather than catching it — both
        // agreed on a key that has never existed in TaxConfigService::getInheritanceTax().
        'standard_rate' => 0.40,
        'rnrb_taper_rate' => 0.5,
        ...$ihtOverrides,
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

    return new HouseholdPlanningService($taxConfig, app(PropertyStore::class), app(MortgageStore::class));
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
    Property::factory()->create([
        'user_id' => $user->id,
        'property_type' => 'main_residence',
        'current_value' => 450000,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ]);
    FamilyMember::factory()->create([
        'user_id' => $user->id,
        'relationship' => 'child',
        'first_name' => 'Oliver',
    ]);
    Property::factory()->create([
        'user_id' => $spouse->id,
        'property_type' => 'main_residence',
        'current_value' => 450000,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ]);
    FamilyMember::factory()->create([
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
            DCPension::factory()->create([
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

    /**
     * W-0154. `$ihtConfig['rate'] ?? 0.40` at two sites — and `rate` is not a key
     * `TaxConfigService::getInheritanceTax()` has ever returned. The configured key is
     * `standard_rate`, which `IHTCalculationService`, `PersonalizedGiftingStrategyService`
     * and `PersonalizedTrustStrategyService` all read correctly.
     *
     * So the rate was not merely unread, it was **unreachable**: every Inheritance Tax
     * figure this service produced used a literal that no configuration change could
     * move. These tests fail if the key regresses, because they change the configured
     * rate and require the answer to follow.
     */
    describe('Inheritance Tax rate is read from configuration (W-0154, Rule 2)', function () {
        it('follows a change to the configured standard rate for a single person', function () {
            [$user] = createMarriedCouple();
            $single = User::factory()->create([
                'marital_status' => 'single',
                'annual_employment_income' => 0,
            ]);
            SavingsAccount::factory()->create([
                'user_id' => $single->id,
                'current_balance' => 1_325_000,
                'ownership_type' => 'individual',
            ]);

            $atForty = createHouseholdService()->modelDeathOfSpouseScenario($single);
            $atTen = createHouseholdService(['standard_rate' => 0.10])
                ->modelDeathOfSpouseScenario($single);

            // 1,325,000 - 325,000 nil rate band = 1,000,000 chargeable.
            expect($atForty['iht_first_death'])->toBe(400000.0)
                ->and($atTen['iht_first_death'])->toBe(100000.0);
        });

        it('follows a change to the configured rate on the married second-death figure', function () {
            [$user] = createMarriedCouple();
            SavingsAccount::factory()->create([
                'user_id' => $user->id,
                'current_balance' => 2_000_000,
                'ownership_type' => 'individual',
            ]);

            $atForty = createHouseholdService()->modelDeathOfSpouseScenario($user, 'primary');
            $atTen = createHouseholdService(['standard_rate' => 0.10])
                ->modelDeathOfSpouseScenario($user, 'primary');

            expect($atForty['iht_second_death'])->toBeGreaterThan(0.0)
                ->and($atTen['iht_second_death'])
                ->toBe(round($atForty['iht_second_death'] / 4, 2));
        });

        /**
         * The residence nil rate band taper was a hardcoded `/ 2` while
         * `rnrb_taper_rate` sat configured and read by IHTCalculationService:1266.
         */
        it('takes the residence nil rate band taper rate from configuration', function () {
            $single = User::factory()->create([
                'marital_status' => 'single',
                'annual_employment_income' => 0,
            ]);
            Property::factory()->create([
                'user_id' => $single->id,
                'property_type' => 'main_residence',
                'current_value' => 2_400_000,
                'ownership_type' => 'individual',
            ]);
            FamilyMember::factory()->create([
                'user_id' => $single->id,
                'relationship' => 'child',
            ]);

            $atHalf = createHouseholdService()->modelDeathOfSpouseScenario($single);
            $atZero = createHouseholdService(['rnrb_taper_rate' => 0.0])
                ->modelDeathOfSpouseScenario($single);

            // A zero taper leaves the residence allowance intact, so less is chargeable.
            expect($atZero['iht_first_death'])->toBeLessThan($atHalf['iht_first_death']);
        });
    });

    /**
     * W-0154 R3. The Defined Benefit spouse benefit was a percentage of
     * `expected_annual_pension` — a column that has never existed on `db_pensions` —
     * so it was £0 for every household in the application. `DeathOfSpouseScenario.vue`
     * guards the row on `> 0`, so a surviving spouse was silently never told about
     * pension income they would actually receive.
     *
     * It also made W-0030 unobservable through this path: `fix-batch-C` corrected the
     * `spouse_pension_percent` unit convention with a migration the same day, and this
     * multiplied the corrected percentage into a null.
     */
    describe('Defined Benefit spouse benefit (W-0154 R3)', function () {
        it('prefers the derived column the store already computes', function () {
            $service = createHouseholdService();
            [$user] = createMarriedCouple();

            DBPension::factory()->create([
                'user_id' => $user->id,
                'accrued_annual_pension' => 35000,
                'spouse_pension_percent' => 50,
                // PensionDerivedColumnCalculator::calculateDb() writes this. Deliberately
                // set to something the fallback arithmetic would NOT produce, so the test
                // proves the derived column is read rather than recomputed (Rule 20).
                'spouse_pension_projected_gbp' => 12345.67,
            ]);

            $result = $service->modelDeathOfSpouseScenario($user, 'primary');

            expect($result['income_impact']['db_spouse_benefit'])->toBe(12345.67);
        });

        /**
         * The state most existing rows are in: the derived column is only written when
         * a write triggers recalculation, so every pension saved before that is null.
         * A one-word column swap would have replaced "always zero" with "zero until
         * someone happens to re-save the record", which looks fixed and is not.
         */
        it('falls back to the accrued figure when the derived column has never been written', function () {
            $service = createHouseholdService();
            [$user] = createMarriedCouple();

            DBPension::factory()->create([
                'user_id' => $user->id,
                'accrued_annual_pension' => 35000,
                'spouse_pension_percent' => 50,
                'projected_annual_pension_at_nra_gbp' => null,
                'spouse_pension_projected_gbp' => null,
            ]);

            $result = $service->modelDeathOfSpouseScenario($user, 'primary');

            expect($result['income_impact']['db_spouse_benefit'])->toBe(17500.0);
        });

        it('prefers the revalued figure over the accrued one in the fallback', function () {
            $service = createHouseholdService();
            [$user] = createMarriedCouple();

            DBPension::factory()->create([
                'user_id' => $user->id,
                'accrued_annual_pension' => 35000,
                'projected_annual_pension_at_nra_gbp' => 40000,
                'spouse_pension_percent' => 50,
                'spouse_pension_projected_gbp' => null,
            ]);

            $result = $service->modelDeathOfSpouseScenario($user, 'primary');

            expect($result['income_impact']['db_spouse_benefit'])->toBe(20000.0);
        });

        /**
         * The persona shape: Sarah Jones's NHS Pension Scheme records £35,000 accrued
         * and no spouse percentage, because the field only reached the form in W-0017.
         */
        it('applies the assumed half share when the scheme records no percentage', function () {
            $service = createHouseholdService();
            [$user] = createMarriedCouple();

            DBPension::factory()->create([
                'user_id' => $user->id,
                'scheme_name' => 'NHS Pension Scheme',
                'accrued_annual_pension' => 35000,
                'projected_annual_pension_at_nra_gbp' => 35000,
                'spouse_pension_percent' => null,
                'spouse_pension_projected_gbp' => null,
            ]);

            $result = $service->modelDeathOfSpouseScenario($user, 'primary');

            expect($result['income_impact']['db_spouse_benefit'])->toBe(17500.0);
        });

        it('contributes nothing when the scheme records no pension amount at all', function () {
            $service = createHouseholdService();
            [$user] = createMarriedCouple();

            DBPension::factory()->create([
                'user_id' => $user->id,
                'accrued_annual_pension' => null,
                'projected_annual_pension_at_nra_gbp' => null,
                'spouse_pension_percent' => 50,
                'spouse_pension_projected_gbp' => null,
            ]);

            $result = $service->modelDeathOfSpouseScenario($user, 'primary');

            expect($result['income_impact']['db_spouse_benefit'])->toBe(0.0);
        });

        it('carries the benefit into the income the survivor is left with', function () {
            // The figure is not decorative: it raises income_after and reduces
            // income_lost, both of which the widowhood card renders.
            $service = createHouseholdService();
            [$user] = createMarriedCouple();

            DBPension::factory()->create([
                'user_id' => $user->id,
                'accrued_annual_pension' => 35000,
                'projected_annual_pension_at_nra_gbp' => 35000,
                'spouse_pension_percent' => 50,
                'spouse_pension_projected_gbp' => null,
            ]);

            $result = $service->modelDeathOfSpouseScenario($user, 'primary');

            // Emily earns 32,000; James's deemed income is 90,000 (85,000 + 5,000).
            expect($result['income_impact']['income_after'])->toBe(49500.0)
                ->and($result['income_impact']['income_lost'])->toBe(72500.0);
        });

        it('sums every scheme rather than only the first', function () {
            $service = createHouseholdService();
            [$user] = createMarriedCouple();

            DBPension::factory()->create([
                'user_id' => $user->id,
                'accrued_annual_pension' => 35000,
                'projected_annual_pension_at_nra_gbp' => 35000,
                'spouse_pension_percent' => 50,
                'spouse_pension_projected_gbp' => null,
            ]);
            DBPension::factory()->create([
                'user_id' => $user->id,
                'accrued_annual_pension' => 10000,
                'projected_annual_pension_at_nra_gbp' => 10000,
                'spouse_pension_percent' => 25,
                'spouse_pension_projected_gbp' => null,
            ]);

            $result = $service->modelDeathOfSpouseScenario($user, 'primary');

            expect($result['income_impact']['db_spouse_benefit'])->toBe(20000.0);
        });
    });
});
