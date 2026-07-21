<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Property;
use App\Models\User;
use App\Services\Stores\Recalc\PropertyDerivedColumnCalculator;
use Illuminate\Console\Command;

class BackfillPropertyDerivedColumns extends Command
{
    protected $signature = 'properties:backfill-derived-columns';

    protected $description = 'Backfill canonical derived columns (current_value_gbp, equity_gbp, loan_to_value_pct) on existing properties.';

    public function handle(PropertyDerivedColumnCalculator $calc): int
    {
        $count = 0;
        Property::chunkById(200, function ($chunk) use ($calc, &$count) {
            foreach ($chunk as $property) {
                $user = User::find($property->user_id);
                if ($user === null) {
                    continue;
                }
                $derived = $calc->calculate($property, $user);
                $property->forceFill([
                    'current_value_gbp' => $derived['current_value_gbp'],
                    'current_value_gbp_calculated_at' => now(),
                    'equity_gbp' => $derived['equity_gbp'],
                    'equity_gbp_calculated_at' => now(),
                    'loan_to_value_pct' => $derived['loan_to_value_pct'],
                    'loan_to_value_pct_calculated_at' => now(),
                ])->saveQuietly();
                $count++;
            }
        });

        $this->info("Backfilled {$count} properties.");

        return self::SUCCESS;
    }
}
