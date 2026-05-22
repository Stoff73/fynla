<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Services\Stores\ActuarialLifeTableStore;
use App\Services\Stores\IngestSource;
use Illuminate\Database\Seeder;

class ActuarialLifeTablesSeeder extends Seeder
{
    /**
     * Seed the actuarial life tables with UK ONS National Life Tables data (2020-2022).
     *
     * Re-runnable: looks up existing rows by (age, gender, table_year, table_source)
     * via ActuarialLifeTableStore::findByCohortAndAge and calls update() if present,
     * else create(). Preserves the canonical store-and-event contract (SP1 Pass 2 §12).
     *
     * Source: Office for National Statistics — National Life Tables UK 2020-2022
     * https://www.ons.gov.uk/peoplepopulationandcommunity/birthsdeathsandmarriages/lifeexpectancies
     *
     * To update tables, modify the values below and run:
     *   php artisan db:seed --class=ActuarialLifeTablesSeeder --force
     */
    public function run(): void
    {
        $store = app(ActuarialLifeTableStore::class);

        $tableYear = '2020-2022';
        $tableSource = 'UK ONS National Life Tables';

        // UK ONS National Life Tables 2020-2022
        // Format: [age, male_life_expectancy, male_prob_death, female_life_expectancy, female_prob_death]
        $data = [
            [0, 78.7, 0.00416, 82.9, 0.00335],
            [1, 78.0, 0.00026, 82.2, 0.00021],
            [5, 74.1, 0.00009, 78.2, 0.00007],
            [10, 69.1, 0.00008, 73.2, 0.00006],
            [15, 64.1, 0.00028, 68.3, 0.00011],
            [20, 59.2, 0.00049, 63.3, 0.00016],
            [25, 54.4, 0.00051, 58.4, 0.00019],
            [30, 49.6, 0.00058, 53.5, 0.00026],
            [35, 44.8, 0.00078, 48.6, 0.00040],
            [40, 40.1, 0.00117, 43.8, 0.00064],
            [45, 35.4, 0.00181, 39.0, 0.00104],
            [50, 30.9, 0.00281, 34.3, 0.00163],
            [55, 26.5, 0.00429, 29.7, 0.00257],
            [60, 22.3, 0.00650, 25.2, 0.00396],
            [65, 18.3, 0.00989, 20.9, 0.00609],
            [70, 14.6, 0.01535, 16.8, 0.00961],
            [75, 11.3, 0.02429, 13.0, 0.01554],
            [80, 8.4, 0.03952, 9.7, 0.02590],
            [85, 6.0, 0.06589, 7.0, 0.04480],
            [90, 4.2, 0.11229, 4.9, 0.08035],
            [95, 2.9, 0.18744, 3.3, 0.14464],
            [100, 2.0, 0.29579, 2.2, 0.24177],
        ];

        $count = 0;

        foreach ($data as $row) {
            [$age, $maleLE, $malePD, $femaleLE, $femalePD] = $row;

            foreach ([
                ['gender' => 'male', 'le' => $maleLE, 'pd' => $malePD],
                ['gender' => 'female', 'le' => $femaleLE, 'pd' => $femalePD],
            ] as $cohort) {
                $payload = [
                    'age' => $age,
                    'gender' => $cohort['gender'],
                    'life_expectancy_years' => $cohort['le'],
                    'probability_of_death' => $cohort['pd'],
                    'table_year' => $tableYear,
                    'table_source' => $tableSource,
                ];

                $existing = $store->findByCohortAndAge($age, $cohort['gender'], $tableYear, $tableSource);

                if ($existing) {
                    $store->update($existing->id, $payload, IngestSource::SEEDER);
                } else {
                    $store->create($payload, IngestSource::SEEDER);
                }

                $count++;
            }
        }

        $this->command->info('✅ Seeded '.$count.' actuarial life table records (UK ONS 2020-2022)');
    }
}
