<?php

declare(strict_types=1);

use App\Events\ReferenceData\ReferenceDataUpdated;
use App\Models\ActuarialLifeTable;
use App\Services\Stores\ActuarialLifeTableStore;
use App\Services\Stores\Exceptions\StoreValidationException;
use App\Services\Stores\IngestSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function () {
    Event::fake([ReferenceDataUpdated::class]);
});

it('creates a row from an admin payload', function () {
    $store = new ActuarialLifeTableStore;

    $id = $store->create([
        'age' => 65,
        'gender' => 'male',
        'life_expectancy_years' => 18.3,
        'probability_of_death' => 0.00989,
        'table_year' => '2020-2022',
        'table_source' => 'UK ONS National Life Tables',
    ], IngestSource::ADMIN, actorUserId: 1);

    $row = ActuarialLifeTable::find($id);
    expect($row)->not->toBeNull()
        ->and($row->age)->toBe(65)
        ->and($row->gender)->toBe('male')
        ->and((float) $row->life_expectancy_years)->toEqualWithDelta(18.3, 0.0001);
});

it('emits ReferenceDataUpdated on create', function () {
    $store = new ActuarialLifeTableStore;

    $id = $store->create([
        'age' => 65,
        'gender' => 'male',
        'life_expectancy_years' => 18.3,
        'probability_of_death' => 0.00989,
        'table_year' => '2020-2022',
        'table_source' => 'UK ONS National Life Tables',
    ], IngestSource::ADMIN, actorUserId: 1);

    Event::assertDispatched(
        ReferenceDataUpdated::class,
        fn ($e) => $e->entityKey === 'actuarial_life_table' && $e->entityId === $id
    );
});

it('updates a row through the store', function () {
    $store = new ActuarialLifeTableStore;

    $id = $store->create([
        'age' => 65,
        'gender' => 'male',
        'life_expectancy_years' => 18.3,
        'probability_of_death' => 0.00989,
        'table_year' => '2020-2022',
        'table_source' => 'UK ONS National Life Tables',
    ], IngestSource::ADMIN, actorUserId: 1);

    $store->update($id, ['life_expectancy_years' => 19.0], IngestSource::ADMIN, actorUserId: 1);

    expect((float) ActuarialLifeTable::find($id)->life_expectancy_years)
        ->toEqualWithDelta(19.0, 0.0001);
});

it('refuses FORM / FYN_AI / UPLOAD ingest sources', function () {
    $store = new ActuarialLifeTableStore;

    foreach ([IngestSource::FORM, IngestSource::FYN_AI, IngestSource::UPLOAD] as $bad) {
        expect(fn () => $store->create([
            'age' => 65,
            'gender' => 'male',
            'life_expectancy_years' => 18.3,
            'probability_of_death' => 0.00989,
            'table_year' => '2020-2022',
            'table_source' => 'UK ONS National Life Tables',
        ], $bad))->toThrow(StoreValidationException::class);
    }
});

it('memoises reads within a request', function () {
    $store = new ActuarialLifeTableStore;

    $id = $store->create([
        'age' => 65,
        'gender' => 'male',
        'life_expectancy_years' => 18.3,
        'probability_of_death' => 0.00989,
        'table_year' => '2020-2022',
        'table_source' => 'UK ONS National Life Tables',
    ], IngestSource::ADMIN, actorUserId: 1);

    $r1 = $store->find($id);
    // mutate life_expectancy_years out-of-band
    ActuarialLifeTable::where('id', $id)->update(['life_expectancy_years' => 5.0]);
    $r2 = $store->find($id);

    // memoised — original value survives the out-of-band mutation
    expect((float) $r2['life_expectancy_years'])->toEqualWithDelta(18.3, 0.0001);
});

it('forCohort returns ordered ages for the gender/year', function () {
    $store = new ActuarialLifeTableStore;

    foreach ([[65, 18.3, 0.00989], [70, 14.6, 0.01535], [60, 22.3, 0.00650]] as [$age, $le, $pd]) {
        $store->create([
            'age' => $age,
            'gender' => 'male',
            'life_expectancy_years' => $le,
            'probability_of_death' => $pd,
            'table_year' => '2020-2022',
            'table_source' => 'UK ONS National Life Tables',
        ], IngestSource::SEEDER);
    }

    // Different gender/year should be excluded
    $store->create([
        'age' => 65,
        'gender' => 'female',
        'life_expectancy_years' => 20.9,
        'probability_of_death' => 0.00609,
        'table_year' => '2020-2022',
        'table_source' => 'UK ONS National Life Tables',
    ], IngestSource::SEEDER);

    $cohort = $store->forCohort('male', '2020-2022');

    expect($cohort)->toHaveCount(3)
        ->and($cohort->pluck('age')->all())->toBe([60, 65, 70]);
});

it('findByCohortAndAge returns the matching row', function () {
    $store = new ActuarialLifeTableStore;

    $id = $store->create([
        'age' => 65,
        'gender' => 'male',
        'life_expectancy_years' => 18.3,
        'probability_of_death' => 0.00989,
        'table_year' => '2020-2022',
        'table_source' => 'UK ONS National Life Tables',
    ], IngestSource::SEEDER);

    $found = $store->findByCohortAndAge(65, 'male', '2020-2022', 'UK ONS National Life Tables');
    expect($found)->not->toBeNull()->and($found->id)->toBe($id);

    expect($store->findByCohortAndAge(99, 'male', '2020-2022', 'UK ONS National Life Tables'))->toBeNull();
});
