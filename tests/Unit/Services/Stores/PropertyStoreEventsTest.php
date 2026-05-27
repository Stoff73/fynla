<?php

declare(strict_types=1);

use App\Events\Property\PropertyCreated;
use App\Events\Property\PropertyDeleted;
use App\Events\Property\PropertyRestored;
use App\Events\Property\PropertyUpdated;
use App\Models\Property;
use App\Models\User;
use App\Services\Stores\IngestSource;
use App\Services\Stores\PropertyStore;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
});

it('create emits PropertyCreated with source', function () {
    Event::fake();
    $user = User::factory()->create(['tier' => 'tier1']);

    app(PropertyStore::class)->create([
        'property_type' => 'main_residence',
        'ownership_type' => 'individual',
        'current_value' => 350000,
        'ownership_percentage' => 100,
    ], $user, IngestSource::FORM);

    Event::assertDispatched(PropertyCreated::class, fn ($e) => $e->user->id === $user->id && $e->source === IngestSource::FORM
    );
});

it('update emits PropertyUpdated with changes diff', function () {
    Event::fake();
    $user = User::factory()->create(['tier' => 'tier1']);
    $property = Property::factory()->create(['user_id' => $user->id, 'current_value' => 350000]);

    app(PropertyStore::class)->update($property->id, ['current_value' => 425000], $user, IngestSource::FORM);

    Event::assertDispatched(PropertyUpdated::class, fn ($e) => array_key_exists('current_value', $e->changes)
    );
});

it('delete emits PropertyDeleted with reason', function () {
    Event::fake();
    $user = User::factory()->create(['tier' => 'tier1']);
    $property = Property::factory()->create(['user_id' => $user->id]);

    app(PropertyStore::class)->delete($property->id, $user, 'sold');

    Event::assertDispatched(PropertyDeleted::class, fn ($e) => $e->reason === 'sold');
});

it('restore emits PropertyRestored', function () {
    Event::fake();
    $user = User::factory()->create(['tier' => 'tier1']);
    $property = Property::factory()->create(['user_id' => $user->id]);
    $property->delete();

    app(PropertyStore::class)->restore($property->id, $user);

    Event::assertDispatched(PropertyRestored::class);
});
