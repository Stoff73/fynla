<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Mortgage;
use App\Services\Stores\Recalc\MortgageDerivedColumnCalculator;
use Illuminate\Console\Command;

final class BackfillMortgageDerivedColumns extends Command
{
    protected $signature = 'mortgages:backfill-derived-columns {--chunk=200}';

    protected $description = 'Recompute outstanding_balance_gbp / monthly_payment_gbp / current_ltv_pct for all mortgages';

    public function handle(MortgageDerivedColumnCalculator $calculator): int
    {
        $count = 0;
        Mortgage::with('property')->chunkById((int) $this->option('chunk'), function ($mortgages) use ($calculator, &$count) {
            foreach ($mortgages as $mortgage) {
                $calculator->recalculate($mortgage);
                $count++;
            }
            $this->info("Processed {$count}");
        });
        $this->info("Backfilled {$count} mortgages.");

        return Command::SUCCESS;
    }
}
