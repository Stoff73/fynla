<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SavingsAccount;
use App\Services\Stores\Recalc\SavingsAccountDerivedColumnCalculator;
use Illuminate\Console\Command;

class BackfillSavingsDerivedColumns extends Command
{
    protected $signature = 'savings:backfill-derived';

    protected $description = 'One-off backfill of canonical derived columns for existing SavingsAccount rows';

    public function handle(SavingsAccountDerivedColumnCalculator $calc): int
    {
        SavingsAccount::chunkById(200, function ($chunk) use ($calc) {
            foreach ($chunk as $account) {
                $derived = $calc->calculate($account);
                $now = now();
                $account->forceFill([
                    'balance_gbp' => $derived['balance_gbp'],
                    'balance_gbp_calculated_at' => $now,
                    'annual_interest_projected_gbp' => $derived['annual_interest_projected_gbp'],
                    'annual_interest_projected_gbp_calculated_at' => $now,
                    'isa_allowance_used_pct' => $derived['isa_allowance_used_pct'],
                    'isa_allowance_used_pct_calculated_at' => $now,
                ])->saveQuietly();
            }
        });

        $this->info('Backfill complete.');

        return self::SUCCESS;
    }
}
