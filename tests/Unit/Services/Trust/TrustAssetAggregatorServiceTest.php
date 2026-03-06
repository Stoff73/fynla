<?php

declare(strict_types=1);

use App\Models\BusinessInterest;
use App\Models\CashAccount;
use App\Models\Estate\Trust;
use App\Models\Household;
use App\Models\Property;
use App\Models\User;
use App\Services\Trust\TrustAssetAggregatorService;

beforeEach(function () {
    $this->service = new TrustAssetAggregatorService;
    $this->user = User::factory()->create();
    $this->household = Household::factory()->create();
    $this->trust = Trust::factory()->create([
        'user_id' => $this->user->id,
        'household_id' => $this->household->id,
        'trust_name' => 'Test Family Trust',
        'trust_type' => 'discretionary',
    ]);
});

it('aggregates multiple asset types for a trust', function () {
    Property::factory()->create([
        'user_id' => $this->user->id,
        'trust_id' => $this->trust->id,
        'current_value' => 500000,
        'ownership_percentage' => 100,
    ]);

    CashAccount::factory()->create([
        'user_id' => $this->user->id,
        'trust_id' => $this->trust->id,
        'current_balance' => 50000,
        'ownership_percentage' => 100,
    ]);

    BusinessInterest::factory()->create([
        'user_id' => $this->user->id,
        'trust_id' => $this->trust->id,
        'current_valuation' => 200000,
        'ownership_percentage' => 100,
    ]);

    $result = $this->service->aggregateAssetsForTrust($this->trust);

    expect($result)->toHaveKeys(['assets', 'total_value', 'asset_count', 'breakdown']);
    expect($result['asset_count'])->toBe(3);
    expect((float) $result['total_value'])->toBe(750000.0);
});

it('calculates correct total value with partial ownership', function () {
    Property::factory()->create([
        'user_id' => $this->user->id,
        'trust_id' => $this->trust->id,
        'current_value' => 500000,
        'ownership_percentage' => 50,
    ]);

    CashAccount::factory()->create([
        'user_id' => $this->user->id,
        'trust_id' => $this->trust->id,
        'current_balance' => 100000,
        'ownership_percentage' => 75,
    ]);

    $result = $this->service->aggregateAssetsForTrust($this->trust);

    // (500000 * 0.5) + (100000 * 0.75) = 250000 + 75000 = 325000
    expect((float) $result['total_value'])->toBe(325000.0);
    expect($result['asset_count'])->toBe(2);
});

it('returns empty assets when trust has no assets', function () {
    $result = $this->service->aggregateAssetsForTrust($this->trust);

    expect($result['asset_count'])->toBe(0);
    expect((float) $result['total_value'])->toBe(0.0);
    expect($result['assets']['properties'])->toBeEmpty();
    expect($result['assets']['investment_accounts'])->toBeEmpty();
    expect($result['assets']['cash_accounts'])->toBeEmpty();
    expect($result['assets']['business_interests'])->toBeEmpty();
    expect($result['assets']['chattels'])->toBeEmpty();
});

it('creates correct value breakdown by asset type', function () {
    Property::factory()->create([
        'user_id' => $this->user->id,
        'trust_id' => $this->trust->id,
        'current_value' => 400000,
        'ownership_percentage' => 100,
    ]);

    CashAccount::factory()->create([
        'user_id' => $this->user->id,
        'trust_id' => $this->trust->id,
        'current_balance' => 100000,
        'ownership_percentage' => 100,
    ]);

    $result = $this->service->aggregateAssetsForTrust($this->trust);

    expect($result['breakdown'])->toHaveKeys(['properties', 'investment_accounts', 'cash_accounts', 'business_interests', 'chattels']);
    expect((float) $result['breakdown']['properties']['value'])->toBe(400000.0);
    expect($result['breakdown']['properties']['count'])->toBe(1);
    expect((float) $result['breakdown']['properties']['percentage'])->toBe(80.0);
    expect((float) $result['breakdown']['cash_accounts']['value'])->toBe(100000.0);
    expect((float) $result['breakdown']['cash_accounts']['percentage'])->toBe(20.0);
});

