<?php

declare(strict_types=1);

use App\Models\Mortgage;
use App\Models\Property;
use App\Models\User;
use App\Services\Stores\Exceptions\StoreValidationException;
use App\Services\Stores\IngestSource;
use App\Services\Stores\MortgageStore;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
    $this->user = User::factory()->create();
    $this->property = Property::factory()->create(['user_id' => $this->user->id]);
    $this->store = app(MortgageStore::class);
});

it('creates a mortgage via the store', function () {
    $canonical = [
        'property_id' => $this->property->id,
        'user_id' => $this->user->id,
        'lender_name' => 'Nationwide',
        'mortgage_type' => 'repayment',
        'outstanding_balance' => 250000.00,
        'interest_rate' => 4.5,
        'rate_type' => 'fixed',
        'monthly_payment' => 1500.00,
        'start_date' => '2020-01-01',
        'maturity_date' => '2045-01-01',
        'remaining_term_months' => 240,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.00,
    ];

    $mortgage = $this->store->create($canonical, $this->user, IngestSource::FORM);

    expect($mortgage)->toBeInstanceOf(Mortgage::class);
    expect($mortgage->outstanding_balance)->toEqual(250000.00);
    expect($mortgage->user_id)->toBe($this->user->id);
});

it('rejects ownership_type=tenants_in_common', function () {
    $canonical = [
        'property_id' => $this->property->id,
        'user_id' => $this->user->id,
        'lender_name' => 'Nationwide',
        'mortgage_type' => 'repayment',
        'outstanding_balance' => 250000.00,
        'monthly_payment' => 1500.00,
        'ownership_type' => 'tenants_in_common',
        'ownership_percentage' => 50.00,
    ];

    expect(fn () => $this->store->create($canonical, $this->user, IngestSource::FORM))
        ->toThrow(StoreValidationException::class);
});

it('returns joint-aware reads via forUser', function () {
    $spouse = User::factory()->create();
    $mortgage = Mortgage::factory()->create([
        'user_id' => $this->user->id,
        'joint_owner_id' => $spouse->id,
        'property_id' => $this->property->id,
        'ownership_type' => 'joint',
    ]);

    expect($this->store->forUser($this->user)->count())->toBe(1);
    expect($this->store->forUser($spouse)->count())->toBe(1);
    expect($this->store->forUserPrimaryOnly($spouse)->count())->toBe(0);
});

it('finds mortgages for a given property', function () {
    Mortgage::factory()->create(['user_id' => $this->user->id, 'property_id' => $this->property->id]);
    Mortgage::factory()->create(['user_id' => $this->user->id, 'property_id' => $this->property->id]);

    expect($this->store->forProperty($this->property->id, $this->user)->count())->toBe(2);
});

it('soft-deletes and restores a mortgage', function () {
    $mortgage = Mortgage::factory()->create([
        'user_id' => $this->user->id,
        'property_id' => $this->property->id,
    ]);

    $this->store->delete($mortgage->id, $this->user, IngestSource::FORM);

    expect(Mortgage::find($mortgage->id))->toBeNull();
    expect(Mortgage::withTrashed()->find($mortgage->id))->not->toBeNull();

    $restored = $this->store->restore($mortgage->id, $this->user, IngestSource::FORM);

    expect($restored)->not->toBeNull();
    expect($restored->deleted_at)->toBeNull();
});

it('updateOrCreate creates a new mortgage when no match exists', function () {
    $canonical = [
        'user_id' => $this->user->id,
        'property_id' => $this->property->id,
        'lender_name' => 'Santander',
        'mortgage_type' => 'repayment',
        'outstanding_balance' => 300000.00,
        'monthly_payment' => 1800.00,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.00,
    ];

    $mortgage = $this->store->updateOrCreate($canonical, $this->user, IngestSource::SEEDER);

    expect($mortgage->wasRecentlyCreated)->toBeTrue();
    expect(Mortgage::where('lender_name', 'Santander')->count())->toBe(1);
});

it('updateOrCreate updates an existing mortgage matched by (user_id, property_id, lender_name)', function () {
    $existing = Mortgage::factory()->create([
        'user_id' => $this->user->id,
        'property_id' => $this->property->id,
        'lender_name' => 'Halifax',
        'outstanding_balance' => 200000.00,
        'monthly_payment' => 1200.00,
        'mortgage_type' => 'repayment',
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.00,
    ]);

    $canonical = [
        'user_id' => $this->user->id,
        'property_id' => $this->property->id,
        'lender_name' => 'Halifax',
        'mortgage_type' => 'repayment',
        'outstanding_balance' => 150000.00,
        'monthly_payment' => 1200.00,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.00,
    ];

    $result = $this->store->updateOrCreate($canonical, $this->user, IngestSource::SEEDER);

    expect($result->id)->toBe($existing->id);
    expect((float) $result->outstanding_balance)->toEqual(150000.0);
});
