<?php

declare(strict_types=1);

use App\Events\Mortgage\MortgageCreated;
use App\Events\Mortgage\MortgageDeleted;
use App\Events\Mortgage\MortgageRestored;
use App\Events\Mortgage\MortgageUpdated;
use App\Listeners\Mortgage\RecalculatePropertyOutstandingMortgage;
use App\Models\Mortgage;
use App\Models\Property;
use App\Models\User;
use App\Services\Stores\IngestSource;
use App\Services\Stores\PropertyStore;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->property = Property::factory()->create(['user_id' => $this->user->id]);
});

afterEach(function () {
    Mockery::close();
});

it('triggers PropertyStore::recalculateDerivedForPropertyId on MortgageCreated', function () {
    $mortgage = Mortgage::factory()->create(['user_id' => $this->user->id, 'property_id' => $this->property->id]);

    $propertyStore = Mockery::mock(PropertyStore::class);
    $propertyStore->shouldReceive('recalculateDerivedForPropertyId')->once()->with($this->property->id);

    $listener = new RecalculatePropertyOutstandingMortgage($propertyStore);
    $listener->handle(new MortgageCreated($mortgage, $this->user, IngestSource::FORM));
});

it('handles MortgageUpdated', function () {
    $mortgage = Mortgage::factory()->create(['user_id' => $this->user->id, 'property_id' => $this->property->id]);

    $propertyStore = Mockery::mock(PropertyStore::class);
    $propertyStore->shouldReceive('recalculateDerivedForPropertyId')->once()->with($this->property->id);

    $listener = new RecalculatePropertyOutstandingMortgage($propertyStore);
    $listener->handle(new MortgageUpdated($mortgage, ['outstanding_balance' => [100000, 95000]], $this->user, IngestSource::FORM));
});

it('handles MortgageDeleted', function () {
    $mortgage = Mortgage::factory()->create(['user_id' => $this->user->id, 'property_id' => $this->property->id]);

    $propertyStore = Mockery::mock(PropertyStore::class);
    $propertyStore->shouldReceive('recalculateDerivedForPropertyId')->once()->with($this->property->id);

    $listener = new RecalculatePropertyOutstandingMortgage($propertyStore);
    $listener->handle(new MortgageDeleted($mortgage, $this->user, IngestSource::FORM, false));
});

it('handles MortgageRestored', function () {
    $mortgage = Mortgage::factory()->create(['user_id' => $this->user->id, 'property_id' => $this->property->id]);

    $propertyStore = Mockery::mock(PropertyStore::class);
    $propertyStore->shouldReceive('recalculateDerivedForPropertyId')->once()->with($this->property->id);

    $listener = new RecalculatePropertyOutstandingMortgage($propertyStore);
    $listener->handle(new MortgageRestored($mortgage, $this->user, IngestSource::FORM));
});

it('does nothing when property_id is null (orphan mortgage)', function () {
    $mortgage = Mortgage::factory()->make(['user_id' => $this->user->id, 'property_id' => null]);

    $propertyStore = Mockery::mock(PropertyStore::class);
    $propertyStore->shouldNotReceive('recalculateDerivedForPropertyId');

    $listener = new RecalculatePropertyOutstandingMortgage($propertyStore);
    $listener->handle(new MortgageCreated($mortgage, $this->user, IngestSource::FORM));
});