it('handles zero total value in breakdown percentage calculation', function () {
    $result = $this->service->aggregateAssetsForTrust($this->trust);

    expect((float) $result['total_value'])->toBe(0.0);
    expect($result['breakdown']['properties']['percentage'])->toBe(0);
    expect($result['breakdown']['cash_accounts']['percentage'])->toBe(0);
});

it('aggregates properties with all metadata', function () {
    Property::factory()->create([
        'user_id' => $this->user->id,
        'trust_id' => $this->trust->id,
        'address_line_1' => '123 Main Street',
        'city' => 'London',
        'current_value' => 750000,
        'ownership_percentage' => 100,
        'valuation_date' => '2024-01-15',
    ]);

    $result = $this->service->aggregateAssetsForTrust($this->trust);

    $property = $result['assets']['properties']->first();
    expect($property)->toHaveKeys(['id', 'type', 'name', 'value', 'full_value', 'ownership_percentage', 'valuation_date']);
    expect($property['type'])->toBe('property');
    expect($property['name'])->toContain('123 Main Street');
    expect($property['name'])->toContain('London');
    expect((float) $property['value'])->toBe(750000.0);
});

it('aggregates cash accounts correctly', function () {
    CashAccount::factory()->create([
        'user_id' => $this->user->id,
        'trust_id' => $this->trust->id,
        'current_balance' => 25000,
        'ownership_percentage' => 100,
        'is_isa' => true,
    ]);

    $result = $this->service->aggregateAssetsForTrust($this->trust);

    $account = $result['assets']['cash_accounts']->first();
    expect($account['type'])->toBe('cash_account');
    expect($account['is_isa'])->toBeTrue();
    expect((float) $account['value'])->toBe(25000.0);
});

it('aggregates business interests correctly', function () {
    BusinessInterest::factory()->create([
        'user_id' => $this->user->id,
        'trust_id' => $this->trust->id,
        'business_name' => 'ABC Ltd',
        'current_valuation' => 500000,
        'ownership_percentage' => 50,
        'valuation_date' => '2024-01-01',
    ]);

    $result = $this->service->aggregateAssetsForTrust($this->trust);

    $business = $result['assets']['business_interests']->first();
    expect($business['type'])->toBe('business_interest');
    expect($business['name'])->toBe('ABC Ltd');
    expect((float) $business['full_value'])->toBe(500000.0);
    expect((float) $business['value'])->toBe(250000.0); // 50% ownership
});

it('aggregates assets for multiple trusts for a user', function () {
    $trust2 = Trust::factory()->create([
        'user_id' => $this->user->id,
        'household_id' => $this->household->id,
        'trust_name' => 'Second Trust',
    ]);

    Property::factory()->create([
        'user_id' => $this->user->id,
        'trust_id' => $this->trust->id,
        'current_value' => 300000,
        'ownership_percentage' => 100,
    ]);

    CashAccount::factory()->create([
        'user_id' => $this->user->id,
        'trust_id' => $trust2->id,
        'current_balance' => 100000,
        'ownership_percentage' => 100,
    ]);

    $result = $this->service->aggregateAssetsForUser($this->user->id);

    expect($result)->toHaveCount(2);
    expect($result->pluck('trust.trust_name'))->toContain('Test Family Trust', 'Second Trust');
    $totalValue = $result->sum(fn ($item) => (float) $item['total_value']);
    expect($totalValue)->toBe(400000.0);
});

it('only aggregates assets belonging to the specific trust', function () {
    $otherTrust = Trust::factory()->create([
        'user_id' => $this->user->id,
        'household_id' => $this->household->id,
        'trust_name' => 'Other Trust',
    ]);

    Property::factory()->create([
        'user_id' => $this->user->id,
        'trust_id' => $this->trust->id,
        'current_value' => 500000,
        'ownership_percentage' => 100,
    ]);

    CashAccount::factory()->create([
        'user_id' => $this->user->id,
        'trust_id' => $otherTrust->id,
        'current_balance' => 100000,
        'ownership_percentage' => 100,
    ]);

    $result = $this->service->aggregateAssetsForTrust($this->trust);

    expect($result['asset_count'])->toBe(1);
    expect((float) $result['total_value'])->toBe(500000.0);
    expect($result['assets']['properties'])->toHaveCount(1);
    expect($result['assets']['cash_accounts'])->toHaveCount(0);
});
