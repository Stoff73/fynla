<?php

declare(strict_types=1);

use App\Models\BusinessInterest;
use App\Models\Chattel;
use App\Models\Investment\InvestmentAccount;
use App\Models\Mortgage;
use App\Models\Property;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\NetWorth\NetWorthService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(NetWorthService::class);
    $this->user = User::factory()->create();
});

it('calculates net worth with no assets as zero', function () {
    $result = $this->service->calculateNetWorth($this->user);

    expect($result['total_assets'])->toBe(0.0)
        ->and($result['total_liabilities'])->toBe(0.0)
        ->and($result['net_worth'])->toBe(0.0);
});

it('includes ownership percentage in property net worth calculation', function () {
    // Single-record pattern: Database stores FULL value, share calculated from percentage
    // For joint properties, ONE record exists with full value
    // User's share = full_value * (ownership_percentage / 100)
    Property::factory()->create([
        'user_id' => $this->user->id,
        'current_value' => 400000, // FULL property value stored
        'ownership_percentage' => 50,
        'ownership_type' => 'joint',
    ]);

    $result = $this->service->calculateNetWorth($this->user);

    // User's share is 50% of £400k = £200k
    expect($result['total_assets'])->toBe(200000.0)
        ->and($result['breakdown']['property'])->toBe(200000.0)
        ->and($result['net_worth'])->toBe(200000.0);
});

it('calculates net worth with investments', function () {
    InvestmentAccount::factory()->create([
        'user_id' => $this->user->id,
        'current_value' => 50000,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ]);

    $result = $this->service->calculateNetWorth($this->user);

    expect($result['total_assets'])->toBe(50000.0)
        ->and($result['breakdown']['investments'])->toBe(50000.0);
});

it('calculates net worth with cash accounts', function () {
    // Service uses SavingsAccount model for cash calculations
    SavingsAccount::factory()->create([
        'user_id' => $this->user->id,
        'current_balance' => 25000,
        'ownership_percentage' => 100,
    ]);

    $result = $this->service->calculateNetWorth($this->user);

    expect($result['total_assets'])->toBe(25000.0)
        ->and($result['breakdown']['cash'])->toBe(25000.0);
});

it('calculates net worth with business interests', function () {
    // Single-record pattern: Database stores FULL business valuation
    // ownership_percentage represents shareholding (e.g., 75% of £100k = £75k share)
    BusinessInterest::factory()->create([
        'user_id' => $this->user->id,
        'current_valuation' => 100000, // Full business valuation
        'ownership_type' => 'individual',
        'ownership_percentage' => 75, // User's 75% shareholding
    ]);

    $result = $this->service->calculateNetWorth($this->user);

    expect($result['total_assets'])->toBe(75000.0)
        ->and($result['breakdown']['business'])->toBe(75000.0);
});

it('calculates net worth with chattels', function () {
    Chattel::factory()->create([
        'user_id' => $this->user->id,
        'current_value' => 15000,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ]);

    $result = $this->service->calculateNetWorth($this->user);

    expect($result['total_assets'])->toBe(15000.0)
        ->and($result['breakdown']['chattels'])->toBe(15000.0);
});

it('reduces net worth when mortgages are present', function () {
    $property = Property::factory()->create([
        'user_id' => $this->user->id,
        'current_value' => 400000,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ]);

    Mortgage::factory()->create([
        'property_id' => $property->id,
        'user_id' => $this->user->id,
        'outstanding_balance' => 200000,
    ]);

    $result = $this->service->calculateNetWorth($this->user);

    expect($result['total_assets'])->toBe(400000.0)
        ->and($result['total_liabilities'])->toBe(200000.0)
        ->and($result['net_worth'])->toBe(200000.0);
});

it('calculates net worth with multiple asset types', function () {
    Property::factory()->create([
        'user_id' => $this->user->id,
        'current_value' => 400000,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ]);

    InvestmentAccount::factory()->create([
        'user_id' => $this->user->id,
        'current_value' => 50000,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ]);

    // Service uses SavingsAccount model for cash calculations
    SavingsAccount::factory()->create([
        'user_id' => $this->user->id,
        'current_balance' => 25000,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ]);

    $result = $this->service->calculateNetWorth($this->user);

    expect($result['total_assets'])->toBe(475000.0)
        ->and($result['breakdown']['property'])->toBe(400000.0)
        ->and($result['breakdown']['investments'])->toBe(50000.0)
        ->and($result['breakdown']['cash'])->toBe(25000.0)
        ->and($result['net_worth'])->toBe(475000.0);
});

it('returns percentages in asset breakdown', function () {
    Property::factory()->create([
        'user_id' => $this->user->id,
        'current_value' => 400000,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ]);

    InvestmentAccount::factory()->create([
        'user_id' => $this->user->id,
        'current_value' => 100000,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ]);

    $breakdown = $this->service->getAssetBreakdown($this->user);

    expect($breakdown['property']['percentage'])->toBe(80.0)
        ->and($breakdown['investments']['percentage'])->toBe(20.0);
});

it('returns counts and totals in assets summary', function () {
    Property::factory()->count(2)->create([
        'user_id' => $this->user->id,
        'current_value' => 200000,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ]);

    $summary = $this->service->getAssetsSummary($this->user);

    expect($summary['property']['count'])->toBe(2)
        ->and($summary['property']['total_value'])->toBe(400000.0);
});

it('filters joint assets correctly', function () {
    // Single-record pattern: FULL value stored, share calculated
    Property::factory()->create([
        'user_id' => $this->user->id,
        'current_value' => 400000, // FULL property value
        'ownership_percentage' => 50,
        'ownership_type' => 'joint',
        'address_line_1' => '123 Test Street',
    ]);

    Property::factory()->create([
        'user_id' => $this->user->id,
        'current_value' => 200000,
        'ownership_percentage' => 100,
        'ownership_type' => 'individual',
    ]);

    $jointAssets = $this->service->getJointAssets($this->user);

    expect($jointAssets)->toHaveCount(1)
        ->and($jointAssets[0]['ownership_percentage'])->toEqual(50);
});

it('returns same result from cached net worth', function () {
    Property::factory()->create([
        'user_id' => $this->user->id,
        'current_value' => 400000,
        'ownership_percentage' => 100,
    ]);

    $result1 = $this->service->getCachedNetWorth($this->user);
    $result2 = $this->service->getCachedNetWorth($this->user);

    expect($result1)->toEqual($result2);
});
