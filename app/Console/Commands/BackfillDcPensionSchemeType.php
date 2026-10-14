<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DCPension;
use App\Services\Stores\Normalisers\PensionNormaliser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * T26 (N2, RC9): pensions saved through Fyn kept `scheme_type` NULL, so a
 * workplace pension paid into through pay was told it receives 0.0% (production
 * Priya, pension 529; 9 of 25 occupational pensions). This sets the missing
 * scheme type from the pension type, by the one rule the forms use
 * (PensionNormaliser::dcSchemeTypeFor). A scheme type already set is left alone.
 */
class BackfillDcPensionSchemeType extends Command
{
    protected $signature = 'pensions:backfill-dc-scheme-type
                            {--dry-run : List the pensions that would change, without changing them}';

    protected $description = 'Set the missing scheme type on defined contribution pensions from their pension type (T26)';

    public function handle(): int
    {
        $pensions = DCPension::query()->whereNull('scheme_type')->get()
            ->filter(fn (DCPension $p): bool => PensionNormaliser::dcSchemeTypeFor((string) $p->pension_type) !== null)
            ->values();
        $ids = $pensions->pluck('id')->implode(', ');

        if ($this->option('dry-run')) {
            $this->info("Would set the scheme type on {$pensions->count()} pension(s): {$ids}");

            return Command::SUCCESS;
        }

        foreach ($pensions as $pension) {
            $pension->forceFill(['scheme_type' => PensionNormaliser::dcSchemeTypeFor((string) $pension->pension_type)])->save();
        }

        $this->info("Set the scheme type on {$pensions->count()} pension(s): {$ids}");
        Log::info('[BackfillDcPensionSchemeType] Set the missing scheme type', ['pension_ids' => $pensions->pluck('id')->all()]);

        return Command::SUCCESS;
    }
}
