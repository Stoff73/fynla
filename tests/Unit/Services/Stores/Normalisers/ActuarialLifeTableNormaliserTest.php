<?php

declare(strict_types=1);

use App\Services\Stores\Exceptions\StoreValidationException;
use App\Services\Stores\Normalisers\ActuarialLifeTableNormaliser;

it('normalises a valid admin payload', function () {
    $n = new ActuarialLifeTableNormaliser;

    $out = $n->fromAdmin([
        'age' => 65,
        'gender' => 'male',
        'life_expectancy_years' => 18.3,
        'probability_of_death' => 0.00989,
        'table_year' => '2020-2022',
        'table_source' => 'UK ONS National Life Tables',
    ]);

    expect($out)->toBe([
        'age' => 65,
        'gender' => 'male',
        'life_expectancy_years' => 18.3,
        'probability_of_death' => 0.00989,
        'table_year' => '2020-2022',
        'table_source' => 'UK ONS National Life Tables',
    ]);
});

it('rejects missing required fields', function () {
    $n = new ActuarialLifeTableNormaliser;

    expect(fn () => $n->fromAdmin([
        'age' => 65,
        'gender' => 'male',
        // missing life_expectancy_years, probability_of_death, table_year, table_source
    ]))->toThrow(StoreValidationException::class);
});

it('rejects out-of-range age', function () {
    $n = new ActuarialLifeTableNormaliser;

    expect(fn () => $n->fromAdmin([
        'age' => 200,
        'gender' => 'male',
        'life_expectancy_years' => 5.0,
        'probability_of_death' => 0.1,
        'table_year' => '2020-2022',
        'table_source' => 'UK ONS',
    ]))->toThrow(StoreValidationException::class);

    expect(fn () => $n->fromAdmin([
        'age' => -1,
        'gender' => 'male',
        'life_expectancy_years' => 5.0,
        'probability_of_death' => 0.1,
        'table_year' => '2020-2022',
        'table_source' => 'UK ONS',
    ]))->toThrow(StoreValidationException::class);
});

it('rejects unknown gender', function () {
    $n = new ActuarialLifeTableNormaliser;

    expect(fn () => $n->fromAdmin([
        'age' => 65,
        'gender' => 'nonbinary',
        'life_expectancy_years' => 18.3,
        'probability_of_death' => 0.01,
        'table_year' => '2020-2022',
        'table_source' => 'UK ONS',
    ]))->toThrow(StoreValidationException::class);
});

it('rejects non-positive life expectancy', function () {
    $n = new ActuarialLifeTableNormaliser;

    expect(fn () => $n->fromAdmin([
        'age' => 65,
        'gender' => 'male',
        'life_expectancy_years' => 0,
        'probability_of_death' => 0.01,
        'table_year' => '2020-2022',
        'table_source' => 'UK ONS',
    ]))->toThrow(StoreValidationException::class);
});

it('rejects probability outside 0..1', function () {
    $n = new ActuarialLifeTableNormaliser;

    expect(fn () => $n->fromAdmin([
        'age' => 65,
        'gender' => 'male',
        'life_expectancy_years' => 18.3,
        'probability_of_death' => 1.5,
        'table_year' => '2020-2022',
        'table_source' => 'UK ONS',
    ]))->toThrow(StoreValidationException::class);
});
