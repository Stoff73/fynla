<?php

declare(strict_types=1);

use App\Events\Mortgage\MortgageCreated;
use App\Events\Mortgage\MortgageDeleted;
use App\Events\Mortgage\MortgageRestored;
use App\Events\Mortgage\MortgageUpdated;
use App\Models\Property;
use App\Models\User;
use App\Services\Stores\IngestSource;
use App\Services\Stores\MortgageStore;
use App\Services\Stores\PropertyStore;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
});

it('creates mortgage → property.outstanding_mortgage updates', function () {
    $user = User::factory()->create();
    $property = Property::factory()->create([
        'user_id' => $user->id,
        'current_value' => 500000,
        'outstanding_mortgage' => 0,
    ]);

    app(MortgageStore::class)->create([
        'property_id' => $property->id,
        'user_id' => $user->id,
        'lender_name' => 'Test Bank',
        'mortgage_type' => 'repayment',
        'outstanding_balance' => 250000,
        'monthly_payment' => 1500,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.00,
    ], $user, IngestSource::FORM);

    expect((float) $property->fresh()->outstanding_mortgage)->toEqual(250000.00);
    expect($property->fresh()->outstanding_mortgage_calculated_at)->not->toBeNull();
});

it('updates mortgage → property.outstanding_mortgage updates', function () {
    $user = User::factory()->create();
    $property = Property::factory()->create([
        'user_id' => $user->id,
        'current_value' => 500000,
    ]);
    $mortgage = app(MortgageStore::class)->create([
        'property_id' => $property->id,
        'user_id' => $user->id,
        'lender_name' => 'Test Bank',
        'mortgage_type' => 'repayment',
        'outstanding_balance' => 250000,
        'monthly_payment' => 1500,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.00,
    ], $user, IngestSource::FORM);

    app(MortgageStore::class)->update(
        $mortgage->id,
        ['outstanding_balance' => 200000],
        $user,
        IngestSource::FORM
    );

    expect((float) $property->fresh()->outstanding_mortgage)->toEqual(200000.00);
});

it('deletes mortgage → property.outstanding_mortgage updates', function () {
    $user = User::factory()->create();
    $property = Property::factory()->create([
        'user_id' => $user->id,
        'current_value' => 500000,
    ]);
    $mortgage = app(MortgageStore::class)->create([
        'property_id' => $property->id,
        'user_id' => $user->id,
        'lender_name' => 'Test Bank',
        'mortgage_type' => 'repayment',
        'outstanding_balance' => 250000,
        'monthly_payment' => 1500,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.00,
    ], $user, IngestSource::FORM);

    app(MortgageStore::class)->delete($mortgage->id, $user, IngestSource::FORM);

    expect((float) $property->fresh()->outstanding_mortgage)->toEqual(0.00);
});

it('multiple mortgages on one property sum correctly', function () {
    $user = User::factory()->create();
    $property = Property::factory()->create([
        'user_id' => $user->id,
        'current_value' => 1000000,
    ]);

    $base = [
        'property_id' => $property->id,
        'user_id' => $user->id,
        'mortgage_type' => 'repayment',
        'monthly_payment' => 1000,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.00,
    ];

    app(MortgageStore::class)->create(array_merge($base, [
        'lender_name' => 'Bank A',
        'outstanding_balance' => 200000,
    ]), $user, IngestSource::FORM);

    app(MortgageStore::class)->create(array_merge($base, [
        'lender_name' => 'Bank B',
        'outstanding_balance' => 150000,
    ]), $user, IngestSource::FORM);

    expect((float) $property->fresh()->outstanding_mortgage)->toEqual(350000.00);
});

it('no Mortgage events fire when Property is updated alone (loop prevention)', function () {
    $user = User::factory()->create();
    $property = Property::factory()->create([
        'user_id' => $user->id,
        'current_value' => 500000,
    ]);

    Event::fake([
        MortgageCreated::class,
        MortgageUpdated::class,
        MortgageDeleted::class,
        MortgageRestored::class,
    ]);

    // PropertyStore::recalculateDerivedForPropertyId should NOT cause Mortgage events
    app(PropertyStore::class)->recalculateDerivedForPropertyId($property->id);

    Event::assertNotDispatched(MortgageCreated::class);
    Event::assertNotDispatched(MortgageUpdated::class);
    Event::assertNotDispatched(MortgageDeleted::class);
    Event::assertNotDispatched(MortgageRestored::class);
});
