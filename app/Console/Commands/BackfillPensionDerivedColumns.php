<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DBPension;
use App\Models\DCPension;
use App\Models\StatePension;
use App\Services\Stores\Recalc\PensionDerivedColumnCalculator;
use Illuminate\Console\Command;

class BackfillPensionDerivedColumns extends Command
{
    protected $signature = 'pensions:backfill-derived';

    protected $description = 'One-off backfill of canonical derived columns for existing DC, DB, and State pension rows';

    public function handle(PensionDerivedColumnCalculator $calc): int
    {
        $this->info('Backfilling DC pensions...');
        DCPension::with('user')->chunkById(200, function ($chunk) use ($calc) {
            foreach ($chunk as $pension) {
                $derived = $calc->calculateDc($pension, $pension->user);
                $now = now();
                $pension->forceFill([
                    'current_fund_value_gbp' => $derived['current_fund_value_gbp'],
                    'current_fund_value_gbp_calculated_at' => $now,
                    'projected_value_at_retirement_gbp' => $derived['projected_value_at_retirement_gbp'],
                    'projected_value_at_retirement_gbp_calculated_at' => $now,
                    'annual_contribution_gbp' => $derived['annual_contribution_gbp'],
                    'annual_contribution_gbp_calculated_at' => $now,
                    'years_to_drawdown' => $derived['years_to_drawdown'],
                    'years_to_drawdown_calculated_at' => $now,
                    'annual_allowance_used_gbp' => $derived['annual_allowance_used_gbp'],
                    'annual_allowance_used_gbp_calculated_at' => $now,
                ])->saveQuietly();
            }
        });

        $this->info('Backfilling DB pensions...');
        DBPension::with('user')->chunkById(200, function ($chunk) use ($calc) {
            foreach ($chunk as $pension) {
                $derived = $calc->calculateDb($pension, $pension->user);
                $now = now();
                $pension->forceFill([
                    'projected_annual_pension_at_nra_gbp' => $derived['projected_annual_pension_at_nra_gbp'],
                    'projected_annual_pension_at_nra_gbp_calculated_at' => $now,
                    'spouse_pension_projected_gbp' => $derived['spouse_pension_projected_gbp'],
                    'spouse_pension_projected_gbp_calculated_at' => $now,
                ])->saveQuietly();
            }
        });

        $this->info('Backfilling State pensions...');
        StatePension::with('user')->chunkById(200, function ($chunk) use ($calc) {
            foreach ($chunk as $state) {
                $derived = $calc->calculateState($state, $state->user);
                $now = now();
                $state->forceFill([
                    'state_pension_forecast_annual_gbp' => $derived['state_pension_forecast_annual_gbp'],
                    'state_pension_forecast_annual_gbp_calculated_at' => $now,
                    'ni_completion_pct' => $derived['ni_completion_pct'],
                    'ni_completion_pct_calculated_at' => $now,
                    'years_to_state_pension_age' => $derived['years_to_state_pension_age'],
                    'years_to_state_pension_age_calculated_at' => $now,
                ])->saveQuietly();
            }
        });

        $this->info('Backfill complete.');

        return self::SUCCESS;
    }
}
