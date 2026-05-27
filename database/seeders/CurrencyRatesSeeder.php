<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Services\Stores\CurrencyRateStore;
use App\Services\Stores\IngestSource;
use Illuminate\Database\Seeder;

class CurrencyRatesSeeder extends Seeder
{
    /**
     * Seed initial GBP-base currency rates via the canonical CurrencyRateStore.
     *
     * Re-runnable: looks up existing rows by (from_ccy, to_ccy, effective_at)
     * via CurrencyRateStore::findByPairAndEffectiveAt and calls update() if
     * present, else create(). Preserves the canonical store/event contract
     * (SP1 Pass 2 §12). Migrated from direct-write CurrencyRate::create() in
     * PR R2.4.
     *
     * To update rates, modify the values below and run:
     *   php artisan db:seed --class=CurrencyRatesSeeder --force
     */
    public function run(): void
    {
        $store = app(CurrencyRateStore::class);

        $effectiveAt = '2026-04-06 00:00:00';
        $source = 'manual';

        // Snapshot rates as of 2026-04-06 (tax year start). Admins can update
        // through the R2.3 admin panel or by re-seeding.
        $rates = [
            ['from_ccy' => 'GBP', 'to_ccy' => 'EUR', 'rate' => 1.17000000],
            ['from_ccy' => 'EUR', 'to_ccy' => 'GBP', 'rate' => 0.85470085],
            ['from_ccy' => 'GBP', 'to_ccy' => 'USD', 'rate' => 1.27500000],
            ['from_ccy' => 'USD', 'to_ccy' => 'GBP', 'rate' => 0.78431373],
        ];

        $count = 0;

        foreach ($rates as $row) {
            $payload = [
                'from_ccy' => $row['from_ccy'],
                'to_ccy' => $row['to_ccy'],
                'rate' => $row['rate'],
                'effective_at' => $effectiveAt,
                'source' => $source,
            ];

            $existing = $store->findByPairAndEffectiveAt($row['from_ccy'], $row['to_ccy'], $effectiveAt);

            if ($existing) {
                $store->update($existing->id, $payload, IngestSource::SEEDER);
            } else {
                $store->create($payload, IngestSource::SEEDER);
            }

            $count++;
        }

        $this->command->info('✅ Seeded '.$count.' currency rates (GBP base, '.$effectiveAt.')');
    }
}
