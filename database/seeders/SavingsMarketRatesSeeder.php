<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\SavingsMarketRate;
use Illuminate\Database\Seeder;

class SavingsMarketRatesSeeder extends Seeder
{
    /**
     * Seed UK savings market benchmark rates for 2025/26.
     *
     * Re-runnable: uses updateOrCreate on the composite key (rate_key + tax_year).
     * To update rates, modify the values below and run:
     *   php artisan db:seed --class=SavingsMarketRatesSeeder --force
     */
    public function run(): void
    {
        $taxYear = '2025/26';
        $effectiveFrom = '2025-04-06';

        $rates = [
            ['rate_key' => 'easy_access', 'label' => 'Easy Access', 'rate' => 0.0450],
            ['rate_key' => 'easy_access_isa', 'label' => 'Easy Access ISA', 'rate' => 0.0475],
            ['rate_key' => 'notice', 'label' => 'Notice Account', 'rate' => 0.0500],
            ['rate_key' => 'notice_isa', 'label' => 'Notice ISA', 'rate' => 0.0525],
            ['rate_key' => 'fixed_1_year', 'label' => '1 Year Fixed', 'rate' => 0.0525],
            ['rate_key' => 'fixed_1_year_isa', 'label' => '1 Year Fixed ISA', 'rate' => 0.0550],
            ['rate_key' => 'fixed_2_year', 'label' => '2 Year Fixed', 'rate' => 0.0500],
            ['rate_key' => 'fixed_2_year_isa', 'label' => '2 Year Fixed ISA', 'rate' => 0.0525],
            ['rate_key' => 'fixed_3_year', 'label' => '3 Year Fixed', 'rate' => 0.0475],
            ['rate_key' => 'fixed_3_year_isa', 'label' => '3 Year Fixed ISA', 'rate' => 0.0500],
        ];

        foreach ($rates as $rate) {
            SavingsMarketRate::updateOrCreate(
                [
                    'rate_key' => $rate['rate_key'],
                    'tax_year' => $taxYear,
                ],
                [
                    'label' => $rate['label'],
                    'rate' => $rate['rate'],
                    'effective_from' => $effectiveFrom,
                ]
            );
        }
    }
}
