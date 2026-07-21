<?php

declare(strict_types=1);

namespace App\Services\Stores\Normalisers;

use App\Services\Stores\Exceptions\StoreValidationException;

class ActuarialLifeTableNormaliser
{
    /**
     * Map an admin/seeder payload to the canonical array shape consumed by
     * ActuarialLifeTableStore::create() / update().
     *
     * Fields mirror the actuarial_life_tables table schema exactly:
     *   age (int 0..125), gender ('male'|'female'),
     *   life_expectancy_years (float > 0), probability_of_death (float 0..1),
     *   table_year (e.g. '2020-2022'), table_source (e.g. 'UK ONS National Life Tables').
     */
    public function fromAdmin(array $input): array
    {
        $errors = [];
        $required = ['age', 'gender', 'life_expectancy_years', 'probability_of_death', 'table_year', 'table_source'];

        foreach ($required as $field) {
            if (! array_key_exists($field, $input) || $input[$field] === null || $input[$field] === '') {
                $errors[$field] = 'required';
            }
        }

        if ($errors) {
            throw new StoreValidationException($errors);
        }

        if (! is_numeric($input['age']) || (int) $input['age'] < 0 || (int) $input['age'] > 125) {
            throw new StoreValidationException(['age' => 'must be an integer in 0..125']);
        }

        if (! in_array($input['gender'], ['male', 'female'], true)) {
            throw new StoreValidationException(['gender' => "must be 'male' or 'female'"]);
        }

        if (! is_numeric($input['life_expectancy_years']) || (float) $input['life_expectancy_years'] <= 0.0) {
            throw new StoreValidationException(['life_expectancy_years' => 'must be numeric and > 0']);
        }

        if (! is_numeric($input['probability_of_death'])
            || (float) $input['probability_of_death'] < 0.0
            || (float) $input['probability_of_death'] > 1.0
        ) {
            throw new StoreValidationException(['probability_of_death' => 'must be numeric in 0..1']);
        }

        return [
            'age' => (int) $input['age'],
            'gender' => trim((string) $input['gender']),
            'life_expectancy_years' => (float) $input['life_expectancy_years'],
            'probability_of_death' => (float) $input['probability_of_death'],
            'table_year' => trim((string) $input['table_year']),
            'table_source' => trim((string) $input['table_source']),
        ];
    }
}
