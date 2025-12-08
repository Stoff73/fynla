<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Mortgage;
use App\Models\Property;
use App\Models\User;
use App\Services\Property\PropertyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropertyServiceTest extends TestCase
{
    use RefreshDatabase;

    private PropertyService $propertyService;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->propertyService = new PropertyService;
        $this->user = User::factory()->create();
    }

    public function test_calculate_equity_with_no_mortgage(): void
    {
        $property = Property::factory()->create([
            'user_id' => $this->user->id,
            'ownership_type' => 'individual',
            'current_value' => 500000,
            'ownership_percentage' => 100,
        ]);

        $equity = $this->propertyService->calculateEquity($property);

        expect($equity)->toBe(500000.0);
    }

    public function test_calculate_equity_with_mortgage(): void
    {
        $property = Property::factory()->create([
            'user_id' => $this->user->id,
            'ownership_type' => 'individual',
            'current_value' => 500000,
            'ownership_percentage' => 100,
        ]);

        Mortgage::factory()->create([
            'property_id' => $property->id,
            'user_id' => $this->user->id,
            'outstanding_balance' => 200000,
        ]);

        $equity = $this->propertyService->calculateEquity($property);

        expect($equity)->toBe(300000.0);
    }

    public function test_calculate_equity_with_joint_ownership(): void
    {
        // Note: Values in database are already stored as user's share
        // So we store the user's 50% share directly
        $property = Property::factory()->create([
            'user_id' => $this->user->id,
            'ownership_type' => 'joint',
            'current_value' => 300000, // User's 50% share of £600k
            'ownership_percentage' => 50,
        ]);

        Mortgage::factory()->create([
            'property_id' => $property->id,
            'user_id' => $this->user->id,
            'outstanding_balance' => 100000, // User's 50% share of £200k mortgage
        ]);

        $equity = $this->propertyService->calculateEquity($property);

        // Equity = £300k (user's share value) - £100k (user's share mortgage) = £200k
        expect($equity)->toBe(200000.0);
    }

    public function test_calculate_total_monthly_costs(): void
    {
        $property = Property::factory()->create([
            'user_id' => $this->user->id,
            'monthly_council_tax' => 150,
            'monthly_gas' => 80,
            'monthly_electricity' => 100,
            'monthly_water' => 40,
            'monthly_building_insurance' => 30,
            'monthly_contents_insurance' => 20,
            'monthly_service_charge' => 100,
            'monthly_maintenance_reserve' => 50,
            'other_monthly_costs' => 30,
        ]);

        $totalCosts = $this->propertyService->calculateTotalMonthlyCosts($property);

        // 150 + 80 + 100 + 40 + 30 + 20 + 100 + 50 + 30 = 600
        expect($totalCosts)->toBe(600.0);
    }

    public function test_calculate_total_monthly_costs_with_null_values(): void
    {
        $property = Property::factory()->create([
            'user_id' => $this->user->id,
            'monthly_council_tax' => 150,
            'monthly_gas' => null,
            'monthly_electricity' => null,
            'monthly_water' => null,
            'monthly_building_insurance' => null,
            'monthly_contents_insurance' => null,
            'monthly_service_charge' => null,
            'monthly_maintenance_reserve' => null,
            'other_monthly_costs' => null,
        ]);

        $totalCosts = $this->propertyService->calculateTotalMonthlyCosts($property);

        expect($totalCosts)->toBe(150.0);
    }

    public function test_calculate_net_rental_yield_for_btl(): void
    {
        $property = Property::factory()->create([
            'user_id' => $this->user->id,
            'ownership_type' => 'individual',
            'property_type' => 'buy_to_let',
            'current_value' => 200000,
            'monthly_rental_income' => 1000, // £1k/month = £12k/year gross
            'monthly_service_charge' => 50,
            'monthly_building_insurance' => 35,
        ]);

        $netYield = $this->propertyService->calculateNetRentalYield($property);

        // Income: £1000/month = £12,000/year at 100% occupancy
        // Costs: £50 + £35 = £85/month
        // Net monthly: £1000 - £85 = £915
        // Net annual: £915 * 12 = £10,980
        // Yield: (£10,980 / £200,000) * 100 = 5.49%
        expect($netYield)->toBeGreaterThan(5.0);
        expect($netYield)->toBeLessThan(6.0);
    }

    public function test_calculate_net_rental_yield_returns_zero_for_zero_value(): void
    {
        $property = Property::factory()->create([
            'user_id' => $this->user->id,
            'property_type' => 'buy_to_let',
            'current_value' => 0,
            'monthly_rental_income' => 1000,
        ]);

        $netYield = $this->propertyService->calculateNetRentalYield($property);

        expect($netYield)->toBe(0.0);
    }

    public function test_get_property_summary(): void
    {
        $property = Property::factory()->create([
            'user_id' => $this->user->id,
            'current_value' => 400000,
            'ownership_percentage' => 100,
        ]);

        Mortgage::factory()->create([
            'property_id' => $property->id,
            'user_id' => $this->user->id,
            'outstanding_balance' => 150000,
        ]);

        $summary = $this->propertyService->getPropertySummary($property);

        expect($summary)->toHaveKeys([
            'id',
            'property_type',
            'address',
            'current_value',
            'equity',
            'mortgage_balance',
            'ownership_percentage',
        ]);

        expect($summary['current_value'])->toBe(400000.0);
        expect($summary['equity'])->toBe(250000.0);
        expect($summary['mortgage_balance'])->toBe(150000.0);
    }

    public function test_calculate_equity_never_goes_negative(): void
    {
        $property = Property::factory()->create([
            'user_id' => $this->user->id,
            'current_value' => 100000,
            'ownership_percentage' => 100,
        ]);

        Mortgage::factory()->create([
            'property_id' => $property->id,
            'user_id' => $this->user->id,
            'outstanding_balance' => 150000, // Negative equity scenario
        ]);

        $equity = $this->propertyService->calculateEquity($property);

        // Even with negative equity, should return 0 not negative
        expect($equity)->toBeGreaterThanOrEqual(0.0);
    }
}
