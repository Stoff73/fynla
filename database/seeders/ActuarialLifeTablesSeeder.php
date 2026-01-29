<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ActuarialLifeTablesSeeder extends Seeder
{
    /**
     * Seed the actuarial life tables with UK ONS National Life Tables data (2020-2022)
     *
     * Source: Office for National Statistics - National Life Tables UK 2020-2022
     * https://www.ons.gov.uk/peoplepopulationandcommunity/birthsdeathsandmarriages/lifeexpectancies
     */
    public function run(): void
    {
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

        $records = [];
        foreach ($data as $row) {
            [$age, $maleLE, $malePD, $femaleLE, $femalePD] = $row;

            // Male record
            $records[] = [
                'age' => $age,
                'gender' => 'male',
                'life_expectancy_years' => $maleLE,
                'probability_of_death' => $malePD,
                'table_year' => $tableYear,
                'table_source' => $tableSource,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // Female record
            $records[] = [
                'age' => $age,
                'gender' => 'female',
                'life_expectancy_years' => $femaleLE,
                'probability_of_death' => $femalePD,
                'table_year' => $tableYear,
                'table_source' => $tableSource,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Upsert all records (handles re-seeding without duplicates)
        DB::table('actuarial_life_tables')->upsert(
            $records,
            ['age', 'gender', 'table_year'], // Unique key columns
            ['life_expectancy_years', 'probability_of_death', 'table_source', 'updated_at'] // Columns to update
        );

        $this->command->info('✅ Seeded '.count($records).' actuarial life table records (UK ONS 2020-2022)');
    }
}
