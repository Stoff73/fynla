<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Property;
use App\Services\Stores\PropertyStore;
use Illuminate\Console\Command;

class BackfillPropertyOutstandingMortgage extends Command
{
    protected $signature = 'properties:backfill-outstanding-mortgage {--chunk=200}';

    protected $description = 'Recompute properties.outstanding_mortgage from canonical mortgages sum';

    public function handle(PropertyStore $propertyStore): int
    {
        $count = 0;
        Property::chunkById((int) $this->option('chunk'), function ($properties) use ($propertyStore, &$count) {
            foreach ($properties as $property) {
                $propertyStore->recalculateDerivedForPropertyId($property->id);
                $count++;
            }
            $this->info("Processed {$count}");
        });
        $this->info("Backfilled {$count} properties.");

        return Command::SUCCESS;
    }
}
