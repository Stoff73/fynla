<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CurrencyRate;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class CurrencyRatesSeeder extends Seeder
{
    /**
     * Seed initial GBP-base currency rates for SP1 Pass 2 R2.
     *
     * NOTE: This PR (R2.1) writes directly via the Eloquent model so the schema
     * is exercised end-to-end before the canonical CurrencyRateStore lands.
     * PR R2.4 swaps this seeder onto the store with IngestSource::SEEDER once
     * R2.2 introduces the facade. The boundary arch test from R2.2 onward
     * keeps CurrencyRatesSeeder in its allowlist until R2.5 lock-down.
     */
    public function run(): void
    {
        $effectiveAt = Carbon::create(2026, 4, 6, 0, 0, 0);
        $source = 'manual';

        // Snapshot rates as of 2026-04-06 (tax year start). Admins can update
        // through the upcoming R2.3 admin panel or by re-seeding.
        $rates = [
            ['from_ccy' => 'GBP', 'to_ccy' => 'EUR', 'rate' => 1.17000000],
            ['from_ccy' => 'EUR', 'to_ccy' => 'GBP', 'rate' => 0.85470085],
            ['from_ccy' => 'GBP', 'to_ccy' => 'USD', 'rate' => 1.27500000],
            ['from_ccy' => 'USD', 'to_ccy' => 'GBP', 'rate' => 0.78431373],
        ];

        $count = 0;

        foreach ($rates as $row) {
            $existing = CurrencyRate::where('from_ccy', $row['from_ccy'])
                ->where('to_ccy', $row['to_ccy'])
                ->where('effective_at', $effectiveAt)
                ->first();

            if ($existing) {
                $existing->update(['rate' => $row['rate'], 'source' => $source]);
            } else {
                CurrencyRate::create([
                    'from_ccy' => $row['from_ccy'],
                    'to_ccy' => $row['to_ccy'],
                    'rate' => $row['rate'],
                    'effective_at' => $effectiveAt,
                    'source' => $source,
                ]);
            }

            $count++;
        }

        $this->command->info('✅ Seeded '.$count.' currency rates (GBP base, '.$effectiveAt->toDateString().')');
    }
}
