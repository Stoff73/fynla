<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Savings\MarketRates\MarketRateFetchException;
use App\Services\Savings\MarketRates\MarketRateRefreshService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * F20: pull the MoneySavingExpert best-buy savings rates into the active tax
 * year. Scheduled quarterly; run by hand with --html when the site challenges
 * the server (save the two pages from a browser first).
 */
class RefreshSavingsMarketRates extends Command
{
    protected $signature = 'savings:refresh-market-rates
        {--html=* : Saved MoneySavingExpert page(s) to parse instead of fetching}
        {--dry-run : Parse and report, write nothing}';

    protected $description = 'Refresh the savings market benchmark rates from MoneySavingExpert';

    public function handle(MarketRateRefreshService $service): int
    {
        $files = (array) $this->option('html');
        $documents = null;
        if ($files !== []) {
            $documents = [];
            foreach ($files as $file) {
                $html = is_readable($file) ? file_get_contents($file) : false;
                if ($html === false) {
                    $this->error("Cannot read {$file}");

                    return self::FAILURE;
                }
                $documents[] = $html;
            }
        }

        try {
            $summary = $service->refresh($documents, (bool) $this->option('dry-run'));
        } catch (MarketRateFetchException $e) {
            Log::warning('[savings:refresh-market-rates] '.$e->getMessage());
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(($this->option('dry-run') ? 'Dry run for ' : 'Refreshed ').$summary['tax_year']);
        foreach (['created', 'updated', 'unchanged', 'skipped'] as $bucket) {
            foreach ($summary[$bucket] as $line) {
                $this->line(sprintf('  %-9s %s', $bucket, $line));
            }
        }

        return self::SUCCESS;
    }
}
